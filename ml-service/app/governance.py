from __future__ import annotations

import csv
import hashlib
import json
from dataclasses import dataclass
from pathlib import Path


EXPECTED_CATEGORIES = {
    "antenatal-appointments",
    "birth-place-choices",
    "labour-preparation",
    "pain-relief-information",
    "after-birth-postnatal",
    "feeding-support",
}
EXPECTED_LANGUAGES = {"English", "Kurdish Sorani"}


class ReviewGateLocked(RuntimeError):
    """Raised when reviewed data is insufficient for training or release."""


@dataclass(frozen=True)
class ApprovedRow:
    question_id: str
    language: str
    question: str
    category: str
    source_id: str
    paraphrase_family_id: str


@dataclass(frozen=True)
class EvaluationRow:
    question_id: str
    language: str
    question: str
    safety_class: str
    paraphrase_family_id: str


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def load_approved_training_rows(path: Path) -> list[ApprovedRow]:
    if not path.exists():
        raise ReviewGateLocked(f"Training dataset not found: {path}")

    with path.open(encoding="utf-8-sig", newline="") as stream:
        rows = list(csv.DictReader(stream))

    eligible: list[ApprovedRow] = []
    for row in rows:
        if row.get("training_eligible") != "true":
            continue
        if row.get("review_status") != "approved":
            raise ReviewGateLocked(f"{row.get('question_id')}: eligible row is not approved")
        language = row.get("language", "")
        if language == "Kurdish Sorani" and row.get("translation_status") != "human_reviewed":
            raise ReviewGateLocked(f"{row.get('question_id')}: Sorani row lacks human review")
        if row.get("safety_class") != "educational":
            raise ReviewGateLocked(f"{row.get('question_id')}: non-educational row entered training data")
        family = row.get("paraphrase_family_id")
        if not family:
            raise ReviewGateLocked(f"{row.get('question_id')}: paraphrase family is required")
        eligible.append(ApprovedRow(
            question_id=row["question_id"], language=language, question=row["question"],
            category=row["category"], source_id=row["source_id"], paraphrase_family_id=family,
        ))

    counts = {(language, category): 0 for language in EXPECTED_LANGUAGES for category in EXPECTED_CATEGORIES}
    for row in eligible:
        if row.language not in EXPECTED_LANGUAGES or row.category not in EXPECTED_CATEGORIES:
            raise ReviewGateLocked(f"{row.question_id}: unexpected language or category")
        counts[(row.language, row.category)] += 1
    family_labels: dict[str, tuple[str, str]] = {}
    family_counts = {(language, category): set() for language in EXPECTED_LANGUAGES for category in EXPECTED_CATEGORIES}
    for row in eligible:
        label = (row.language, row.category)
        previous = family_labels.setdefault(row.paraphrase_family_id, label)
        if previous != label:
            raise ReviewGateLocked(f"{row.question_id}: paraphrase family crosses language or category boundaries")
        family_counts[label].add(row.paraphrase_family_id)
    short = {f"{language}/{category}": count for (language, category), count in counts.items() if count < 50}
    if short:
        raise ReviewGateLocked(f"Each language/category requires 50 approved rows: {json.dumps(short, sort_keys=True)}")
    sparse_families = {
        f"{language}/{category}": len(families)
        for (language, category), families in family_counts.items()
        if len(families) < 3
    }
    if sparse_families:
        raise ReviewGateLocked(f"Each language/category requires at least three paraphrase families: {json.dumps(sparse_families, sort_keys=True)}")
    return eligible


def load_approved_evaluation_rows(path: Path) -> list[EvaluationRow]:
    with path.open(encoding="utf-8-sig", newline="") as stream:
        rows = list(csv.DictReader(stream))

    fixtures: list[EvaluationRow] = []
    for row in rows:
        safety_class = row.get("safety_class", "")
        if safety_class not in {"out_of_scope", "safety_bypass"}:
            continue
        if row.get("training_eligible") != "false" or row.get("review_status") != "approved":
            continue
        language = row.get("language", "")
        if language == "Kurdish Sorani" and row.get("translation_status") != "human_reviewed":
            raise ReviewGateLocked(f"{row.get('question_id')}: Sorani evaluation fixture lacks human review")
        family = row.get("paraphrase_family_id", "")
        if not family:
            raise ReviewGateLocked(f"{row.get('question_id')}: evaluation fixture needs a paraphrase family")
        fixtures.append(EvaluationRow(
            question_id=row["question_id"], language=language, question=row["question"],
            safety_class=safety_class, paraphrase_family_id=family,
        ))

    counts = {(language, fixture_type): 0 for language in EXPECTED_LANGUAGES for fixture_type in {"out_of_scope", "safety_bypass"}}
    for row in fixtures:
        if row.language not in EXPECTED_LANGUAGES:
            raise ReviewGateLocked(f"{row.question_id}: unexpected evaluation language")
        counts[(row.language, row.safety_class)] += 1
    short = {f"{language}/{fixture_type}": count for (language, fixture_type), count in counts.items() if count < 30}
    if short:
        raise ReviewGateLocked(f"Each language requires 30 approved evaluation fixtures per type: {json.dumps(short, sort_keys=True)}")
    return fixtures


def load_reviewed_safety_rules(path: Path) -> dict[str, list[str]]:
    if not path.exists():
        raise ReviewGateLocked(f"Clinical-safety rules not found: {path}")
    payload = json.loads(path.read_text(encoding="utf-8"))
    if payload.get("schema_version") != 1 or payload.get("review_status") != "approved":
        raise ReviewGateLocked("Clinical-safety rules are not independently approved.")
    if not payload.get("reviewer_role") or not payload.get("evidence_checksum"):
        raise ReviewGateLocked("Clinical-safety rule approval evidence is incomplete.")
    phrases = payload.get("phrases", {})
    reviewed: dict[str, list[str]] = {}
    for locale in ("en", "ckb"):
        values = phrases.get(locale, [])
        if not isinstance(values, list) or not values or not all(isinstance(value, str) and value.strip() for value in values):
            raise ReviewGateLocked(f"Clinical-safety rules require reviewed {locale} phrases.")
        reviewed[locale] = sorted({" ".join(value.split()).casefold() for value in values})
    return reviewed
