# Contributing to yads-infra

First off, thank you for considering contributing to `yads-infra`! It's people like you that make open source such a great community.

## How can I contribute?

### Reporting Bugs
- Make sure you are on the latest version.
- Use the GitHub Issues tab to search if the bug has already been reported.
- If not, open a new issue. Include a clear description of the problem, steps to reproduce it, and any relevant logs (`docker compose logs`).

### Suggesting Enhancements
- Open a new issue with the label `enhancement`.
- Describe the current behavior and the new behavior you want to see.
- Explain why this enhancement would be useful to most users.

### Submitting Pull Requests
1. Fork the repo and create your branch from `main`.
2. If you're changing a compose stack, test that `docker compose -f <file> config` still validates and that the stack actually comes up.
3. Update the README if you add/remove a compose file, service, or top-level directory.
4. Ensure your code follows the existing style and conventions.
5. Issue the pull request!

## Development Setup
This repo has no application code of its own — it's Docker Compose stacks (`docker-compose*.yml`), Keycloak realm examples (`keycloak/`), and a monitoring stack (`monitoring/`) for [`yads`](https://github.com/MrMarco74/yads). Compose files build the API/worker images from a sibling `../yads` (and `../yads-shadowtwin`) checkout, so clone those next to this repo to actually run anything.

### Running locally
```bash
cp .env.example .env
# fill in secrets
docker compose -f docker-compose.yml up -d
```
Use `docker-compose.server.yml` for a single-VM production-style deploy, or `docker-compose.test.yml` / `docker-compose.testlab.yml` for the test environment / intentionally-vulnerable target stack.

### Running the test suite
There's no test suite in this repo itself — `yads`'s own `make test` targets (in the core repo) drive `docker-compose.test.yml` here as their runtime. See `yads/CONTRIBUTING.md` for that.

## Project Philosophy

This codebase is built agentically (with Claude Code) and run as a hobby
project in the maintainer's spare time — there's no roadmap, SLA, or
guarantee that a given issue or pull request gets reviewed. Contributions
and reports are genuinely welcome, but they get acted on when they
happen to interest the maintainer, not on any particular schedule.
