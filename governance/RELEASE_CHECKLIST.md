# Production free-text release checklist

These gates apply to unrestricted user-entered maternity questions and the separately registered production model. They do not describe or unlock the fixed-sample curated portfolio demonstration.

- [ ] Governance validator passes
- [ ] 600 bilingual examples approved with leakage-safe family identifiers
- [ ] Sorani review evidence checksum recorded
- [ ] Clinical-safety review evidence checksum recorded
- [ ] Source licences and verification dates reviewed
- [ ] Baseline and pinned XLM-R evaluation completed
- [ ] Metric and latency thresholds pass
- [ ] Safety and out-of-scope fixtures pass
- [ ] Model/dataset cards updated from real results
- [ ] No secrets, patient data, raw questions or reviewer personal data in repository
- [ ] PHP, Python, GraphQL, PWA and end-to-end tests pass
- [ ] Manual accessibility assessment completed
- [ ] Deployment smoke test and rollback tested
- [ ] Independent status and non-affiliation statements visible

Until every applicable item passes, keep `FREE_TEXT_ENABLED=false` and the production model registry unserved. The checksum-approved fixed-sample demonstration may be public and indexable because it accepts sample identifiers only and cannot bypass these gates.
