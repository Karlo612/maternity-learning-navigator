# Data flow and retention

1. The browser sends a question and `en` or `ckb` locale over HTTPS.
2. Laravel validates the request and checks language/clinical release gates.
3. When locked, Laravel returns an explicit `unsupported` response and does not call a model.
4. When approved, Laravel sends the question over private service networking to FastAPI.
5. FastAPI returns a category, routing confidence and model ID; it has no database and access logging is disabled.
6. Laravel resolves registered source metadata from MySQL.
7. MySQL stores only request ID, locale, outcome, category, model version, confidence, latency and optional fixed-choice feedback. A keyed fingerprint allows a later explanation request to prove it contains the same question without retaining the text.
8. An on-demand LIME response returns up to eight token weights to the browser and is discarded.

The PWA cache includes the application shell and source-directory metadata only. Classification requests, responses, user questions and copied clinical content are excluded.
