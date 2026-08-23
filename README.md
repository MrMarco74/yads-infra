# yads-infra

![License](https://img.shields.io/badge/license-MIT-blue.svg) ![Language](https://img.shields.io/badge/language-Shell-informational.svg) ![AI generated](https://img.shields.io/badge/AI-generated-8A2BE2.svg)

Deployment infrastructure for [YADS](https://github.com/MrMarco74/yads) — Docker Compose stacks, Keycloak realm examples, and a monitoring stack (Prometheus/Grafana/Loki).

## Contents

- **`docker-compose.yml`** / **`docker-compose.prod.yml`** / **`docker-compose.server.yml`** — core stack (API, worker, Postgres, Redis), building from source against a sibling `../yads` (and `../yads-shadowtwin`) checkout
- **`docker-compose.prebuilt.yml`** — same core stack, but pulls prebuilt images from GHCR instead of building from source — no sibling `../yads` checkout or local build toolchain needed
- **`docker-compose.build.yml`** — thin overlay adding `build:` sections on top of the base stack
- **`docker-compose.test.yml`** / **`docker-compose.testlab.yml`** — test environment and an intentionally-vulnerable target stack used to exercise YADS's own scanners
- **`keycloak/`** — example Keycloak realm exports for OIDC auth (`realm-frischkorn.json`, `realm-yads-platform.json`) — demo users/passwords only, not for production use as-is
- **`monitoring/`** — Prometheus, Grafana (dashboards + alerting), Loki/Promtail log stack
- **`registry-php/`** — minimal read-only OCI registry frontend (auth credentials are loaded from a gitignored `auth.php`, never committed)
- **`proxy_infos.md`** — analysis/roadmap for running YADS workers behind a corporate HTTP proxy

## Usage

```bash
cp .env.example .env
# fill in secrets
docker compose up -d
```

`docker-compose.server.yml` (single-VM production deployment, no reverse-proxy included)
expects a pre-existing external `proxy-net` Docker network, so a separate reverse-proxy
stack can share it:

```bash
docker network create proxy-net
docker compose -f docker-compose.server.yml up -d
```

`docker-compose.yml` (the default quickstart file) doesn't need this — it creates its own
`proxy-net` network automatically.

To skip building from source entirely and just pull prebuilt images (published for every
[release tag](https://github.com/MrMarco74/yads/releases)):

```bash
cp .env.example .env
YADS_VERSION=v1.20.0 docker compose -f docker-compose.prebuilt.yml pull
YADS_VERSION=v1.20.0 docker compose -f docker-compose.prebuilt.yml up -d
```

## License

MIT — see [LICENSE](LICENSE).
