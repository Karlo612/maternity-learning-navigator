# Deployment and offline architecture

## Evidence boundary

The repository currently proves that the deployable topology builds and passes integration, browser and accessibility checks in Docker Compose and GitHub Actions. Railway operation, managed persistence, backups, custom-domain routing and rollback remain pending until a live release is verified. This document must be updated with deployment identifiers and test timestamps before those capabilities are claimed as completed experience.

## Service topology

| Service | Public boundary | State and storage |
| --- | --- | --- |
| Laravel 13 / React | HTTPS web application, REST and read-only GraphQL | Stateless container; sessions, provenance and derived telemetry use MySQL |
| FastAPI / scikit-learn / LIME | Private authenticated PHP-to-model calls only | Stateless container; curated artifact is built immutably into the image |
| MySQL 8.4 | Private network only | Persistent UTF-8 storage for approved content, source provenance and derived routing events |

Only Laravel receives public traffic. FastAPI requires a bearer token and has no public route in the intended cloud topology. MySQL is not exposed publicly.

## Data boundaries

- Curated demo endpoints accept approved sample identifiers, not browser-supplied maternity text.
- MySQL stores a request UUID, governed sample/category/model references, locale, routing status, confidence band and latency.
- LIME tokens, weights and occurrence spans are returned transiently and are not persisted.
- The model container has no database and runs without access logs.
- Secrets are supplied through deployment variables and are excluded from Git and Docker build contexts.

## Offline boundary

The PWA caches the compiled application shell and reviewed source-directory metadata. It does not cache classification requests, explanations, arbitrary questions, copied clinical guidance or healthcare answers. Browser model actions are disabled when offline, and the end-to-end suite verifies that offline state cannot enable a model call.

## Railway release sequence

1. Create private MySQL and FastAPI services plus the public Laravel service.
2. attach a persistent MySQL volume and configure backup/restore policy;
3. configure service-scoped secrets and Laravel-to-FastAPI private networking;
4. build images from the pinned Dockerfiles;
5. let the application entrypoint run idempotent Laravel migrations and the governed importer before starting the web processes;
6. verify public health, REST, GraphQL and curated LIME journeys;
7. verify that FastAPI and MySQL have no public ingress;
8. verify the English/Sorani, RTL, offline and accessibility journeys against HTTPS;
9. attach `maternity.karlonahro.com`; and
10. record the deployed image SHAs, test timestamp, backup result and rollback target below.

## Live verification record

| Check | Result |
| --- | --- |
| Laravel deployment | Pending |
| Private FastAPI deployment | Pending |
| Private MySQL persistence | Pending |
| Migration and governed import | Pending |
| REST, GraphQL and LIME smoke tests | Pending |
| English, Sorani RTL and offline browser tests | Pending |
| HTTPS custom domain | Pending |
| Backup/restore evidence | Pending |
| Rollback target | Pending |
