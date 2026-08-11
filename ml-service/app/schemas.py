from typing import Literal

from pydantic import BaseModel, Field


Locale = Literal["en", "ckb"]


class ClassificationRequest(BaseModel):
    question: str = Field(min_length=3, max_length=500)
    locale: Locale


class ExplanationRequest(ClassificationRequest):
    model_id: str = Field(min_length=1, max_length=100)
    model_version: str = Field(min_length=1, max_length=100)


class FeatureWeight(BaseModel):
    token: str
    weight: float


class ClassificationResponse(BaseModel):
    status: Literal["matched", "low_confidence", "safety_bypass", "unsupported"]
    category: str | None = None
    confidence: float | None = None
    confidence_band: Literal["high", "medium", "low"] | None = None
    model_id: str
    model_version: str


class ExplanationResponse(BaseModel):
    model_id: str
    model_version: str
    features: list[FeatureWeight]
