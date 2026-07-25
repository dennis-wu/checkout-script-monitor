# Script Guard for WooCommerce

A free, open-source WordPress/WooCommerce plugin that inventories the scripts on a
merchant's checkout and shows where they stand on **PCI DSS 6.4.3**, in plain
English. Built by [CyberShield Studio](https://cybershieldstudio.com).

It is a **readiness and visibility** aid, not a compliance guarantee, and it is not
affiliated with or endorsed by the PCI Security Standards Council.

## What it does

- **Script inventory** — scans the store's own home/shop/cart/checkout pages
  (`wp_remote_get`), parses `<script src>`, classifies first- vs third-party, tags
  vendor/category, and records SRI status.
- **6.4.3 readout** — counts, a per-script table, and plain-English guidance.
- **CSP Report-Only** (off by default) — emits a `Content-Security-Policy-Report-Only`
  header on cart/checkout, built from a **baseline of the merchant's own observed
  scripts** (never an opinionated "trusted sources" default), with a local REST
  endpoint (`/wp-json/css-script-guard/v1/csp-report`) storing violations in the
  site's own database. Visitor IP and referrer are dropped at ingest. Report-Only
  never blocks anything.

## Installation

**From a release zip**

1. In WordPress: **Plugins → Add New Plugin → Upload Plugin**, choose
   `css-script-guard.zip`, **Install Now**, then **Activate**.
2. A **Script Guard** menu appears in the admin sidebar.

**From source**

```bash
git clone https://github.com/dennis-wu/css-script-guard.git \
  wp-content/plugins/css-script-guard
```

Then activate it from **Plugins**.

Requirements: WordPress 6.2+, PHP 7.4+, and WooCommerce (the CSP header is scoped to
the cart/checkout pages).

## Usage

1. **Script Guard → Inventory & 6.4.3 → Scan my store** to build the inventory.
2. Review it, then **Set baseline from my latest scan**.
3. Optionally enable **CSP Report-Only** in Settings. New or changed scripts on
   checkout then appear under **Violations** as drift to review.

## Design principles

- **No endorsement.** The CSP allowlist is derived from the merchant's own site, so
  the plugin vouches for no third party. It alerts on drift.
- **No phone-home.** The only outbound request is scanning the merchant's own URLs.
- **Never claims compliance.** Wording is readiness/visibility throughout.
- **Never blocks.** The plugin only ever emits the Report-Only CSP variant, so it
  cannot break a checkout.

## Structure

```
css-script-guard.php         Bootstrap: header, constants, activation
includes/class-inventory.php Scan + parse + classify + SRI + snapshot
includes/class-csp.php       Report-Only header + REST report endpoint + storage
includes/class-admin.php     Admin menus and screens
uninstall.php                Removes options + violations table
readme.txt                   wordpress.org listing
```

## License

GPL-2.0-or-later. Free and open source.
