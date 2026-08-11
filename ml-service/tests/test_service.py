import importlib
import hashlib
import json

from fastapi.testclient import TestClient
import joblib
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import Pipeline

from app.registry import ModelRegistry


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
