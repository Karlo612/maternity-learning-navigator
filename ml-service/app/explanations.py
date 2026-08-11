from __future__ import annotations

import re
from dataclasses import dataclass

import numpy as np
from lime.lime_text import LimeTextExplainer

from .registry import ModelRegistry


EXPLANATION_SEED = 41
EXPLANATION_SAMPLES = 1_000
EXPLANATION_FEATURES = 8


@dataclass(frozen=True)
class ExplainedFeature:
    token: str
    weight: float
    direction: str
    occurrences: list[dict[str, int]]


@dataclass(frozen=True)
class LocalExplanation:
    predicted_class: str
    explained_class: str
    probability: float
    features: list[ExplainedFeature]


def _occurrences(text: str, token: str) -> list[dict[str, int]]:
    """Return case-insensitive spans without changing the source-text order."""
    if not token:
        return []
    return [
        {"start": match.start(), "end": match.end()}
        for match in re.finditer(re.escape(token), text, flags=re.IGNORECASE)
    ]


def explain_prediction(question: str, registry: ModelRegistry) -> LocalExplanation:
    probabilities = registry.predict_proba([question])[0]
    predicted_index = int(np.argmax(probabilities))
    predicted_class = str(registry.router.classes_[predicted_index])  # type: ignore[union-attr]
    explainer = LimeTextExplainer(
        class_names=registry.class_names,
        random_state=EXPLANATION_SEED,
    )
    explanation = explainer.explain_instance(
        question,
        registry.predict_proba,
        labels=(predicted_index,),
        num_features=EXPLANATION_FEATURES,
        num_samples=EXPLANATION_SAMPLES,
    )
    pairs = explanation.as_list(label=predicted_index)
    features = [
        ExplainedFeature(
            token=token,
            weight=float(weight),
            direction="supporting" if weight >= 0 else "opposing",
            occurrences=_occurrences(question, token),
        )
        for token, weight in pairs
    ]
    features.sort(key=lambda feature: (
        feature.occurrences[0]["start"] if feature.occurrences else len(question),
        feature.occurrences[0]["end"] if feature.occurrences else len(question),
    ))
    explained_class = str(registry.router.classes_[predicted_index])  # type: ignore[union-attr]
    if explained_class != predicted_class:
        raise RuntimeError("LIME explained class does not match the predicted class.")
    return LocalExplanation(
        predicted_class=predicted_class,
        explained_class=explained_class,
        probability=float(probabilities[predicted_index]),
        features=features,
    )
