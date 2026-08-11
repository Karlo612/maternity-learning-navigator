import csv
from pathlib import Path

import pytest
import pandas as pd

from app.governance import ReviewGateLocked, load_approved_training_rows
from app.train import split


def test_training_fails_closed_when_no_rows_are_eligible(tmp_path: Path) -> None:
    dataset = tmp_path / "questions.csv"
    fields = ["question_id","language","question","category","source_id","authoring_method","review_status","translation_status","training_eligible","safety_class","paraphrase_family_id"]
    with dataset.open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=fields)
        writer.writeheader()
        writer.writerow({
            "question_id":"EN-001","language":"English","question":"Example","category":"antenatal-appointments",
            "source_id":"NHS-006","authoring_method":"heading_to_question","review_status":"pending_content_review",
            "translation_status":"not_applicable","training_eligible":"false","safety_class":"educational","paraphrase_family_id":"family-1",
        })
    with pytest.raises(ReviewGateLocked, match="requires 50 approved rows"):
        load_approved_training_rows(dataset)


def test_split_keeps_paraphrase_families_isolated_and_stratified() -> None:
    rows = []
    for language in ("English", "Kurdish Sorani"):
        for category in ("antenatal-appointments", "feeding-support"):
            for family in range(10):
                for variant in range(2):
                    rows.append({
                        "question": f"{language}-{category}-{family}-{variant}",
                        "language": language,
                        "category": category,
                        "paraphrase_family_id": f"{language}-{category}-{family}",
                    })
    train, validation, test = split(pd.DataFrame(rows))

    family_sets = [set(part.paraphrase_family_id) for part in (train, validation, test)]
    assert family_sets[0].isdisjoint(family_sets[1])
    assert family_sets[0].isdisjoint(family_sets[2])
    assert family_sets[1].isdisjoint(family_sets[2])
    for part in (train, validation, test):
        assert set(zip(part.language, part.category)) == {
            ("English", "antenatal-appointments"),
            ("English", "feeding-support"),
            ("Kurdish Sorani", "antenatal-appointments"),
            ("Kurdish Sorani", "feeding-support"),
        }
