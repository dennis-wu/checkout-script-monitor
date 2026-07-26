=== Checkout Script Monitor for WooCommerce ===
Contributors: cybershieldstudio
Tags: pci, security, content security policy, subresource integrity, checkout
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See every script on your checkout and where you stand on PCI DSS 6.4.3, in plain English. A readiness and visibility tool, not a compliance guarantee.

== Description ==

Checkout Script Monitor scans your own store pages and shows you the scripts loading on them, so you can see what runs on your checkout and where you stand on PCI DSS Requirement 6.4.3. It is a readiness and visibility aid. **It does not make you PCI compliant, and it is not affiliated with or endorsed by the PCI Security Standards Council.** The compliance decision stays yours.

What it does:

* **Script inventory.** Scans your home, shop, cart, and checkout pages and lists every external script, marked first-party or third-party, tagged by vendor and category, with its integrity (SRI) status.
* **Plain-English 6.4.3 readout.** Counts your scripts, flags the ones missing an integrity check, and explains in merchant language what Requirement 6.4.3 asks and what to do.
* **CSP Report-Only monitoring (optional, off by default).** Emits a `Content-Security-Policy-Report-Only` header on the cart and checkout, built from a baseline of the scripts already on **your own** site. It never blocks anything. When a new or changed script appears, your browser reports it, so you can catch drift. Reports are stored on your own site.

How the baseline works, and why it is safe:

Checkout Script Monitor does not ship a list of "trusted" sources. That would mean deciding on your behalf which third parties are safe, which is exactly the risk you want to avoid (a trusted provider getting compromised is a real attack path). Instead, the baseline is the scripts already present on your own site on the day you set it. Report-Only then flags anything that later differs from that baseline. Honest note: the baseline is "what is here now," not a clean bill of health, so review your inventory and remove anything unwanted before you set it.

Privacy: Checkout Script Monitor makes no outbound connections except scanning your own site's pages when you click "Scan my store." CSP reports are received and stored on your own WordPress site; the visitor IP address and referrer are dropped and never stored.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/checkout-script-monitor`, or install it from the Plugins screen.
2. Activate it.
3. Open **Checkout Script Monitor > Inventory & 6.4.3** and click **Scan my store**.
4. Review the list and remove anything you do not need.
5. (Optional) In **Settings**, set your baseline from the latest scan, then turn on **CSP Report-Only monitoring**. Check **Violations** for drift.

== Frequently Asked Questions ==

= Does this make my store PCI compliant? =

No. It gives you visibility and a starting point for Requirement 6.4.3. Compliance is your responsibility and, depending on your setup, may involve your acquirer or a QSA.

= Will turning on CSP break my checkout? =

No. Checkout Script Monitor only uses `Content-Security-Policy-Report-Only`, which reports but never blocks.

= Why do some scripts not show up? =

The scan reads scripts written into the page HTML. Scripts injected later by other scripts (for example by a tag manager) may not appear. A full external scan can catch those.

= Where do the violation reports go? =

To your own WordPress site's database. Nothing is sent to a third party. A future opt-in version may offer a hosted collector, disclosed separately.

== Changelog ==

= 0.2.0 =
* Plain-language redesign for non-technical store owners. A 3-step guided setup (scan your checkout, mark scripts as trusted, turn on monitoring). Renamed screens: "Checkout Scripts" and "Alerts" (was Violations). The "Trust this" button on an alert adds a script to your trusted list and clears it. PCI/CSP wording moved into optional "why this matters" and "technical details" sections. No change to how monitoring works.

= 0.1.3 =
* Renamed the plugin to Checkout Script Monitor (display name). The tool monitors and inventories the scripts on your checkout (Report-Only, never blocks) rather than guarding or blocking them, and the name now reflects that. No functional change.

= 0.1.2 =
* Focus violation reports on off-baseline script LOADS (the drift signal), not the site's own inline scripts. Added 'unsafe-inline' to the Report-Only policy so the browser stops reporting the checkout's many legitimate inline blocks (no security effect — the policy is never enforced), and the report endpoint now drops inline/eval/data/blob reports as a safety net. The Violations screen now shows only external hosts not in your confirmed baseline.

= 0.1.1 =
* Fix: the CSP Report-Only header now emits reliably on the cart/checkout pages. It was gated on a conditional (is_cart/is_checkout) evaluated too early in the request, so the header never went out. Moved header emission to template_redirect, where the page conditionals are reliable.

= 0.1.0 =
* Initial release: script inventory across home/shop/cart/checkout, plain-English PCI 6.4.3 readout, and optional CSP Report-Only monitoring with a self-baselined allowlist and local violation storage.
