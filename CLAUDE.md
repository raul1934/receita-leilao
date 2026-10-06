# Receita Leilão

Laravel 13 / PHP 8.5 app that imports auction notices ("editais") and lots from the Receita Federal SLE portal into MySQL. See README.md (Portuguese) for usage.

## Environment

PHP and Composer are not installed on the host; everything runs in Docker. Do not install PHP locally.

- Start: `docker compose up -d --build` (app on http://localhost:8080)
- Artisan/Composer: `docker compose exec app php artisan ...`, `docker compose exec app composer ...`
- Tests: `docker compose exec app php artisan test` (SQLite in memory, HTTP faked with fixtures in `tests/Fixtures/sle`)
- Format: `docker compose exec app ./vendor/bin/pint`
- After changing code: `docker compose restart queue scheduler` (wait for running imports to finish first; restarting the worker interrupts the current job)

## Code map

- `app/Services/Sle/SleClient.php`: client for the portal's public JSON API (`/sle-sociedade/api/...`), with throttling and retries.
- `app/Services/Sle/EditalImporter.php`: maps API payloads to `Edital`, `Lote`, `LoteItem`, `LoteImagem` (idempotent upserts).
- `app/Services/Sle/EditalRef.php`: parses portal URLs / `unidade/numero/ano` identifiers.
- `app/Console/Commands`: `leilao:importar`, `leilao:sincronizar` and `leilao:resultados`.
- `app/Services/Sle/ExtratoLeilao.php` + `PdfParaTexto.php`: winning bids come from the "Extrato do Leilão" PDF (`pdftotext -layout`, poppler-utils in the image). Winner names/CPFs in the PDF are intentionally not stored. Bids above `SLE_ARREMATE_SUSPEITO_MULTIPLO` (20) times max(valor_minimo, valor_avaliacao) get `arremate_suspeito` (set in `Lote` `saving`) and are excluded from totals (`Edital::resumoArremate()`). Tests use `tests/Support/PdfSimples.php` and an anonymized text fixture, never real extracts.
- `app/Jobs/ImportarEdital.php`: queued import used by the web form and `--fila`.
- `app/Models/Lote.php`: `created`/`updated` events write a `LoteHistorico` snapshot when `situacao`, `valor_minimo` or `valor_avaliacao` change.
- `resources/views/partials/galeria.blade.php`: vanilla JS lightbox for any `[data-galeria]` element (JSON list, HTML-escaped via `{{ json_encode() }}`; `@json` does not escape quotes in this Laravel version).
- `routes/console.php`: `leilao:sincronizar --fila` and `leilao:resultados` checked every 5 minutes by the `scheduler` container (`schedule:work`) and run once per day after `SLE_SINCRONIZACAO_HORARIO`, catching up if the PC was off (`App\Support\ExecucaoDiaria`, cache-based marker; full manual runs count).
- Domain names are in Portuguese, matching the portal's vocabulary.

## API notes

- The API returns HTTP 500 with `{"code":"ER9999"}` both for failures and for editais/lots that don't exist.
- Money values are in reais (not cents). Dates are `Y-m-d H:i` in America/Sao_Paulo (the app timezone).
- In `api/edital/...`, the `cidade` field holds the executing unit's name; the real city only appears in `api/editais-disponiveis`.
