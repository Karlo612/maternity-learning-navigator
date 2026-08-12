# Deployment and offline architecture

## Evidence boundary

The deployable topology builds and passes integration, browser and accessibility checks in Docker Compose and GitHub Actions. A Railway release was verified on 12 August 2026 at [app-production-fe71.up.railway.app](https://app-production-fe71.up.railway.app). This record claims only the checks listed as passed below: custom-domain DNS, backup/restore evidence and a tested rollback target remain pending.

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
| GitHub Actions release gates | Passed: governance, MySQL/PHP, Python/LIME, secret scan, containers, REST/GraphQL smoke tests, Playwright RTL/offline and accessibility (`31549357234`) |
| Laravel deployment | Passed on Railway EU; deployment `1e1c6a0b-46d5-4f15-b796-c4cc0273891d`, commit `186c556` |
| Private FastAPI deployment | Passed; reachable from Laravel over Railway private DNS and not assigned a public domain |
| Private MySQL persistence | Passed; pinned MySQL 8.4 with a private hostname and persistent `/var/lib/mysql` volume |
| Migration and governed import | Passed; startup migration/import completed and health reported the database available with 12 visible approved samples |
| REST, GraphQL and LIME smoke tests | Passed over public HTTPS in English and Sorani; predicted and explained classes matched with seed 41 |
| English and Sorani RTL browser journeys | Passed; Sorani set `lang="ckb"` and `dir="rtl"`; offline behavior remains covered by the automated browser suite |
| Railway HTTPS domain | Passed: `app-production-fe71.up.railway.app` |
| Custom domain | Pending DNS: `maternity.karlonahro.com` is registered in Railway and awaits the `maternity` CNAME update |
| Backup/restore evidence | Pending |
| Rollback target | Pending |
