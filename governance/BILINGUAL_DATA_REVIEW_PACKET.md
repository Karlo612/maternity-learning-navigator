# Bilingual data review packet

Prepared on 11 August 2026 for project-owner review and referral to qualified reviewers. This packet is an auditable decision aid, not a clinical or language sign-off.

## Decision summary

The bilingual dataset is **not approved** and is not sufficient for training.

| Measure | Required release set | Present | Approved / training-eligible |
| --- | ---: | ---: | ---: |
| English topic-routing questions | 300 | 12 | 0 |
| Sorani topic-routing questions | 300 | 0 suitable rows | 0 |
| English out-of-scope fixtures | 30 | 0 | 0 |
| Sorani out-of-scope fixtures | 30 | 0 | 0 |
| English safety-bypass fixtures | 30 | 0 | 0 |
| Sorani safety-bypass fixtures | 30 | 0 | 0 |

Three Sorani strings are retained in the source-acquisition audit. They are not suitable topic-routing examples: their provisional categories are outside the six-category taxonomy, two concern alcohol exposure and one concerns reduced fetal movement. The latter is urgent material. Their source records are link-only pending licensing and their meaning has not been independently verified. The recommended project-owner decision is to exclude all three from training and public demonstration while preserving the audit trail.

Dataset file reviewed: `resources/question_candidates.csv`

Current SHA-256: `a341d09cd5ceec991f4662b9604bd7cbbabc1102f6daee00a14178b9f58cfdae`

## English candidate review

Approval here means “retain as a candidate for the governed dataset.” It does not make a row training-eligible and does not replace the clinical-safety gate.

| ID | Candidate question | Proposed category | Source | Recommended decision |
| --- | --- | --- | --- | --- |
| EN-001 | What happens at antenatal appointments? | Antenatal appointments | NHS-006 | Retain pending content review |
| EN-002 | When should antenatal care begin? | Antenatal appointments | NHS-006 | Retain pending content review |
| EN-003 | What birth-place options can I discuss with my midwife? | Birth-place choices | NHS-005 | Retain pending content review |
| EN-004 | What questions can I ask when choosing where to give birth? | Birth-place choices | NHS-005 | Retain pending content review |
| EN-005 | What are the stages of labour and birth? | Labour preparation | NHS-003 | Retain pending content review |
| EN-006 | What are the signs that labour may have begun? | Labour preparation | NHS-002 | Refer to clinical-safety reviewer |
| EN-007 | When should I contact my midwife or maternity unit about possible labour? | Labour preparation | NHS-002 | Refer to clinical-safety reviewer |
| EN-008 | What pain-relief options can I discuss for labour? | Pain-relief information | NHS-004 | Retain pending content review |
| EN-009 | Where can I get help and support with breastfeeding? | Feeding support | NHS-010 | Retain pending content review |
| EN-010 | Where can I find NHS bottle-feeding advice? | Feeding support | NHS-011 | Retain pending content review |
| EN-011 | What usually happens straight after birth? | After birth and postnatal | NHS-007 | Retain pending content review |
| EN-012 | What may happen at a 6-week postnatal check? | After birth and postnatal | NHS-009 | Retain pending content review |

## Sorani acquisition-audit review

Do not infer an English translation from these strings for model training. A competent Sorani reviewer must verify the exact meaning against the linked source.

| ID | Exact acquired text | Provisional source topic | Source | Recommended decision |
| --- | --- | --- | --- | --- |
| KU-001 | هەموو منداڵێک تووشی کۆمەڵە گرفتی مەی وەرگرتنی کۆرپە دەبێت ئەگەر دایکەکە لە کاتی دووگیانیدا مەی خواردبێتەوە؟ | Pregnancy health / alcohol exposure | [West Yorkshire Health and Care Partnership](https://www.wypartnership.co.uk/application/files/6217/6354/5011/3805_Fetal_Alcohol_Spectrum_Disorder_Leaflet_BR_Kurdish_Sorani.pdf) | Exclude: outside taxonomy, clinical, licence and language review pending |
| KU-002 | چۆن بتوانم بزانم ئایا کۆرپە/منداڵەکەم کۆمەڵە گرفتی مەی وەرگرتنی کۆرپەی هەیە؟ | Pregnancy health / alcohol exposure | [West Yorkshire Health and Care Partnership](https://www.wypartnership.co.uk/application/files/6217/6354/5011/3805_Fetal_Alcohol_Spectrum_Disorder_Leaflet_BR_Kurdish_Sorani.pdf) | Exclude: outside taxonomy, clinical, licence and language review pending |
| KU-003 | ئەی ئەگەر دیسانەوە جوڵەی کۆرپەکەم کەمی کردەوە؟ | Reduced fetal movement | [Tommy's](https://www.tommys.org/sites/default/files/2024-02/Reduced%20fetal%20movement%20leaflet%20SORANI%202024.pdf) | Exclude: outside taxonomy and urgent safety material |

## Project-owner decision requested

The project owner may record agreement or request changes for these governance decisions:

1. Retain EN-001–EN-005 and EN-008–EN-012 as English candidates pending qualified review.
2. Refer EN-006 and EN-007 to the clinical-safety reviewer before any training or public routing use.
3. Exclude KU-001–KU-003 from this router’s dataset while retaining their provenance audit records.
4. Build the missing Sorani corpus only within the same six categories, with every string reviewed by a competent Sorani reviewer.

Project-owner name: Karlo Nahro

Decision: Agreed to decisions 1–4

Requested changes: None

Decision date: 11 August 2026

Approval scope: Project-owner governance decision only. The project owner subsequently declared competence as a native Sorani speaker and professional translator; that declaration and its deliberately limited review decision are recorded separately in `SORANI_LANGUAGE_REVIEW_SIGNOFF.md`. No clinical-safety competence was asserted.

## Independent sign-offs still required

Project-owner agreement cannot release the data unless the project owner also has the relevant competence and records it transparently. The following remain separate:

- The identified Sorani reviewer must still verify every replacement Sorani question, category label, interface string, safety message, terminology choice and bidirectional presentation before changing the language decision from `changes_required` to `approved`.
- A qualified clinical-safety reviewer must review the boundary, hazard log, safety-bypass phrases, urgent-capable examples and public safety wording.
- Each sign-off must name the reviewed dataset checksum, interface/build version, decision, limitations, date and independently verifiable evidence reference using `governance/REVIEW_SIGNOFF_TEMPLATE.md`.
