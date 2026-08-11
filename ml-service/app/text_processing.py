from __future__ import annotations

import re


_PAIN_MANAGEMENT_TERMS = re.compile(r"\b(?:managed|managing|management)\b", flags=re.IGNORECASE)


def normalize_demo_text(text: str) -> str:
    """Normalise one reviewed topic synonym without changing the signed source text."""
    return _PAIN_MANAGEMENT_TERMS.sub("pain relief", text.casefold())
