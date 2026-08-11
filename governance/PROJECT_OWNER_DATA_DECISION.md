# Project-owner bilingual data decision

- Project: Maternity Learning Navigator
- Project owner: Karlo Nahro
- Decision date: 11 August 2026
- Dataset reviewed: `resources/question_candidates.csv`
- Dataset SHA-256: `a341d09cd5ceec991f4662b9604bd7cbbabc1102f6daee00a14178b9f58cfdae`
- Decision: Agreed to decisions 1–4 in `BILINGUAL_DATA_REVIEW_PACKET.md`

## Approved governance actions

1. Retain EN-001–EN-005 and EN-008–EN-012 as candidates pending independent content review.
2. Refer EN-006 and EN-007 to a qualified clinical-safety reviewer before any training or public routing use.
3. Exclude KU-001–KU-003 from the router dataset while retaining their provenance audit records.
4. Develop replacement Sorani candidates within the six approved routing categories and submit every string for competent Sorani review.

## Limits of this decision

This is a project-owner decision. It does not make any row training-eligible and did not itself satisfy either production release gate. The project owner subsequently declared competence as a native Sorani speaker and professional translator and separately approved the fixed curated-demo corpus. The production language record remains `changes_required` because the full 300-row Sorani production corpus, evaluation fixtures and reviewed safety materials do not yet exist. No clinical-safety competence has been asserted. Unrestricted routing must continue to fail closed until the complete production reviews, dataset and model-performance thresholds pass.

Approval was supplied by the project owner in the project task conversation. An independently verifiable approval reference may be added later if required for external audit.
