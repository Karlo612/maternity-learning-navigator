from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import classification_report, log_loss
from sklearn.pipeline import FeatureUnion, Pipeline

from .governance import (
    EvaluationRow,
    load_approved_evaluation_rows,
    load_approved_training_rows,
    load_reviewed_safety_rules,
    sha256_file,
)


def split(frame: pd.DataFrame) -> tuple[pd.DataFrame, pd.DataFrame, pd.DataFrame]:
    family_table = frame.groupby("paraphrase_family_id", as_index=False).agg(
        language=("language", "first"), category=("category", "first"),
    )
    rng = np.random.default_rng(41)
    assignments: dict[str, str] = {}
    for _, stratum in family_table.groupby(["language", "category"]):
        families = stratum.paraphrase_family_id.to_numpy().copy()
        rng.shuffle(families)
        validation_count = max(1, int(round(len(families) * .15)))
        test_count = max(1, int(round(len(families) * .15)))
        train_end = len(families) - validation_count - test_count
        validation_end = train_end + validation_count
        for family in families[:train_end]:
            assignments[str(family)] = "train"
        for family in families[train_end:validation_end]:
            assignments[str(family)] = "validation"
        for family in families[validation_end:]:
            assignments[str(family)] = "test"
    partitions = frame.paraphrase_family_id.map(assignments)
    return (
        frame.loc[partitions == "train"].copy(),
        frame.loc[partitions == "validation"].copy(),
        frame.loc[partitions == "test"].copy(),
    )


def locale_code(language: str) -> str:
    return "ckb" if language == "Kurdish Sorani" else "en"


def threshold_proposal(frame: pd.DataFrame, probabilities: np.ndarray, predictions: np.ndarray) -> dict[str, dict[str, float]]:
    proposals: dict[str, dict[str, float]] = {}
    confidence = probabilities.max(axis=1)
    for language in sorted(frame.language.unique()):
        mask = frame.language.to_numpy() == language
        correct = predictions[mask] == frame.category.to_numpy()[mask]
        locale_confidence = confidence[mask]

        def choose(target_accuracy: float, minimum_coverage: float, fallback: float) -> float:
            for candidate in np.linspace(.40, .95, 56):
                accepted = locale_confidence >= candidate
                if accepted.mean() >= minimum_coverage and accepted.any() and correct[accepted].mean() >= target_accuracy:
                    return round(float(candidate), 2)
            return fallback

        medium = choose(.80, .50, .65)
        high = max(medium, choose(.90, .25, .85))
        correct_confidence = locale_confidence[correct]
        unsupported = round(float(np.clip(np.quantile(correct_confidence, .10), .25, .45)), 2) if correct_confidence.size else .30
        proposals[locale_code(language)] = {"high": high, "medium": medium, "unsupported": unsupported}
    return proposals


def fixture_recall(
    fixtures: list[EvaluationRow], router: Pipeline, thresholds: dict[str, dict[str, float]],
    safety_phrases: dict[str, list[str]],
) -> dict[str, dict[str, float]]:
    results: dict[str, dict[str, float]] = {}
    for language in ("English", "Kurdish Sorani"):
        locale = locale_code(language)
        language_rows = [row for row in fixtures if row.language == language]
        safety = [row for row in language_rows if row.safety_class == "safety_bypass"]
        out_of_scope = [row for row in language_rows if row.safety_class == "out_of_scope"]
        safety_hits = sum(
            any(phrase in " ".join(row.question.split()).casefold() for phrase in safety_phrases[locale])
            for row in safety
        )
        probabilities = router.predict_proba([row.question for row in out_of_scope])
        rejected = int((probabilities.max(axis=1) < thresholds[locale]["unsupported"]).sum())
        results[locale] = {
            "safety_bypass_recall": safety_hits / len(safety),
            "out_of_scope_recall": rejected / len(out_of_scope),
        }
    return results


def train(dataset: Path, safety_rules: Path, output: Path) -> None:
    rows = load_approved_training_rows(dataset)
    evaluation_rows = load_approved_evaluation_rows(dataset)
    safety_phrases = load_reviewed_safety_rules(safety_rules)
    frame = pd.DataFrame([row.__dict__ for row in rows])
    train_frame, validation_frame, test_frame = split(frame)
    features = FeatureUnion([
        ("word", TfidfVectorizer(ngram_range=(1, 2), min_df=2, sublinear_tf=True)),
        ("char", TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5), min_df=2, sublinear_tf=True)),
    ])
    pipeline = Pipeline([
        ("features", features),
        ("classifier", LogisticRegression(max_iter=3000, class_weight="balanced", random_state=41)),
    ])
    pipeline.fit(train_frame.question, train_frame.category)
    validation_probabilities = pipeline.predict_proba(validation_frame.question)
    validation_predictions = pipeline.classes_[validation_probabilities.argmax(axis=1)]
    thresholds = threshold_proposal(validation_frame, validation_probabilities, validation_predictions)
    test_probabilities = pipeline.predict_proba(test_frame.question)
    test_predictions = pipeline.classes_[test_probabilities.argmax(axis=1)]
    report = classification_report(test_frame.category, test_predictions, output_dict=True, zero_division=0)
    by_language = {
        locale_code(language): classification_report(
            test_frame.loc[test_frame.language == language, "category"],
            test_predictions[test_frame.language.to_numpy() == language],
            output_dict=True,
            zero_division=0,
        )
        for language in sorted(test_frame.language.unique())
    }
    calibration = {
        locale_code(language): {
            "multiclass_log_loss": log_loss(
                test_frame.loc[test_frame.language == language, "category"],
                test_probabilities[test_frame.language.to_numpy() == language],
                labels=list(pipeline.classes_),
            )
        }
        for language in sorted(test_frame.language.unique())
    }
    fixture_metrics = fixture_recall(evaluation_rows, pipeline, thresholds, safety_phrases)
    output.mkdir(parents=True, exist_ok=True)
    artifact = output / "baseline.joblib"
    joblib.dump({
        "schema_version": 1,
        "router": pipeline,
        "safety_phrases": safety_phrases,
    }, artifact)
    manifest = {
        "schema_version": 1,
        "model_id": "baseline-tfidf-logreg",
        "version": sha256_file(dataset)[:12],
        "artifact": artifact.name,
        "sha256": hashlib.sha256(artifact.read_bytes()).hexdigest(),
        "dataset_sha256": sha256_file(dataset),
        "release_status": "evaluation_only",
        "confidence_thresholds": thresholds,
        "metrics": {
            "routing_overall": report,
            "routing_by_language": by_language,
            "calibration_by_language": calibration,
            "safety_and_scope_by_language": fixture_metrics,
        },
        "split_counts": {"train": len(train_frame), "validation": len(validation_frame), "test": len(test_frame)},
        "limitations": "Generated by the gated pipeline; requires language, clinical-safety and metric review before serving.",
    }
    (output / "manifest.json").write_text(json.dumps(manifest, indent=2), encoding="utf-8")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset", type=Path, required=True)
    parser.add_argument("--safety-rules", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    train(args.dataset, args.safety_rules, args.output)
