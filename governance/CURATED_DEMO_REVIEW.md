# Curated demo bilingual review

Decision: **changes required — exact-text review pending**

Reviewer: **Karlo Nahro**  
Role: **Native Sorani speaker and professional translator**

## Review scope

The review covers the exact files below. Approval applies only when their SHA-256 values match the machine-readable manifest.

| Item | File | SHA-256 | Required decision |
| --- | --- | --- | --- |
| 144 fixed questions | `resources/demo_samples.csv` | `17a50cafd40f1ba960495542f288e136819f66f2b83f9bd1dbb2afea32bfbba7` | Review all 72 Sorani rows and confirm the category, terminology, meaning and non-clinical boundary. |
| English/Sorani interface | `app/resources/js/interface-catalog.json` | `91a9887fbd6e173475aa4fddf1d8741521718afc606ee744c6ada3142dc63224` | Review every Sorani interface string and limitation notice. |

## Decision rules

- Each Sorani row must be corrected if needed, then changed from `draft_machine_assisted` to `human_reviewed` and from `pending_review` to `approved`.
- Each English row must be changed from `pending_review` to `approved` after content review.
- The visible and hidden fixture families must remain separate from the ten training families.
- Questions must remain fixed, educational and non-urgent. Any symptom, triage, treatment or outcome-prediction wording must be rejected.
- RTL layout must be inspected in the running application after the content review.
- The final approval records new checksums in `governance/demo_review_manifest.json`; editing either approved file invalidates that approval.

## Terminology review guide

The CSV is the exact-text worksheet: `question`, `source_id`, `category`, `translation_status` and `review_status` are the proposed review fields for every row. Use the following category-level terminology guide while checking each sentence.

| Category | English concept | Draft Sorani label to confirm or correct | Registered source scope |
| --- | --- | --- | --- |
| `antenatal-appointments` | Antenatal appointments | چاوپێکەوتنەکانی پێش لەدایکبوون | NHS antenatal care and appointments |
| `birth-place-choices` | Birth-place choices | هەڵبژاردەکانی شوێنی لەدایکبوون | NHS where-to-give-birth options |
| `labour-preparation` | Labour preparation | ئامادەکاری بۆ ژان | NHS stages of labour and birth |
| `pain-relief-information` | Pain-relief information | زانیاری کەمکردنەوەی ئازار | NHS pain relief in labour |
| `after-birth-postnatal` | After birth and postnatal | دوای لەدایکبوون | NHS after-birth and postnatal-check information |
| `feeding-support` | Feeding support | پشتگیری شیرپێدان | NHS breastfeeding and bottle-feeding support |

The proposed decision for every row is currently `pending_review`; the manifest decision is `changes_required`. Approval must be applied to exact corrected text, not to this category guide in isolation.

The independent clinical-safety gate remains pending and this curated-language review cannot approve unrestricted free-text routing.
