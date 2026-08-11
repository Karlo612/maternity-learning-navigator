# Dataset card

## Intended use

The governed dataset will contain non-clinical English and Kurdish Sorani question phrasings for routing to six educational resource categories. It must not be used for diagnosis, symptom assessment, treatment, birth-outcome prediction or generation of maternity advice.

## Current state

- Registered sources: 24
- Candidate questions: 15 (12 English, 3 published-source Sorani)
- Training-eligible questions: 0
- Release state: locked pending content, Sorani-language and clinical-safety review

The target dataset is 50 approved examples per category per language. Out-of-scope and safety-bypass sets remain evaluation-only. Each example requires a source ID, paraphrase-family ID, authoring method, review status, translation status and safety class.

## Splitting and leakage controls

Train, validation and test partitions are 70/15/15 and grouped by paraphrase family. Variants of the same underlying question may not cross partitions. Metrics are reported separately by language and category.

## Limitations

Synthetic question phrasings are not evidence of real-world representativeness. No patient records or Real Birth Company historical data are included. Sorani fluency or XLM-R language coverage does not establish maternity-domain validity.
