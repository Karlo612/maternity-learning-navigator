import importlib
import hashlib
import json

from fastapi.testclient import TestClient
import joblib
import pytest
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import Pipeline

from app.explanations import EXPLANATION_FEATURES, EXPLANATION_SEED, explain_prediction
from app.registry import ArtifactError, ModelRegistry


def test_health_reports_review_gate_when_artifacts_are_missing(monkeypatch, tmp_path) -> None:
    monkeypatch.setenv("MODEL_ARTIFACT_DIR", str(tmp_path))
    monkeypatch.setenv("ML_SERVICE_TOKEN", "test-token")
    import app.main
    module = importlib.reload(app.main)
    client = TestClient(module.app)
    response = client.get("/v1/health", headers={"Authorization": "Bearer test-token"})
    assert response.status_code == 200
    assert response.json()["status"] == "review_gate_locked"


def test_service_requires_private_token(monkeypatch, tmp_path) -> None:
    monkeypatch.setenv("MODEL_ARTIFACT_DIR", str(tmp_path))
    monkeypatch.setenv("ML_SERVICE_TOKEN", "test-token")
    import app.main
    module = importlib.reload(app.main)
    response = TestClient(module.app).get("/v1/health")
    assert response.status_code == 401


def test_approved_bundle_applies_safety_and_confidence_layers(tmp_path) -> None:
    router = Pipeline([
        ("features", TfidfVectorizer()),
        ("classifier", LogisticRegression(random_state=41)),
    ])
    router.fit(
        ["antenatal appointment care", "antenatal check visit", "feeding support milk", "breastfeeding support help"],
        ["antenatal-appointments", "antenatal-appointments", "feeding-support", "feeding-support"],
    )
    artifact = tmp_path / "baseline.joblib"
    joblib.dump({
        "schema_version": 1,
        "router": router,
        "safety_phrases": {"en": ["approved safety fixture"], "ckb": ["ckb-reviewed-fixture-token"]},
    }, artifact)
    manifest = {
        "schema_version": 1,
        "model_id": "baseline-tfidf-logreg",
        "artifact": artifact.name,
        "sha256": hashlib.sha256(artifact.read_bytes()).hexdigest(),
        "release_status": "approved",
        "confidence_thresholds": {
            "en": {"high": .60, "medium": .50, "unsupported": .25},
            "ckb": {"high": .60, "medium": .50, "unsupported": .25},
        },
    }
    (tmp_path / "manifest.json").write_text(json.dumps(manifest), encoding="utf-8")

    registry = ModelRegistry(tmp_path)

    assert registry.ready is True
    assert registry.predict("approved safety fixture", "en")["status"] == "safety_bypass"
    assert registry.predict("antenatal appointment care", "en")["status"] == "matched"


def test_lime_explains_predicted_class_reproducibly_with_source_spans(tmp_path) -> None:
    router = Pipeline([
        ("features", TfidfVectorizer()),
        ("classifier", LogisticRegression(random_state=EXPLANATION_SEED)),
    ])
    router.fit(
        [
            "antenatal appointment care",
            "antenatal check visit",
            "feeding support milk",
            "breastfeeding support help",
            "birth place options",
            "choosing where to give birth",
        ],
        [
            "antenatal-appointments",
            "antenatal-appointments",
            "feeding-support",
            "feeding-support",
            "birth-place-choices",
            "birth-place-choices",
        ],
    )
    artifact = tmp_path / "baseline.joblib"
    joblib.dump({
        "schema_version": 1,
        "router": router,
        "safety_phrases": {"en": ["approved safety fixture"], "ckb": ["ckb-reviewed-fixture-token"]},
    }, artifact)
    (tmp_path / "manifest.json").write_text(json.dumps({
        "schema_version": 1,
        "model_id": "baseline-tfidf-logreg",
        "artifact": artifact.name,
        "sha256": hashlib.sha256(artifact.read_bytes()).hexdigest(),
        "release_status": "approved",
        "confidence_thresholds": {
            "en": {"high": .60, "medium": .50, "unsupported": .25},
            "ckb": {"high": .60, "medium": .50, "unsupported": .25},
        },
    }), encoding="utf-8")
    registry = ModelRegistry(tmp_path)
    question = "Where can I find breastfeeding support?"

    first = explain_prediction(question, registry)
    second = explain_prediction(question, registry)

    assert first == second
    assert first.predicted_class == "feeding-support"
    assert first.explained_class == first.predicted_class
    assert 0 < len(first.features) <= EXPLANATION_FEATURES
    assert all(feature.direction in {"supporting", "opposing"} for feature in first.features)
    assert any(feature.occurrences for feature in first.features)
    for feature in first.features:
        for occurrence in feature.occurrences:
            assert question[occurrence["start"]:occurrence["end"]].casefold() == feature.token.casefold()


def test_demo_and_production_artifacts_cannot_cross_registries(tmp_path) -> None:
    router = Pipeline([
        ("features", TfidfVectorizer()),
        ("classifier", LogisticRegression(random_state=41)),
    ])
    router.fit(
        ["antenatal appointment", "antenatal visit", "feeding support", "feeding help"],
        ["antenatal-appointments", "antenatal-appointments", "feeding-support", "feeding-support"],
    )
    artifact = tmp_path / "demo.joblib"
    joblib.dump({
        "schema_version": 1,
        "router": router,
        "safety_phrases": {"en": ["not-used-in-fixed-demo"], "ckb": ["not-used-in-fixed-demo-ckb"]},
    }, artifact)
    (tmp_path / "manifest.json").write_text(json.dumps({
        "schema_version": 1,
        "model_id": "demo-tfidf-logreg",
        "intended_mode": "curated_demo",
        "artifact": artifact.name,
        "sha256": hashlib.sha256(artifact.read_bytes()).hexdigest(),
        "release_status": "demo_approved",
        "confidence_thresholds": {
            "en": {"high": .60, "medium": .40, "unsupported": .10},
            "ckb": {"high": .60, "medium": .40, "unsupported": .10},
        },
    }), encoding="utf-8")

    assert ModelRegistry(
        tmp_path,
        expected_mode="curated_demo",
        required_release_status="demo_approved",
    ).ready is True
    with pytest.raises(ArtifactError, match="curated_demo"):
        ModelRegistry(tmp_path)


def test_sorani_explanation_features_follow_original_text_order(tmp_path) -> None:
    router = Pipeline([
        ("features", TfidfVectorizer(analyzer="char_wb", ngram_range=(3, 5))),
        ("classifier", LogisticRegression(random_state=41)),
    ])
    router.fit(
        [
            "چاوپێکەوتنی دووگیانی پێش لەدایکبوون",
            "چاودێری پێش لەدایکبوون و چاوپێکەوتن",
            "پشتگیری شیرپێدان بە منداڵ",
            "یارمەتی بۆ شیرپێدان",
        ],
        ["antenatal-appointments", "antenatal-appointments", "feeding-support", "feeding-support"],
    )
    artifact = tmp_path / "demo.joblib"
    joblib.dump({
        "schema_version": 1,
        "router": router,
        "safety_phrases": {"en": ["fixed-demo"], "ckb": ["fixed-demo-ckb"]},
    }, artifact)
    (tmp_path / "manifest.json").write_text(json.dumps({
        "schema_version": 1,
        "model_id": "demo-tfidf-logreg",
        "intended_mode": "curated_demo",
        "artifact": artifact.name,
        "sha256": hashlib.sha256(artifact.read_bytes()).hexdigest(),
        "release_status": "demo_approved",
        "confidence_thresholds": {
            "en": {"high": .60, "medium": .20, "unsupported": .10},
            "ckb": {"high": .60, "medium": .20, "unsupported": .10},
        },
    }), encoding="utf-8")
    registry = ModelRegistry(tmp_path, expected_mode="curated_demo", required_release_status="demo_approved")
    explanation = explain_prediction("پشتگیری شیرپێدان بە منداڵ", registry)
    starts = [feature.occurrences[0]["start"] for feature in explanation.features if feature.occurrences]

    assert starts == sorted(starts)
