#!/usr/bin/env python3
"""Fail closed when source or question governance requirements are not met."""

from __future__ import annotations

import csv
import hashlib
import json
from collections import Counter
from datetime import date
from pathlib import Path
from urllib.parse import urlparse


ROOT = Path(__file__).resolve().parents[1]
RESOURCES = ROOT / "resources"

SOURCE_FIELDS = {
    "source_id",
    "organisation",
    "title",
    "url",
    "language",
    "category",
    "authority",
    "reuse_status",
    "allowed_use",
    "last_verified",
}
QUESTION_FIELDS = {
    "question_id",
    "language",
    "question",
    "category",
    "source_id",
    "authoring_method",
    "review_status",
    "translation_status",
    "training_eligible",
    "safety_class",
    "paraphrase_family_id",
}
MODEL_FIELDS = {
    "model_id",
    "role",
    "source",
    "version_or_revision",
    "licence",
    "local_status",
    "sorani_evidence",
    "approved_use",
    "limitations",
}
DEMO_FIELDS = {
    "sample_id", "locale", "question", "category", "source_id", "split",
    "paraphrase_family_id", "authoring_method", "translation_status",
    "review_status", "reviewer_name", "reviewer_role", "reviewed_at", "safety_class",
}
EXPECTED_CATEGORIES = {
    "antenatal-appointments", "birth-place-choices", "labour-preparation",
    "pain-relief-information", "after-birth-postnatal", "feeding-support",
}
APPROVED_DOMAINS = {
    "digital.nhs.uk",
    "realbirthcompany.com",
    "www.england.nhs.uk",
    "www.gov.uk",
    "www.leedsth.nhs.uk",
    "www.nhs.uk",
    "www.tommys.org",
    "www.wypartnership.co.uk",
}
ALLOWED_SAFETY_CLASSES = {
    "educational", "urgent_capable", "clinical", "urgent", "out_of_scope", "safety_bypass",
}


def read_csv(path: Path, expected_fields: set[str]) -> list[dict[str, str]]:
    with path.open(encoding="utf-8-sig", newline="") as stream:
        reader = csv.DictReader(stream)
        actual_fields = set(reader.fieldnames or [])
        if actual_fields != expected_fields:
            raise ValueError(
                f"{path.name}: expected fields {sorted(expected_fields)}, "
                f"found {sorted(actual_fields)}"
            )
        rows = list(reader)
    if not rows:
        raise ValueError(f"{path.name}: must not be empty")
    return rows


def require_unique(rows: list[dict[str, str]], key: str, filename: str) -> None:
    values = [row[key] for row in rows]
    duplicates = sorted({value for value in values if values.count(value) > 1})
    if duplicates:
        raise ValueError(f"{filename}: duplicate {key}: {duplicates}")


def sha256_file(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def validate() -> None:
    sources = read_csv(RESOURCES / "source_registry.csv", SOURCE_FIELDS)
    questions = read_csv(RESOURCES / "question_candidates.csv", QUESTION_FIELDS)
    models = read_csv(RESOURCES / "model_registry.csv", MODEL_FIELDS)
    demo = read_csv(RESOURCES / "demo_samples.csv", DEMO_FIELDS)
    require_unique(sources, "source_id", "source_registry.csv")
    require_unique(questions, "question_id", "question_candidates.csv")
    require_unique(models, "model_id", "model_registry.csv")
    require_unique(demo, "sample_id", "demo_samples.csv")

    source_ids = {row["source_id"] for row in sources}
    for source in sources:
        parsed = urlparse(source["url"])
        if parsed.scheme != "https" or parsed.hostname not in APPROVED_DOMAINS:
            raise ValueError(
                f"{source['source_id']}: URL must use HTTPS and an approved domain"
            )
        verified = date.fromisoformat(source["last_verified"])
        if verified > date.today():
            raise ValueError(f"{source['source_id']}: last_verified is in the future")
        if source["source_id"].startswith("RBC-") and not (
            source["reuse_status"] == "proprietary_link_only"
            or source["reuse_status"] == "policy"
        ):
            raise ValueError(
                f"{source['source_id']}: Real Birth material must remain link-only"
            )

    for question in questions:
        identifier = question["question_id"]
        if question["source_id"] not in source_ids:
            raise ValueError(f"{identifier}: unknown source_id")
        if not question["question"].strip():
            raise ValueError(f"{identifier}: empty question")
        if not question["paraphrase_family_id"].strip():
            raise ValueError(f"{identifier}: paraphrase_family_id is required")
        if question["safety_class"] not in ALLOWED_SAFETY_CLASSES:
            raise ValueError(f"{identifier}: invalid safety_class")
        if question["training_eligible"] not in {"true", "false"}:
            raise ValueError(f"{identifier}: training_eligible must be true or false")
        if question["training_eligible"] == "true" and question["review_status"] != "approved":
            raise ValueError(f"{identifier}: training requires approved human review")
        if question["language"] == "Kurdish Sorani" and question["training_eligible"] == "true":
            if question["translation_status"] != "human_reviewed":
                raise ValueError(
                    f"{identifier}: Sorani training data requires human review"
                )
        if question["safety_class"] != "educational" and question["training_eligible"] == "true":
            raise ValueError(
                f"{identifier}: only educational questions may train the topic router"
            )

    for model in models:
        identifier = model["model_id"]
        if not model["limitations"].strip():
            raise ValueError(f"{identifier}: limitations must be documented")
        if model["source"] != "none":
            parsed = urlparse(model["source"])
            if parsed.scheme != "https" or parsed.hostname not in {
                "huggingface.co",
                "scikit-learn.org",
            }:
                raise ValueError(
                    f"{identifier}: model source is not on an approved HTTPS domain"
                )
        if model["local_status"] == "acquired" and (
            model["version_or_revision"] in {"to_be_pinned", "revision_to_be_pinned", "not_selected"}
            or model["licence"] in {"not_applicable", "unknown"}
        ):
            raise ValueError(
                f"{identifier}: acquired models require a pinned revision and known licence"
            )

    if len(demo) != 144:
        raise ValueError(f"demo_samples.csv: expected exactly 144 rows, found {len(demo)}")
    counts = Counter((row["locale"], row["category"], row["split"]) for row in demo)
    expected_counts = Counter({
        (locale, category, split): count
        for locale in ("en", "ckb")
        for category in EXPECTED_CATEGORIES
        for split, count in {"train": 10, "visible": 1, "hidden": 1}.items()
    })
    if counts != expected_counts:
        raise ValueError(f"demo_samples.csv: invalid locale/category/split distribution: {dict(counts)}")
    for row in demo:
        identifier = row["sample_id"]
        if row["source_id"] not in source_ids:
            raise ValueError(f"{identifier}: unknown source_id")
        if not row["question"].strip() or not row["paraphrase_family_id"].strip():
            raise ValueError(f"{identifier}: question and paraphrase family are required")
        if row["safety_class"] != "educational":
            raise ValueError(f"{identifier}: curated demo content must be educational")
        if row["locale"] == "ckb" and row["review_status"] == "approved":
            if row["translation_status"] != "human_reviewed":
                raise ValueError(f"{identifier}: approved Sorani text must be human reviewed")
        if row["review_status"] == "approved" and not all(
            row[field].strip() for field in ("reviewer_name", "reviewer_role", "reviewed_at")
        ):
            raise ValueError(f"{identifier}: approved demo rows require complete review attribution")
    for locale in ("en", "ckb"):
        for category in EXPECTED_CATEGORIES:
            grouped = [row for row in demo if row["locale"] == locale and row["category"] == category]
            training_families = {row["paraphrase_family_id"] for row in grouped if row["split"] == "train"}
            fixture_families = {row["paraphrase_family_id"] for row in grouped if row["split"] != "train"}
            if training_families & fixture_families:
                raise ValueError(f"demo_samples.csv: paraphrase-family leakage for {locale}/{category}")

    review_path = ROOT / "governance" / "demo_review_manifest.json"
    review = json.loads(review_path.read_text(encoding="utf-8"))
    if review.get("schema_version") != 1 or review.get("decision") not in {
        "changes_required", "approved", "rejected",
    }:
        raise ValueError("demo review manifest: invalid schema or decision")
    dataset_path = ROOT / review["dataset_file"]
    interface_path = ROOT / review["interface_file"]
    if review.get("dataset_sha256") != sha256_file(dataset_path):
        raise ValueError("demo review manifest: dataset checksum mismatch")
    if review.get("interface_sha256") != sha256_file(interface_path):
        raise ValueError("demo review manifest: interface checksum mismatch")
    if review["decision"] == "approved":
        if not all(review.get(field) for field in ("reviewer_name", "reviewer_role", "reviewed_at")):
            raise ValueError("demo review manifest: approval evidence is incomplete")
        if any(row["review_status"] not in {"pending_review", "approved"} for row in demo):
            raise ValueError("demo review manifest: dataset contains an invalid row review state")

    signoffs = json.loads((ROOT / "governance" / "review_signoffs.json").read_text(encoding="utf-8"))
    if signoffs.get("schema_version") != 1:
        raise ValueError("review signoffs: unsupported schema version")
    gates = {row.get("gate") for row in signoffs.get("signoffs", [])}
    if not {"sorani_language", "curated_demo_sorani", "clinical_safety"}.issubset(gates):
        raise ValueError("review signoffs: required gates are missing")
    for signoff in signoffs["signoffs"]:
        if signoff.get("evidence_checksum"):
            evidence = ROOT / signoff["evidence_file"]
            if not evidence.exists() or sha256_file(evidence) != signoff["evidence_checksum"]:
                raise ValueError(f"review signoffs: evidence mismatch for {signoff['gate']}")
    demo_signoff = next(
        row for row in signoffs["signoffs"] if row.get("gate") == "curated_demo_sorani"
    )
    manifest_approved = review["decision"] == "approved"
    signoff_approved = demo_signoff.get("status") == "approved"
    if manifest_approved != signoff_approved:
        raise ValueError("curated demo manifest and release signoff disagree")
    if manifest_approved:
        if any(
            demo_signoff.get(field) != review.get(field)
            for field in ("reviewer_name", "reviewer_role", "reviewed_at")
        ):
            raise ValueError("curated demo manifest and release signoff attribution disagree")
        if demo_signoff.get("evidence_file") != "governance/demo_review_manifest.json":
            raise ValueError("curated demo signoff must reference the approved review manifest")
        if demo_signoff.get("evidence_checksum") != sha256_file(review_path):
            raise ValueError("curated demo signoff is not bound to the review manifest checksum")

    safety_template = json.loads((RESOURCES / "safety_bypass_rules.template.json").read_text(encoding="utf-8"))
    if safety_template.get("schema_version") != 1:
        raise ValueError("safety rules: unsupported schema_version")
    if safety_template.get("review_status") == "approved":
        if not safety_template.get("evidence_checksum"):
            raise ValueError("safety rules: approval requires an evidence checksum")
        for locale in ("en", "ckb"):
            phrases = safety_template.get("phrases", {}).get(locale, [])
            if not phrases or not all(isinstance(phrase, str) and phrase.strip() for phrase in phrases):
                raise ValueError(f"safety rules: approved file requires reviewed {locale} phrases")

    eligible = sum(row["training_eligible"] == "true" for row in questions)
    sorani = sum(row["language"] == "Kurdish Sorani" for row in questions)
    print(f"Validated {len(sources)} registered sources")
    print(f"Validated {len(questions)} candidate questions ({sorani} Sorani)")
    print(f"Validated {len(models)} model decisions")
    print(f"Validated {len(demo)} curated demo questions")
    print(f"Training-eligible questions: {eligible}")
    print("Governance validation passed")


if __name__ == "__main__":
    validate()
