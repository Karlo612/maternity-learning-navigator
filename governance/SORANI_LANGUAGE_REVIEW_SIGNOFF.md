# Sorani-language review record

- Gate: `sorani_language`
- Reviewer: Karlo Nahro
- Reviewer role and relevant competence: Native Sorani speaker and professional translator, as declared by the reviewer in the project task conversation
- Review date: 11 August 2026
- Dataset reviewed: `resources/question_candidates.csv`
- Dataset SHA-256: `a341d09cd5ceec991f4662b9604bd7cbbabc1102f6daee00a14178b9f58cfdae`
- Interface/build reference: the fixed curated-demo catalogue was later approved separately in `governance/demo_review_manifest.json`; the production safety interface remains incomplete
- Decision: `changes_required`
- Evidence checksum: recorded in `SORANI_LANGUAGE_REVIEW_SIGNOFF.sha256`
- Approval reference: reviewer statements in the project task conversation; an independently verifiable external reference may be added for external audit

## Approved decision within the reviewed scope

The reviewer approved excluding KU-001–KU-003 from the six-category router dataset while retaining their provenance audit records. These strings remain ineligible for training and public demonstration.

## Material not yet available for approval

The following material required for unrestricted production routing does not yet exist and therefore was not approved:

- 300 Sorani topic-routing examples across the six categories
- 30 Sorani out-of-scope fixtures
- 30 Sorani safety-bypass fixtures
- Independently reviewed Sorani safety and production limitation messages
- A production-dataset terminology record

The separate curated-demo approval covers the exact 72 fixed Sorani questions, category/interface catalogue and RTL presentation identified by checksums in `governance/demo_review_manifest.json`. It does not expand this production gate or approve arbitrary Sorani input.

## Conditions for an approved language gate

Once the missing production material exists, it must be presented with an immutable dataset checksum and interface commit. The reviewer must record corrections, limitations and a new explicit decision. Neither this record nor the curated-demo approval may be reinterpreted as approval of later or unseen Sorani material.

The application remains fail-closed for Sorani free-text routing.
