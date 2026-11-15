# CLAUDE.md

## Project
From-scratch rewrite of an earlier internship project (LaraForm). Social login with Laravel + Socialite, using Docker and SQLite.
This is a portfolio project: a clean commit history and tests matter.

## Commands
Laravel 12, PHP 8.3. Everything runs inside the `app` container (no local PHP needed).
- Start: `docker compose up --build` -> http://localhost:8000
- Tests: `docker compose exec app php artisan test`
- Artisan / Composer: `docker compose exec app php artisan <cmd>`, `docker compose exec app composer <cmd>`
- Stop: `docker compose down`

On start, `docker/entrypoint.sh` installs vendor (if missing), creates `.env` from `.env.example`, generates `APP_KEY`, creates `database/database.sqlite` and runs migrations.

## Folder structure
Standard Laravel 12 layout (`app/`, `routes/`, `resources/`, `database/`, `tests/`, ...), plus:
- `Dockerfile`, `docker-compose.yml` — dev container
- `docker/entrypoint.sh` — idempotent startup script

## Rules
- The data-science sections of the global CLAUDE.md (standards, project layout, README chart) do not apply here; follow Laravel conventions.
- Never commit the `.env` file or any secret keys, and never write them to logs.
