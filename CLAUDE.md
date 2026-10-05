# Receita Leilão

Laravel 13 / PHP 8.5 app that imports auction notices ("editais") and lots from the Receita Federal SLE portal into MySQL. See README.md (Portuguese) for usage.

## Environment

PHP and Composer are not installed on the host; everything runs in Docker. Do not install PHP locally.

- Start: `docker compose up -d --build` (app on http://localhost:8080)
- Artisan/Composer: `docker compose exec app php artisan ...`, `docker compose exec app composer ...`
- Tests: `docker compose exec app php artisan test` (SQLite in memory, HTTP faked with fixtures in `tests/Fixtures/sle`)
- Format: `docker compose exec app ./vendor/bin/pint`
- After changing code used by jobs: `docker compose restart queue`

## Code map

- `app/Services/Sle/SleClient.php`: client for the portal's public JSON API (`/sle-sociedade/api/...`), with throttling and retries.
- `app/Services/Sle/EditalImporter.php`: maps API payloads to `Edital`, `Lote`, `LoteItem`, `LoteImagem` (idempotent upserts).
- `app/Services/Sle/EditalRef.php`: parses portal URLs / `unidade/numero/ano` identifiers.
- `app/Console/Commands`: `leilao:importar` and `leilao:sincronizar`.
- `app/Jobs/ImportarEdital.php`: queued import used by the web form and `--fila`.
- Domain names are in Portuguese, matching the portal's vocabulary.

## API notes

- The API returns HTTP 500 with `{"code":"ER9999"}` both for failures and for editais/lots that don't exist.
- Money values are in reais (not cents). Dates are `Y-m-d H:i` in America/Sao_Paulo (the app timezone).
- In `api/edital/...`, the `cidade` field holds the executing unit's name; the real city only appears in `api/editais-disponiveis`.
