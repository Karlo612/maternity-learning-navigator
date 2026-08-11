from __future__ import annotations

import hashlib
import json
from pathlib import Path
from typing import Protocol

import joblib
import numpy as np


class ArtifactError(RuntimeError):
    pass


def sha256_path(path: Path) -> str:
    digest = hashlib.sha256()
    if path.is_file():
        return hashlib.sha256(path.read_bytes()).hexdigest()
    for child in sorted(path.rglob("*")):
        if child.is_file():
            digest.update(child.relative_to(path).as_posix().encode())
            digest.update(child.read_bytes())
    return digest.hexdigest()


class Router(Protocol):
    classes_: np.ndarray

    def predict_proba(self, questions: list[str]) -> np.ndarray: ...


class TransformerRouter:
    def __init__(self, artifact_path: Path):
        try:
            import torch
            from transformers import AutoModelForSequenceClassification, AutoTokenizer
        except ImportError as error:
            raise ArtifactError("Transformer runtime dependencies are unavailable.") from error
        self.torch = torch
        self.tokenizer = AutoTokenizer.from_pretrained(str(artifact_path), local_files_only=True)
        self.model = AutoModelForSequenceClassification.from_pretrained(str(artifact_path), local_files_only=True)
        self.model.eval()
        id_to_label = {int(index): label for index, label in self.model.config.id2label.items()}
        self.classes_ = np.array([id_to_label[index] for index in sorted(id_to_label)])

    def predict_proba(self, questions: list[str]) -> np.ndarray:
        batches: list[np.ndarray] = []
        with self.torch.inference_mode():
            for start in range(0, len(questions), 32):
                encoded = self.tokenizer(
                    questions[start:start + 32], truncation=True, padding=True, max_length=128, return_tensors="pt",
                )
                logits = self.model(**encoded).logits
                batches.append(self.torch.softmax(logits, dim=1).cpu().numpy())
        return np.concatenate(batches, axis=0)


class ModelRegistry:
    def __init__(
        self,
        artifact_dir: Path,
        *,
        expected_mode: str = "production",
        required_release_status: str = "approved",
    ):
        self.artifact_dir = artifact_dir
        self.expected_mode = expected_mode
        self.required_release_status = required_release_status
        self.manifest: dict = {}
        self.router: Router | None = None
        self.safety_phrases: dict[str, list[str]] = {}
        self.ready = False
        self.error = "Approved model artifacts have not been created."
        self.reload()

    def reload(self) -> None:
        manifest_path = self.artifact_dir / "manifest.json"
        if not manifest_path.exists():
            return
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        if manifest.get("schema_version") != 1:
            raise ArtifactError("Model manifest schema is unsupported.")
        intended_mode = manifest.get("intended_mode", "production")
        if intended_mode != self.expected_mode:
            raise ArtifactError(
                f"Model artifact is intended for {intended_mode}, not {self.expected_mode}."
            )
        artifact_path = self.artifact_dir / manifest["artifact"]
        if not artifact_path.exists():
            raise ArtifactError("Model artifact is missing.")
        actual = sha256_path(artifact_path)
        if actual != manifest["sha256"]:
            raise ArtifactError("Model artifact checksum does not match the signed manifest.")
        if manifest.get("release_status") != self.required_release_status:
            raise ArtifactError(
                f"Model manifest requires release status {self.required_release_status}."
            )
        if manifest.get("model_id") == "xlm-roberta-base":
            bundle_path = artifact_path / "navigator_bundle.json"
            if not bundle_path.exists():
                raise ArtifactError("Transformer artifact lacks its governed routing bundle.")
            bundle = json.loads(bundle_path.read_text(encoding="utf-8"))
            self.router = TransformerRouter(artifact_path)
        else:
            bundle = joblib.load(artifact_path)
            if "router" not in bundle:
                raise ArtifactError("Model artifact is missing the router.")
            self.router = bundle["router"]
        if not isinstance(bundle, dict) or bundle.get("schema_version") != 1:
            raise ArtifactError("Model artifact schema is unsupported.")
        if "safety_phrases" not in bundle:
            raise ArtifactError("Model artifact is missing the safety layer.")
        safety_phrases = bundle["safety_phrases"]
        if not all(isinstance(safety_phrases.get(locale), list) and safety_phrases[locale] for locale in ("en", "ckb")):
            raise ArtifactError("Model artifact lacks reviewed bilingual safety rules.")
        self.safety_phrases = safety_phrases
        self.manifest = manifest
        self.ready = True
        self.error = ""

    @property
    def model_id(self) -> str:
        fallback = "demo-tfidf-logreg" if self.expected_mode == "curated_demo" else "baseline-tfidf-logreg"
        return self.manifest.get("model_id", fallback)

    @property
    def model_version(self) -> str:
        return self.manifest.get("version", "not-trained")

    @property
    def class_names(self) -> list[str]:
        if self.router is None:
            return []
        return [str(value) for value in self.router.classes_]

    def predict_proba(self, questions: list[str]) -> np.ndarray:
        if not self.ready or self.router is None:
            raise ArtifactError(self.error)
        return self.router.predict_proba(questions)

    def predict(self, question: str, locale: str) -> dict:
        if not self.ready or self.router is None:
            raise ArtifactError(self.error)
        normalized = " ".join(question.split()).casefold()
        if any(phrase in normalized for phrase in self.safety_phrases[locale]):
            return {"status": "safety_bypass", "category": None, "confidence": None, "confidence_band": None}
        probabilities = self.router.predict_proba([question])[0]
        index = int(np.argmax(probabilities))
        label = str(self.router.classes_[index])
        confidence = float(probabilities[index])
        thresholds = self.manifest["confidence_thresholds"][locale]
        if confidence < float(thresholds["unsupported"]):
            return {"status": "unsupported", "category": None, "confidence": confidence, "confidence_band": "low"}
        if confidence < float(thresholds["medium"]):
            return {"status": "low_confidence", "category": None, "confidence": confidence, "confidence_band": "low"}
        band = "high" if confidence >= float(thresholds["high"]) else "medium"
        return {"status": "matched", "category": label, "confidence": confidence, "confidence_band": band}
