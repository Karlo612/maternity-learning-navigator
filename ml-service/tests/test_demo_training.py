import csv
import hashlib
import json
from pathlib import Path

import pytest

from app.governance import EXPECTED_CATEGORIES
from app.registry import ArtifactError, ModelRegistry
from app.text_processing import normalize_demo_text
from app.train_demo import DemoReviewLocked, train


FIELDS = [
    "sample_id", "locale", "question", "category", "source_id", "split",
    "paraphrase_family_id", "authoring_method", "translation_status",
    "review_status", "reviewer_name", "reviewer_role", "reviewed_at", "safety_class",
]


def sha(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def approved_inputs(tmp_path: Path) -> tuple[Path, Path, Path]:
    dataset = tmp_path / "demo.csv"
    with dataset.open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=FIELDS)
        writer.writeheader()
        for locale in ("en", "ckb"):
            for category in sorted(EXPECTED_CATEGORIES):
                for index in range(1, 13):
                    split = "train" if index <= 10 else "visible" if index == 11 else "hidden"
                    writer.writerow({
                        "sample_id": f"{locale}-{category}-{index:02d}",
                        "locale": locale,
                        "question": f"{locale} {category} reviewed learning example {index}",
                        "category": category,
                        "source_id": "NHS-TEST",
                        "split": split,
                        "paraphrase_family_id": f"{locale}-{category}-family-{index:02d}",
                        "authoring_method": "test_fixture",
                        "translation_status": "draft_machine_assisted" if locale == "ckb" else "not_applicable",
                        "review_status": "pending_review",
                        "reviewer_name": "",
                        "reviewer_role": "",
                        "reviewed_at": "",
                        "safety_class": "educational",
                    })
    interface = tmp_path / "interface.json"
    interface.write_text('{"reviewed":true}', encoding="utf-8")
    review = tmp_path / "review.json"
    review.write_text(json.dumps({
        "schema_version": 1,
        "decision": "approved",
        "reviewer_name": "Test reviewer",
        "reviewer_role": "Test language reviewer",
        "reviewed_at": "2026-08-11T00:00:00+01:00",
        "dataset_sha256": sha(dataset),
        "interface_sha256": sha(interface),
    }), encoding="utf-8")
    return dataset, interface, review


def test_demo_training_is_locked_until_exact_review_manifest_is_approved(tmp_path: Path) -> None:
    dataset, interface, review = approved_inputs(tmp_path)
    contents = json.loads(review.read_text(encoding="utf-8"))
    contents["decision"] = "changes_required"
    review.write_text(json.dumps(contents), encoding="utf-8")

    with pytest.raises(DemoReviewLocked, match="not approved"):
        train(dataset, review, interface, tmp_path / "output")


def test_demo_training_packages_isolated_checksum_verified_fixture_model(tmp_path: Path) -> None:
    dataset, interface, review = approved_inputs(tmp_path)
    output = tmp_path / "output"

    train(dataset, review, interface, output)

    manifest = json.loads((output / "manifest.json").read_text(encoding="utf-8"))
    assert manifest["intended_mode"] == "curated_demo"
    assert manifest["release_status"] == "demo_approved"
    assert all(row["passed"] for row in manifest["fixture_checks"]["visible"])
    assert all(row["passed"] for row in manifest["fixture_checks"]["hidden"])
    assert manifest["training_configuration"]["random_seed"] == 41
    registry = ModelRegistry(output, expected_mode="curated_demo", required_release_status="demo_approved")
    assert registry.ready is True

    (output / "demo.joblib").write_bytes((output / "demo.joblib").read_bytes() + b"tampered")
    with pytest.raises(ArtifactError, match="checksum"):
        ModelRegistry(output, expected_mode="curated_demo", required_release_status="demo_approved")


def test_demo_topic_synonym_normalisation_is_explicit_and_deterministic() -> None:
    assert normalize_demo_text("Ways pain may be managed during labour") == (
        "ways pain may be pain relief during labour"
    )


def test_demo_training_rejects_modified_reviewed_content(tmp_path: Path) -> None:
    dataset, interface, review = approved_inputs(tmp_path)
    interface.write_text('{"reviewed":false}', encoding="utf-8")

    with pytest.raises(DemoReviewLocked, match="interface checksum"):
        train(dataset, review, interface, tmp_path / "output")
