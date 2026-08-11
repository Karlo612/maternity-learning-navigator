# Resource acquisition report

Verified on 11 August 2026. This document records development resources and unresolved external gates; it is not a claim of NHS, MHRA, DCB0129 or clinical compliance.

## Acquired locally

The local container runtime is Docker CLI 29.7.2 with Docker Engine 29.5.2, Colima 0.10.3 and standalone Docker Compose 5.4.0. The following official container images are present locally and are identified by immutable registry digest:

| Image | Digest |
| --- | --- |
| `php:8.4-fpm-alpine` | `sha256:5992f8b7433fe7fa96dfbf67746c86d6c41bc91e686eac38fe531c72a02e40e4` |
| `mysql:8.4` | `sha256:b3b90af2a6552ae30c266fdb7d5dd55f3afb72404bb78d37fe8a23eb857fd3fb` |
| `python:3.12-slim` | `sha256:229a2c5bfa27522db7815ea81f9bed70af17ccb9de9fc7ad142b1877b5830d36` |
| `node:24-alpine` | `sha256:d32cdf619f63fe0471182d08996dd516c6275bb5fd31ae06e55a570bd9e1ad43` |
| `nginx:1.28-alpine` | `sha256:a8b39bd9cf0f83869a2162827a0caf6137ddf759d50a171451b335cecc87d236` |
| `composer:2` | `sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040` |

Application packages are intentionally not installed yet. They will be pinned in lockfiles when the application skeleton is created; installing floating versions now would not create a reproducible resource pack.

## Acquired and registered source material

`source_registry.csv` contains the authoritative links and licence/use decisions. `question_candidates.csv` contains only provenance-bearing candidate questions. No row is training-eligible yet. The Sorani rows were extracted from published Sorani resources but remain excluded pending review by a competent Sorani speaker.

The public source registry contains only the authoritative health, governance, accessibility and multilingual links used by the demonstration.

## Model decision

The initial release will compare a transparent TF-IDF/logistic-regression baseline with an XLM-R candidate for question routing. XLM-R has published evidence of being used on Sorani text, but that evidence concerns dialect identification and cannot be treated as evidence of maternity-domain accuracy. Model weights are not yet downloaded because there is no approved labelled dataset on which to train or evaluate them.

The initial product will not use an LLM to compose healthcare answers. It will return reviewed resource links and explanations of routing features. This directly reduces hallucination risk while still demonstrating NLP, API deployment and LIME.

## External gates that cannot be self-certified

- A competent Sorani speaker must review meaning, terminology, right-to-left presentation and safety messaging before Sorani examples enter training, evaluation or the public interface.
- A qualified clinical-safety reviewer must review the hazard log, escalation wording, boundaries and representative test cases before a healthcare-facing release.
- Cloud hosting, a domain and production secrets require an account owner and billing decision. No cloud service has been opened or charged.
- Accessibility conformance requires manual keyboard, screen-reader, zoom, contrast and bidirectional-text testing in addition to automated checks.

Until those gates are satisfied, the repository is a portfolio prototype and must not describe itself as clinically validated, certified, compliant, approved or safe for maternity-care decisions.
