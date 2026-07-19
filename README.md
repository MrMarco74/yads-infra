# yads-infra

Deployment infrastructure for [YADS](https://github.com/MrMarco74/yads) — Docker Compose stacks, Keycloak realm examples, and a monitoring stack (Prometheus/Grafana/Loki).

## Contents

- **`docker-compose.yml`** / **`docker-compose.prod.yml`** — core stack (API, worker, Postgres, Redis)
- **`docker-compose.swarm.yml`** — Docker Swarm deployment
- **`docker-compose.build.yml`** / **`docker-compose.release.yml`** — build/release variants
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

## License

MIT — see [LICENSE](LICENSE).
