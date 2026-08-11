# Proportionate hazard log

This is an awareness artefact for a portfolio prototype, not a DCB0129 safety case or certification.

| Hazard | Possible harm | Control | Verification | State |
| --- | --- | --- | --- | --- |
| Routing output mistaken for medical advice | Delayed or inappropriate care-seeking | No generated answer; repeated intended-use statement; model confidence labelled as routing confidence | Content and usability review | Open — clinical review required |
| Urgent wording routed as education | User relies on a resource directory | Approved deterministic safety fixtures bypass the model; generic inability-to-assess message | 100% pass on approved fixtures | Open — fixtures not approved |
| Incorrect Sorani meaning | Misunderstanding or inequitable access | Qualified human review; RTL and bidirectional tests; no machine-generated public translation | Signed language review | Open |
| Low-confidence model presented as certain | Over-trust | Language-specific thresholds and safe refusal | Calibration and OOD evaluation | Open — model not trained |
| Sensitive text retained | Privacy impact | No raw-question or LIME-token fields; request bodies excluded from logs | Schema, API and log tests | Implemented locally |
| Stale or unlicensed source content | Incorrect attribution or outdated information | Link-first source registry, reuse status and weekly verification report | Automated link/metadata check plus manual review | Partially implemented |
| NHS or Real Birth endorsement implied | Misrepresentation | Independent identity, no logos, explicit non-affiliation notice | Release content review | Implemented locally |
