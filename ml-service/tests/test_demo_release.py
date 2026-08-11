from __future__ import annotations

import csv
import json
import time
from pathlib import Path

import numpy as np

from app.explanations import EXPLANATION_FEATURES, explain_prediction
from app.registry import ModelRegistry
from app.train_demo import train


ROOT = Path(__file__).resolve().parents[2]


def governed_input(repository_path: Path, container_path: Path) -> Path:
    """Resolve signed inputs in either the checkout or the deployable image."""
    return repository_path if repository_path.exists() else container_path


def test_approved_demo_release_passes_fixtures_lime_and_latency_gates(tmp_path: Path) -> None:
    dataset = governed_input(
        ROOT / "resources" / "demo_samples.csv",
        Path("/governed/demo_samples.csv"),
    )
    review = governed_input(
        ROOT / "governance" / "demo_review_manifest.json",
        Path("/governed/demo_review_manifest.json"),
    )
    interface = governed_input(
        ROOT / "app" / "resources" / "js" / "interface-catalog.json",
        Path("/governed/interface-catalog.json"),
    )
    train(dataset, review, interface, tmp_path)
    manifest = json.loads((tmp_path / "manifest.json").read_text(encoding="utf-8"))
    assert len(manifest["fixture_checks"]["visible"]) == 12
    assert len(manifest["fixture_checks"]["hidden"]) == 12
    assert all(
        fixture["passed"]
        for group in manifest["fixture_checks"].values()
        for fixture in group
    )

    registry = ModelRegistry(
        tmp_path,
        expected_mode="curated_demo",
        required_release_status="demo_approved",
    )
    with dataset.open(encoding="utf-8-sig", newline="") as stream:
        visible = [row for row in csv.DictReader(stream) if row["split"] == "visible"]

    classification_times: list[float] = []
    explanation_times: list[float] = []
    for row in visible:
        started = time.perf_counter()
        prediction = registry.predict(row["question"], row["locale"])
        classification_times.append(time.perf_counter() - started)
        assert prediction["status"] == "matched"
        assert prediction["category"] == row["category"]

        started = time.perf_counter()
        first = explain_prediction(row["question"], registry)
        explanation_times.append(time.perf_counter() - started)
        second = explain_prediction(row["question"], registry)
        assert first == second
        assert first.predicted_class == first.explained_class == row["category"]
        assert len(first.features) <= EXPLANATION_FEATURES

    assert float(np.percentile(classification_times, 95)) < 2
    assert float(np.percentile(explanation_times, 95)) < 12
