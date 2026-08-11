# Source, clinical-safety and language policy

## Intended use

The prototype routes general maternity education questions to authoritative public resources. It must not diagnose, assess symptoms, recommend treatment, predict outcomes, replace a clinician, or imply NHS endorsement.

## Source hierarchy

1. **NHS primary guidance with confirmed reuse terms** may support attributed question authoring and retrieval. Reproduced, unchanged NHS website text requires the attribution and refresh controls in the NHS website terms (including the stated seven-day maximum refresh interval, unless a different interval applies). Adapted content must use the OGL attribution rather than imply that the adaptation is NHS-authored. Translation is an adaptation under those terms.
2. **NHS pages excluded from standard reuse terms**, NHS Trust materials, and third-party translated leaflets are link-only unless their individual licence explicitly permits reuse.
3. Blogs, forums, social media, commercial pregnancy sites and unattributed AI-generated text are not approved factual sources.

Logos, design styles, excluded NHS sites, third-party material, medical devices, and excluded images or videos are not reusable under the standard NHS website terms. The application must not imply NHS approval or endorsement. The source registry records a page as `OGL_subject_to_terms` only where its URL is outside the currently listed excluded sites; this is a project governance decision, not legal advice.

## Question provenance

Every candidate question must record:

- a stable question identifier;
- language and category;
- at least one registered source identifier;
- authoring method;
- translation and human-review status;
- training eligibility;
- safety classification.

Questions may be faithful, non-clinical paraphrases of an approved source heading or user intent. The factual answer must never be invented. Retrieval output must point to the registered source and must not state more than the source supports.

Generated paraphrases are labelled `synthetic_paraphrase`, reviewed against the source, and kept out of evaluation until approved. Train, validation and test partitions must be grouped by source intent so paraphrases cannot leak between partitions.

## Kurdish Sorani

- Sorani text is eligible only when it comes directly from a traceable published Sorani resource or has been reviewed by a competent human Sorani speaker against the English source.
- Machine- or model-generated Sorani is labelled `unreviewed` and is excluded from training, evaluation and the public application.
- The product must not describe a translation as clinically validated merely because it appears fluent.
- Right-to-left layout, font rendering, language identification and meaning preservation require explicit tests.

## Safety behaviour

- Free text is never retained. Store only a random request ID, selected category, model version, confidence band, latency and fixed-choice feedback. LIME tokens and weights are returned transiently and must not enter databases, logs, traces or analytics.
- Possible urgent or clinical requests bypass the information classifier. The application states that it cannot assess urgency and directs the user to their midwife or maternity unit, NHS 111, or 999 for an emergency.
- The public prototype uses no real patient records or special-category personal data.
- Low-confidence, unsupported and out-of-scope questions return a safe refusal with authoritative links rather than a generated answer.
- The first public release is retrieval-only: it may classify a question and show reviewed resource cards, but it must not generate, summarise or translate clinical guidance. Any future generative feature requires a separately approved evidence corpus, grounded citations, hallucination testing and clinical-safety review.
- LIME explains the routing model only. It must not be presented as evidence that a response is clinically correct or as a causal explanation.

## Required governance artefacts

Before public deployment, the repository must contain an intended-use statement, dataset card, model card, data-flow diagram, hazard log, privacy notice, accessibility assessment, source-refresh report, multilingual evaluation and documented human-review sign-off.

## Release gate

No question or response is public or training-eligible until automated schema checks pass and `review_status=approved`. Sorani rows additionally require `translation_status=human_reviewed`.
