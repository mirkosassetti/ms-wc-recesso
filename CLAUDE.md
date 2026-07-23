# CLAUDE.md

Plugin WordPress/WooCommerce che implementa la funzione di recesso ex art. 54-bis del Codice del Consumo (D.Lgs. 209/2025, Direttiva UE 2023/2673) per e-commerce B2C. Compatibile HPOS.

## Comandi

- `composer install` — installa le dipendenze dev.
- `composer lint` — phpcs (WordPress Coding Standards). Deve chiudere con **0 errori e 0 warning**.
- `composer lint:fix` — phpcbf, corregge quanto auto-fixabile.
- `composer test` — PHPUnit (suite `tests/Unit`).
- `php bin/build.php` — genera `dist/ms-wc-recesso-<versione>.zip` pulito (solo file di produzione, via allowlist).
- **Rilascio**: push del tag `vX.Y.Z` (deve combaciare con l'header `Version:` di `ms-wc-recesso.php`, il workflow Release lo verifica) → il workflow pubblica la release GitHub con lo zip.
- **CI** (`.github/workflows/lint.yml`): matrice PHP 8.1 / 8.2 / 8.3 su push a `main` e PR → `php -l`, `composer lint`, `composer test`.

## Architettura

- PSR-4 `MS\WcRecesso\` → `src/`. In `ms-wc-recesso.php` c'è un autoloader di fallback: in produzione **non serve** Composer (`vendor/` viene usato solo se presente).
- Layer: `Support` (Options, Schema, Dates, Logger, Tokens, RateLimiter) · `Model` (Repository, WithdrawalRequest, RequestStatus) · `Domain` (Eligibility, Exclusion, WithdrawalService, AccessPolicy, OrderLocator) · `Frontend` (Shortcode, FlowController, MyAccountEndpoint, View, Assets) · `Emails` · `Admin` · `Integration/Hpos`. Bootstrap: `Plugin::instance()->boot()` su `plugins_loaded`.
- Persistenza su due tabelle custom: `{prefix}_ms_wc_recesso_requests` e `{prefix}_ms_wc_recesso_log` (vedi `src/Support/Schema.php`). Timestamp in **UTC**.
- I template in `templates/` sono override-abili dal tema (renderizzati via `View::render()`).

## Convenzioni

- PHP 8.1+. Ogni PR deve passare `composer lint` a 0 errori/warning.
- Ogni file PHP inizia con `defined( 'ABSPATH' ) || exit;` (nei test `ABSPATH` è definita in `tests/bootstrap.php`).
- **HPOS**: accedere agli ordini SOLO via CRUD API WooCommerce (`wc_get_order`, `$order->get_meta()`, `wc_get_orders`); mai query dirette su postmeta.
- **Test**: Brain Monkey + Mockery, nessuna installazione WP richiesta; base class in `tests/TestCase.php` (stub `__`, `get_option`, `apply_filters`, ecc.). `phpunit.xml.dist` ha `failOnWarning` e `beStrictAboutOutputDuringTests` attivi.
- **i18n**: text domain `ms-wc-recesso`. UI in italiano (lingua base), commenti/codice in inglese. Rigenerare `.pot`/`.po`/`.mo` con `wp i18n` quando cambiano le stringhe.
- **phpcs** esclude `bin/`, `dist/`, `tests/`, `vendor/`, `languages/`, `node_modules/`. I nomi file seguono PSR-4 (StudlyCase), non lo schema hyphenated di WPCS (sniff disabilitati apposta).
- La build zip usa un'**allowlist** (`ms-wc-recesso.php`, `uninstall.php`, `readme.txt`, `README.md`, `src`, `templates`, `assets`, `languages`): nessun file dev viene impacchettato.
- **Bump versione**: aggiornare l'header `Version:` **e** la costante `MS_WC_RECESSO_VERSION` in `ms-wc-recesso.php`, più `Stable tag`/changelog in `readme.txt`.
- **Estendibilità** via hook `ms_wc_recesso_*` (filtri/azioni documentati nel README, es. `ms_wc_recesso_eligible_statuses`, `ms_wc_recesso_item_eligibility`, `ms_wc_recesso_user_excluded`, `ms_wc_recesso_window_end`, `ms_wc_recesso_request_confirmed`, `ms_wc_recesso_status_changed`).
