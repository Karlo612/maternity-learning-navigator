from __future__ import annotations

import os
from pathlib import Path

from fastapi import Depends, FastAPI, Header, HTTPException, status
from lime.lime_text import LimeTextExplainer

from .registry import ArtifactError, ModelRegistry
from .schemas import ClassificationRequest, ClassificationResponse, ExplanationRequest, ExplanationResponse, FeatureWeight


ARTIFACT_DIR = Path(os.environ.get("MODEL_ARTIFACT_DIR", "/models"))
SERVICE_TOKEN = os.environ.get("ML_SERVICE_TOKEN", "")
registry = ModelRegistry(ARTIFACT_DIR)
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
    explainer = LimeTextExplainer(class_names=registry.class_names, random_state=41)
    explanation = explainer.explain_instance(
        payload.question, registry.predict_proba, num_features=8, num_samples=500,
    )
    features = [FeatureWeight(token=token, weight=float(weight)) for token, weight in explanation.as_list()]
    return ExplanationResponse(model_id=registry.model_id, model_version=registry.model_version, features=features)
