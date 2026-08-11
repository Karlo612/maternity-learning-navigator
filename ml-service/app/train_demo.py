from __future__ import annotations

import argparse
import csv
import hashlib
import json
from collections import Counter
from dataclasses import dataclass
from pathlib import Path

import joblib
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import FeatureUnion, Pipeline

from .governance import EXPECTED_CATEGORIES, sha256_file


EXPECTED_LOCALES = {"en", "ckb"}
EXPECTED_SPLITS = {"train": 10, "visible": 1, "hidden": 1}


class DemoReviewLocked(RuntimeError):
    pass


@dataclass(frozen=True)
class DemoRow:
    sample_id: str
    locale: str
    question: str
    category: str
    source_id: str
    split: str
    paraphrase_family_id: str


def load_reviewed_demo_rows(dataset: Path, review_manifest: Path, interface_catalog: Path) -> list[DemoRow]:
    review = json.loads(review_manifest.read_text(encoding="utf-8"))
    if review.get("schema_version") != 1 or review.get("decision") != "approved":
        raise DemoReviewLocked("The curated demo review manifest is not approved.")
    if review.get("dataset_sha256") != sha256_file(dataset):
        raise DemoReviewLocked("The approved demo dataset checksum does not match.")
    if review.get("interface_sha256") != sha256_file(interface_catalog):
        raise DemoReviewLocked("The approved interface checksum does not match.")
    if not review.get("reviewer_name") or not review.get("reviewer_role") or not review.get("reviewed_at"):
        raise DemoReviewLocked("The curated demo review evidence is incomplete.")

    with dataset.open(encoding="utf-8-sig", newline="") as stream:
        raw_rows = list(csv.DictReader(stream))

    rows: list[DemoRow] = []
    for raw in raw_rows:
        identifier = raw.get("sample_id", "unknown")
        # The manifest is the sign-off record for the exact immutable CSV and
        # interface hashes. Row fields retain their pre-sign-off provenance so
        # approving the corpus never changes the content checksum being signed.
        if raw.get("review_status") not in {"pending_review", "approved"}:
            raise DemoReviewLocked(f"{identifier}: demo row has an invalid review state")
        if raw.get("locale") == "ckb" and raw.get("translation_status") not in {
            "draft_machine_assisted", "human_reviewed",
        }:
            raise DemoReviewLocked(f"{identifier}: Sorani demo row has invalid translation provenance")
        if raw.get("safety_class") != "educational":
            raise DemoReviewLocked(f"{identifier}: demo rows must be non-urgent educational questions")
        row = DemoRow(
            sample_id=identifier,
            locale=raw["locale"],
            question=raw["question"].strip(),
            category=raw["category"],
            source_id=raw["source_id"],
            split=raw["split"],
            paraphrase_family_id=raw["paraphrase_family_id"],
        )
        if row.locale not in EXPECTED_LOCALES or row.category not in EXPECTED_CATEGORIES:
            raise DemoReviewLocked(f"{identifier}: unexpected locale or category")
        if not row.question or row.split not in EXPECTED_SPLITS or not row.paraphrase_family_id:
            raise DemoReviewLocked(f"{identifier}: incomplete demo row")
        rows.append(row)

    counts = Counter((row.locale, row.category, row.split) for row in rows)
    expected = {
        (locale, category, split): count
        for locale in EXPECTED_LOCALES
        for category in EXPECTED_CATEGORIES
        for split, count in EXPECTED_SPLITS.items()
    }
    if counts != Counter(expected):
        raise DemoReviewLocked(f"Demo dataset must have the exact reviewed split counts: {dict(counts)}")
    if len({row.sample_id for row in rows}) != len(rows):
        raise DemoReviewLocked("Demo sample IDs must be unique.")
    return rows


def train(dataset: Path, review_manifest: Path, interface_catalog: Path, output: Path) -> None:
    rows = load_reviewed_demo_rows(dataset, review_manifest, interface_catalog)
    train_rows = [row for row in rows if row.split == "train"]
    visible_rows = [row for row in rows if row.split == "visible"]
    hidden_rows = [row for row in rows if row.split == "hidden"]
    router = Pipeline([
        ("features", FeatureUnion([
            ("word", TfidfVectorizer(ngram_range=(1, 2), min_df=1, sublinear_tf=True)),
            ("char", TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5), min_df=1, sublinear_tf=True)),
        ])),
        ("classifier", LogisticRegression(max_iter=3_000, class_weight="balanced", random_state=41)),
    ])
    router.fit([row.question for row in train_rows], [row.category for row in train_rows])

    def fixture_results(fixtures: list[DemoRow]) -> list[dict[str, str | bool]]:
        predictions = router.predict([row.question for row in fixtures])
        return [
            {
                "sample_id": row.sample_id,
                "expected": row.category,
                "predicted": str(predicted),
                "passed": str(predicted) == row.category,
            }
            for row, predicted in zip(fixtures, predictions, strict=True)
        ]

    visible_results = fixture_results(visible_rows)
    hidden_results = fixture_results(hidden_rows)
    failures = [result for result in visible_results + hidden_results if not result["passed"]]
    if failures:
        raise DemoReviewLocked(f"Curated demo fixtures failed: {json.dumps(failures, ensure_ascii=False)}")

    output.mkdir(parents=True, exist_ok=True)
    artifact = output / "demo.joblib"
    joblib.dump({
        "schema_version": 1,
        "router": router,
        "safety_phrases": {
            "en": ["__fixed_demo_does_not_accept_free_text__"],
            "ckb": ["__fixed_demo_does_not_accept_free_text_ckb__"],
        },
    }, artifact)
    dataset_hash = sha256_file(dataset)
    manifest = {
        "schema_version": 1,
        "model_id": "demo-tfidf-logreg",
        "version": dataset_hash[:12],
        "intended_mode": "curated_demo",
        "release_status": "demo_approved",
        "artifact": artifact.name,
        "sha256": hashlib.sha256(artifact.read_bytes()).hexdigest(),
        "dataset_sha256": dataset_hash,
        "review_manifest_sha256": sha256_file(review_manifest),
        "interface_sha256": sha256_file(interface_catalog),
        "class_names": sorted(EXPECTED_CATEGORIES),
        "explanation": {"method": "LIME", "random_seed": 41, "num_samples": 1_000},
        "confidence_thresholds": {
            "en": {"high": .60, "medium": .20, "unsupported": .10},
            "ckb": {"high": .60, "medium": .20, "unsupported": .10},
        },
        "fixture_checks": {"visible": visible_results, "hidden": hidden_results},
        "limitations": "Fixed portfolio demonstration only; fixture checks are not a general accuracy estimate.",
    }
    (output / "manifest.json").write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--dataset", type=Path, required=True)
    parser.add_argument("--review-manifest", type=Path, required=True)
    parser.add_argument("--interface-catalog", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    train(args.dataset, args.review_manifest, args.interface_catalog, args.output)
