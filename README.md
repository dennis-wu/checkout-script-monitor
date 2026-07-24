# Script Guard for WooCommerce

A free, open-source WordPress/WooCommerce plugin that inventories the scripts on a
merchant's checkout and shows where they stand on **PCI DSS 6.4.3**, in plain
English. Built by [CyberShield Studio](https://cybershieldstudio.com).

It is a **readiness and visibility** aid, not a compliance guarantee, and it is not
affiliated with or endorsed by the PCI Security Standards Council.

## What it does (MVP / v0.1.0)

- **Script inventory** — scans the store's own home/shop/cart/checkout pages
  (`wp_remote_get`), parses `<script src>`, classifies first- vs third-party, tags
  vendor/category, and records SRI status.
- **6.4.3 readout** — counts, a per-script table, and plain-English guidance.
- **CSP Report-Only** (off by default) — emits a `Content-Security-Policy-Report-Only`
  header on cart/checkout, built from a **baseline of the merchant's own observed
  scripts** (never an opinionated "trusted sources" default), with a local REST
  endpoint (`/wp-json/css-script-guard/v1/csp-report`) storing violations in the
  site's own DB. Visitor IP and referrer are dropped at ingest.

## Design principles

- **No endorsement.** The CSP allowlist is derived from the merchant's own site, so
  the plugin vouches for no third party. It alerts on drift.
- **No phone-home.** The only outbound request is scanning the merchant's own URLs.
- **Never claims compliance.** Wording is readiness/visibility throughout.

## Structure

```
css-script-guard.php        Bootstrap: header, constants, activation
includes/class-inventory.php Scan + parse + classify + SRI + snapshot
includes/class-csp.php       Report-Only header + REST report endpoint + storage
includes/class-admin.php     Admin menus and screens
uninstall.php                Removes options + violations table
readme.txt                   wordpress.org listing
```

## Roadmap (see the CyberShield plan doc)

- v1.1: opt-in **hosted CSP collector** (cross-site summary + email digest + funnel),
  which makes CyberShield a data processor (disclosed, opt-in, retention-limited).
- Later: capture JS-injected scripts (output buffering), freemium Pro.

## License

GPL-2.0-or-later. Free and open source.
