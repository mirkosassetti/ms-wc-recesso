=== MS Recesso 54-bis per WooCommerce ===
Contributors: mirkosassetti
Tags: woocommerce, recesso, codice del consumo, consumatori, hpos
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Funzione di recesso obbligatoria ex art. 54-bis del Codice del Consumo (D.Lgs. 209/2025) per e-commerce B2C italiani su WooCommerce.

== Description ==

Implementa la "funzione di recesso" prevista dall'art. 54-bis del Codice del Consumo, introdotto dal D.Lgs. 209/2025 (recepimento della Direttiva UE 2023/2673), applicabile ai contratti conclusi dal 19 giugno 2026.

Caratteristiche principali:

* Link/pulsante "Recedere dal contratto qui" sempre disponibile (footer, shortcode, "I miei ordini" e dettaglio ordine in Il mio account).
* Dichiarazione di recesso online con i soli dati necessari (nome, ordine, email di conferma); motivo del reso facoltativo (minimizzazione dei dati).
* Flusso a due step con schermata di riepilogo e pulsante di conferma separato: il recesso è trasmesso solo alla conferma.
* Avviso di ricevimento su supporto durevole (email) con il contenuto della dichiarazione e la data/ora della trasmissione, generata server-side in UTC.
* Supporto ordini guest: lookup pubblico (numero ordine + email) con link di verifica via email (token monouso a scadenza 48h).
* Esercizio non bloccante: numero ordine ed email non corrispondenti non impediscono la trasmissione; la richiesta viene registrata e marcata per verifica manuale.
* Eccezioni art. 59: prodotti/categorie escludibili dal recesso, mostrati come non selezionabili con motivazione visibile.
* Pannello admin: elenco richieste con filtri e cronologia, transizioni di stato gestionali, metabox sull'ordine, pagina impostazioni.
* Compatibile HPOS (High-Performance Order Storage); nessuna query diretta su postmeta degli ordini.

== Installation ==

1. Carica la cartella `ms-wc-recesso` in `/wp-content/plugins/`.
2. Attiva il plugin dal menu Plugin di WordPress.
3. All'attivazione vengono create le tabelle, la pagina "Recesso dal contratto" (shortcode `[ms_recesso_54bis]`) e l'endpoint "Il mio account".
4. Configura da "Recesso → Impostazioni". I testi delle email si gestiscono in WooCommerce → Impostazioni → Email.

== Frequently Asked Questions ==

= È compatibile con HPOS? =
Sì. Tutti gli accessi agli ordini usano le CRUD API di WooCommerce.

= I dati vengono cancellati alla disinstallazione? =
No per impostazione predefinita: le richieste di recesso sono documenti probatori. La rimozione avviene solo disattivando l'opzione "Conserva le richieste alla disinstallazione".

= Come si personalizzano i template? =
Copiando i file da `templates/` del plugin in `tuo-tema/ms-wc-recesso/` (front-end) o `tuo-tema/woocommerce/ms-wc-recesso/` e `tuo-tema/woocommerce/emails/` (email).

== Changelog ==

= 0.1.0 =
* Versione iniziale: flusso loggato e guest, email transazionali (verifica, ricevuta, notifica admin), pannello admin, esclusioni art. 59, compatibilità HPOS.
