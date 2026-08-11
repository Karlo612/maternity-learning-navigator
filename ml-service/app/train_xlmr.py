from __future__ import annotations

import argparse
import hashlib
import json
import re
from pathlib import Path

import pandas as pd
import torch
import numpy as np
from sklearn.metrics import classification_report, log_loss
from torch.utils.data import Dataset
from transformers import AutoModelForSequenceClassification, AutoTokenizer, Trainer, TrainingArguments

from .governance import (
    load_approved_evaluation_rows,
    load_approved_training_rows,
    load_reviewed_safety_rules,
    sha256_file,
)
from .train import fixture_recall, locale_code, split, threshold_proposal


class QuestionDataset(Dataset):
    def __init__(self, frame: pd.DataFrame, tokenizer, label_to_id: dict[str, int]):
        self.encodings = tokenizer(frame.question.tolist(), truncation=True, padding=True, max_length=128)
        self.labels = [label_to_id[label] for label in frame.category]

    def __len__(self) -> int:
        return len(self.labels)

    def __getitem__(self, index: int) -> dict:
        item = {key: torch.tensor(values[index]) for key, values in self.encodings.items()}
        item["labels"] = torch.tensor(self.labels[index])
        return item


class TransformerProbabilityAdapter:
    def __init__(self, model, tokenizer, classes: list[str]):
        self.model = model
        self.tokenizer = tokenizer
        self.classes_ = np.array(classes)

    def predict_proba(self, questions: list[str]) -> np.ndarray:
        batches: list[np.ndarray] = []
        self.model.eval()
        with torch.inference_mode():
            for start in range(0, len(questions), 32):
                encoded = self.tokenizer(
                    questions[start:start + 32], truncation=True, padding=True, max_length=128, return_tensors="pt",
                )
                logits = self.model(**encoded).logits
                batches.append(torch.softmax(logits, dim=1).cpu().numpy())
        return np.concatenate(batches, axis=0)


def train(dataset: Path, safety_rules: Path, output: Path, revision: str) -> None:
    if re.fullmatch(r"[0-9a-f]{40}", revision) is None:
        raise ValueError("A 40-character immutable XLM-R commit revision is required.")
    rows = load_approved_training_rows(dataset)
    evaluation_rows = load_approved_evaluation_rows(dataset)
    safety_phrases = load_reviewed_safety_rules(safety_rules)
    frame = pd.DataFrame([row.__dict__ for row in rows])
    train_frame, validation_frame, test_frame = split(frame)
    labels = sorted(frame.category.unique())
    label_to_id = {label: index for index, label in enumerate(labels)}
    id_to_label = {index: label for label, index in label_to_id.items()}
    model_name = "FacebookAI/xlm-roberta-base"
    tokenizer = AutoTokenizer.from_pretrained(model_name, revision=revision)
    model = AutoModelForSequenceClassification.from_pretrained(
        model_name, revision=revision, num_labels=len(labels), label2id=label_to_id, id2label=id_to_label,
    )
    arguments = TrainingArguments(
        output_dir=str(output / "checkpoints"), num_train_epochs=6, learning_rate=2e-5,
        per_device_train_batch_size=8, per_device_eval_batch_size=16, weight_decay=.01,
        eval_strategy="epoch", save_strategy="epoch", load_best_model_at_end=True,
        metric_for_best_model="eval_loss", seed=41, data_seed=41, report_to=[],
    )
    trainer = Trainer(
        model=model, args=arguments,
        train_dataset=QuestionDataset(train_frame, tokenizer, label_to_id),
        eval_dataset=QuestionDataset(validation_frame, tokenizer, label_to_id),
    )
    trainer.train()
    adapter = TransformerProbabilityAdapter(model, tokenizer, [id_to_label[index] for index in sorted(id_to_label)])
    validation_probabilities = adapter.predict_proba(validation_frame.question.tolist())
    validation_predictions = adapter.classes_[validation_probabilities.argmax(axis=1)]
    thresholds = threshold_proposal(validation_frame, validation_probabilities, validation_predictions)
    test_probabilities = adapter.predict_proba(test_frame.question.tolist())
    predictions = test_probabilities.argmax(axis=1)
    predicted_labels = adapter.classes_[predictions]
    report = classification_report(
        test_frame.category, predicted_labels, labels=list(adapter.classes_), output_dict=True, zero_division=0,
    )
    by_language = {
        locale_code(language): classification_report(
            test_frame.loc[test_frame.language == language, "category"],
            predicted_labels[test_frame.language.to_numpy() == language],
            labels=list(adapter.classes_), output_dict=True, zero_division=0,
        )
        for language in sorted(test_frame.language.unique())
    }
    calibration = {
        locale_code(language): {
            "multiclass_log_loss": log_loss(
                test_frame.loc[test_frame.language == language, "category"],
                test_probabilities[test_frame.language.to_numpy() == language],
                labels=list(adapter.classes_),
            )
        }
        for language in sorted(test_frame.language.unique())
    }
    fixture_metrics = fixture_recall(evaluation_rows, adapter, thresholds, safety_phrases)
    artifact = output / "xlm-roberta"
    artifact.mkdir(parents=True, exist_ok=True)
    trainer.save_model(str(artifact))
    tokenizer.save_pretrained(str(artifact))
    (artifact / "navigator_bundle.json").write_text(json.dumps({
        "schema_version": 1,
        "safety_phrases": safety_phrases,
    }, indent=2, ensure_ascii=False), encoding="utf-8")
    digest = hashlib.sha256()
    for path in sorted(artifact.rglob("*")):
        if path.is_file():
            digest.update(path.relative_to(artifact).as_posix().encode())
            digest.update(path.read_bytes())
    manifest = {
        "schema_version": 1,
        "model_id": "xlm-roberta-base", "upstream_revision": revision,
        "version": sha256_file(dataset)[:12], "artifact": artifact.name,
        "sha256": digest.hexdigest(), "dataset_sha256": sha256_file(dataset),
        "release_status": "evaluation_only",
        "confidence_thresholds": thresholds,
        "metrics": {
            "routing_overall": report,
            "routing_by_language": by_language,
            "calibration_by_language": calibration,
            "safety_and_scope_by_language": fixture_metrics,
        },
        "split_counts": {"train": len(train_frame), "validation": len(validation_frame), "test": len(test_frame)},
        "limitations": "Sorani feasibility evidence is not maternity-domain validation. Human and metric review are required before serving.",
    }
    (output / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset", type=Path, required=True)
    parser.add_argument("--safety-rules", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--revision", required=True)
    args = parser.parse_args()
    train(args.dataset, args.safety_rules, args.output, args.revision)
