# Model card

## Released curated demonstration

The demo image trains a TF-IDF word/character logistic-regression router from 120 checksum-approved training examples. It is released only for the 12 visible and 12 hidden fixed fixtures. Passing those fixtures demonstrates the request path and artifact controls; it is not an estimate of performance on new questions.

The demo manifest is bound to the reviewed dataset and interface checksums, declares `intended_mode=curated_demo` and `release_status=demo_approved`, and is isolated from the production registry.

## Production candidates

1. TF-IDF word/character features with logistic regression for an interpretable baseline and responsive LIME explanations.
2. `FacebookAI/xlm-roberta-base` for a multilingual deep-learning comparison, pinned to an immutable upstream revision before download or training.

Neither production candidate has been trained because the full governed production dataset contains zero eligible rows. No production accuracy, F1 or calibration claim is currently permitted. Local latency measurements and fixed-fixture outcomes apply only to the curated demonstration.

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
