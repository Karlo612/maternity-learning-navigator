# Model card

## Candidates

1. TF-IDF word/character features with logistic regression for an interpretable baseline and responsive LIME explanations.
2. `FacebookAI/xlm-roberta-base` for a multilingual deep-learning comparison, pinned to an immutable upstream revision before download or training.

Neither model has been trained for this project because the governed dataset contains zero eligible rows. No accuracy, F1, calibration or latency claim is currently permitted.

## Serving boundary

The model may choose one of six educational topics. It cannot write an answer, infer a condition, assess urgency, recommend an action or predict an outcome. Low-confidence and unavailable states fail closed.

LIME explains local topic-routing behavior only. Feature weights are approximate and non-causal, and they do not establish that a linked resource is clinically correct.

## Release thresholds

- Macro-F1 at least 0.80 in each language
- No category F1 below 0.70
- Reviewed out-of-scope recall at least 0.85
- Every approved safety fixture reaches the generic safety bypass
- Classification p95 below two seconds and LIME p95 below twelve seconds for single-user demo load

The serving manifest remains `evaluation_only` until independent review confirms the evidence.
