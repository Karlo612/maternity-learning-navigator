from __future__ import annotations

import os
from pathlib import Path

from fastapi import Depends, FastAPI, Header, HTTPException, status
from .explanations import EXPLANATION_SAMPLES, EXPLANATION_SEED, explain_prediction
from .registry import ArtifactError, ModelRegistry
from .schemas import (
    ClassificationRequest,
    ClassificationResponse,
    DemoClassificationResponse,
    ExplanationRequest,
    ExplanationResponse,
    FeatureWeight,
)


ARTIFACT_DIR = Path(os.environ.get("MODEL_ARTIFACT_DIR", "/models"))
DEMO_ARTIFACT_DIR = Path(os.environ.get("DEMO_MODEL_ARTIFACT_DIR", "/demo-model"))
SERVICE_TOKEN = os.environ.get("ML_SERVICE_TOKEN", "")
registry = ModelRegistry(ARTIFACT_DIR)
demo_registry = ModelRegistry(
    DEMO_ARTIFACT_DIR,
    expected_mode="curated_demo",
    required_release_status="demo_approved",
)
app = FastAPI(
    title="Maternity Learning Navigator model service",
    version="1.0.0-review-gated",
    docs_url=None,
    redoc_url=None,
)


def require_token(authorization: str | None = Header(default=None)) -> None:
    if not SERVICE_TOKEN or authorization != f"Bearer {SERVICE_TOKEN}":
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid service token")


@app.get("/v1/health")
def health(_: None = Depends(require_token)) -> dict:
    return {
        "status": "ready" if registry.ready else "review_gate_locked",
        "model_id": registry.model_id,
        "model_version": registry.model_version,
        "detail": None if registry.ready else registry.error,
        "demo": {
            "status": "ready" if demo_registry.ready else "unavailable",
            "model_id": demo_registry.model_id,
            "model_version": demo_registry.model_version,
            "detail": None if demo_registry.ready else demo_registry.error,
        },
    }


@app.post("/v1/classify", response_model=ClassificationResponse)
def classify(payload: ClassificationRequest, _: None = Depends(require_token)) -> ClassificationResponse:
    try:
        prediction = registry.predict(payload.question, payload.locale)
    except ArtifactError as error:
        raise HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail=str(error)) from error
    return ClassificationResponse(
        status=prediction["status"],
        category=prediction["category"],
        confidence=prediction["confidence"],
        confidence_band=prediction["confidence_band"],
        model_id=registry.model_id,
        model_version=registry.model_version,
    )


@app.post("/v1/explain", response_model=ExplanationResponse)
def explain(payload: ExplanationRequest, _: None = Depends(require_token)) -> ExplanationResponse:
    if not registry.ready or registry.router is None:
        raise HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail=registry.error)
    if payload.model_id != registry.model_id:
        raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail="Requested model is not the serving model")
    if payload.model_version != registry.model_version:
        raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail="Requested model version is not serving")
    local = explain_prediction(payload.question, registry)
    features = [FeatureWeight(**feature.__dict__) for feature in local.features]
    return ExplanationResponse(
        model_id=registry.model_id,
        model_version=registry.model_version,
        predicted_class=local.predicted_class,
        explained_class=local.explained_class,
        probability=local.probability,
        random_seed=EXPLANATION_SEED,
        num_samples=EXPLANATION_SAMPLES,
        features=features,
    )


@app.post("/v1/demo/classify", response_model=DemoClassificationResponse)
def classify_demo(
    payload: ClassificationRequest,
    _: None = Depends(require_token),
) -> DemoClassificationResponse:
    try:
        prediction = demo_registry.predict(payload.question, payload.locale)
    except ArtifactError as error:
        raise HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail=str(error)) from error
    return DemoClassificationResponse(
        status=prediction["status"],
        category=prediction["category"],
        confidence=prediction["confidence"],
        confidence_band=prediction["confidence_band"],
        model_id=demo_registry.model_id,
        model_version=demo_registry.model_version,
    )


@app.post("/v1/demo/explain", response_model=ExplanationResponse)
def explain_demo(
    payload: ExplanationRequest,
    _: None = Depends(require_token),
) -> ExplanationResponse:
    if not demo_registry.ready or demo_registry.router is None:
        raise HTTPException(status_code=status.HTTP_503_SERVICE_UNAVAILABLE, detail=demo_registry.error)
    if payload.model_id != demo_registry.model_id or payload.model_version != demo_registry.model_version:
        raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail="Requested demo model is not serving")
    local = explain_prediction(payload.question, demo_registry)
    features = [FeatureWeight(**feature.__dict__) for feature in local.features]
    return ExplanationResponse(
        model_id=demo_registry.model_id,
        model_version=demo_registry.model_version,
        predicted_class=local.predicted_class,
        explained_class=local.explained_class,
        probability=local.probability,
        random_seed=EXPLANATION_SEED,
        num_samples=EXPLANATION_SAMPLES,
        demo_only=True,
        features=features,
    )
