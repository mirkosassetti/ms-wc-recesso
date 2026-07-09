# MS Recesso 54-bis per WooCommerce

Plugin WordPress/WooCommerce che implementa la **funzione di recesso** obbligatoria per gli e-commerce B2C italiani ai sensi dell'**art. 54-bis del Codice del Consumo** (D.Lgs. 209/2025, recepimento della Direttiva UE 2023/2673), applicabile ai contratti conclusi dal **19 giugno 2026**.

## Requisiti

- WordPress 6.0+
- WooCommerce 8.0+ (compatibile **HPOS**)
- PHP 8.1+

## Installazione e sviluppo

In produzione non serve Composer: l'autoloader SPL di fallback (in `ms-wc-recesso.php`) mappa `MS\WcRecesso\` su `src/`. Composer serve solo per i tool di sviluppo.

```bash
composer install
composer lint        # phpcs (WordPress Coding Standards)
composer lint:fix    # phpcbf
```

## Architettura

```
src/
├── Plugin.php            Container e wiring
├── Activator/Deactivator Attivazione (schema, pagina, endpoint), disattivazione
├── Integration/Hpos      Dichiarazione custom order tables
├── Support/              Schema, Options, Dates, Tokens, RateLimiter, Logger
├── Model/                RequestStatus (enum/stati), WithdrawalRequest, RequestRepository
├── Domain/               OrderLocator, EligibilityService, ExclusionRules, WithdrawalService
├── Frontend/             FlowController, Shortcode, MyAccountEndpoint, PlacementService, Assets, View
├── Emails/               EmailManager, Mailer, 3× WC_Email
└── Admin/                AdminPages, RequestsListTable, SettingsPage, OrderMetabox, ProductFields
templates/                Template front-end, email (html/plain), admin — override-abili da tema
```

Persistenza: due tabelle custom (`{prefix}_ms_wc_recesso_requests` e `_log`), timestamp in **UTC**. Il momento giuridico (`confirmed_at`) è generato server-side alla conferma; alla conferma la dichiarazione è congelata in `declaration_snapshot` (immutabile).

Stati: `pending_verification → draft → confirmed → in_review → approved | rejected_out_of_scope | completed`.

## Estendibilità (hook)

### Actions

| Action | Argomenti | Quando |
|---|---|---|
| `ms_wc_recesso_booted` | `Plugin $plugin` | Servizi core avviati |
| `ms_wc_recesso_request_confirmed` | `WithdrawalRequest $request` | Recesso trasmesso (Fase email) |
| `ms_wc_recesso_status_changed` | `WithdrawalRequest $request, RequestStatus $from` | Transizione di stato admin |
| `ms_wc_recesso_logged` | `int $request_id, string $event` | Evento scritto nel log probatorio |

### Filters

| Filter | Firma | Scopo |
|---|---|---|
| `ms_wc_recesso_window_end` | `DateTimeImmutable $end, WC_Order $order` | Override della scadenza della finestra (es. data di consegna reale) |
| `ms_wc_recesso_eligible_statuses` | `string[] $statuses` | Stati ordine idonei al recesso |
| `ms_wc_recesso_item_eligibility` | `array $eligibility, $item, $product, $order` | Idoneità per singola riga (art. 59) |
| `ms_wc_recesso_footer_link_enabled` | `bool $enabled` | Mostra/nasconde il pulsante nel footer |

Esempio — data di consegna reale da un plugin di tracking:

```php
add_filter( 'ms_wc_recesso_window_end', function ( $end, $order ) {
    $delivered = $order->get_meta( '_delivery_date' ); // UTC
    if ( $delivered ) {
        $base = new DateTimeImmutable( $delivered, new DateTimeZone( 'UTC' ) );
        return $base->modify( '+14 days' );
    }
    return $end;
}, 10, 2 );
```

## Metadati

- **Order meta**: `_ms_wc_recesso_request_uuid`, `_ms_wc_recesso_request_status` (mirror della richiesta più recente).
- **Product meta** (art. 59): `_ms_wc_recesso_excluded` (`yes`/`no`), `_ms_wc_recesso_exclusion_reason`.

## Override dei template

- Front-end: `tuo-tema/ms-wc-recesso/<template>.php`
- Email: `tuo-tema/woocommerce/emails/<template>.php`

## Frontend

- Pagina dedicata con shortcode `[ms_recesso_54bis]` (slug `recesso-dal-contratto`).
- Endpoint "Il mio account" `recesso`.
- Shortcode ausiliario `[ms_recesso_link]` per inserire manualmente il link.

## Sicurezza

Nonce su tutti i form, sanitizzazione/escaping ovunque, prepared statements, rate limiting sul lookup pubblico (5/ora per IP hashato), token guest con `random_bytes` (in DB solo l'hash) e scadenza configurabile (default 48h), nessun dato personale in query string. La conferma è idempotente e il lookup guest è protetto da dedupe contro il doppio invio.

## i18n

Text domain `ms-wc-recesso`, lingua base **italiano**. File modello: `languages/ms-wc-recesso.pot`.

Rigenerazione:

```bash
wp i18n make-pot . languages/ms-wc-recesso.pot --domain=ms-wc-recesso --exclude=vendor,node_modules,assets
```

## CI e rilascio

GitHub Actions:

- **Lint** (`.github/workflows/lint.yml`): a ogni push su `main` e a ogni pull request esegue `php -l` e phpcs (WordPress Coding Standards).
- **Release** (`.github/workflows/release.yml`): al push di un tag `v*` verifica che il tag coincida con l'header `Version:`, genera lo zip con `bin/build.php` e pubblica una GitHub Release con l'asset allegato.

Per rilasciare una nuova versione:

```bash
# 1. aggiorna Version: in ms-wc-recesso.php e Stable tag in readme.txt
# 2. (se cambiano le stringhe) rigenera .pot/.po/.mo
git commit -am "Release x.y.z"
git tag vx.y.z
git push origin main --tags
```

## Uninstall

Per impostazione predefinita i dati vengono **conservati** (documenti probatori). La rimozione completa avviene solo se l'opzione `retain_data_on_uninstall` è disattivata.
