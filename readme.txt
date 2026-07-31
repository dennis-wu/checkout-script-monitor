=== Checkout Script Monitor for WooCommerce ===
Contributors: cybershieldstudio
Tags: woocommerce, ecommerce security, pci compliance, checkout, card skimming
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor the scripts on your WooCommerce checkout, spot card-skimming risk, and get alerted when one changes. Visibility, not a compliance guarantee.

== Description ==

Card-skimming attacks (also called e-skimming or Magecart) work by slipping a malicious script onto your checkout, where it quietly copies your customers' card details as they type. The hard part for a store owner is easy to miss: you cannot watch what you cannot see. A typical WooCommerce checkout loads a dozen or more scripts from plugins, themes, and third parties, and nothing normally tells you when that list changes.

Checkout Script Monitor gives you that visibility. It scans your own store pages and shows you every script loading on them, so you can see what runs on your checkout and where you stand on PCI DSS Requirement 6.4.3. **It is a readiness and visibility aid. It does not make you PCI compliant, it does not block anything, and it is not affiliated with or endorsed by the PCI Security Standards Council.** The compliance decision stays yours.

What it does:

* **Script inventory for your checkout.** Scans your home, shop, cart, and checkout pages and lists every external script, named by source (the WordPress plugin, theme, or core component for your own scripts, or the outside vendor for external ones), with its integrity (SRI) status.
* **Plain-English PCI 6.4.3 readout.** An at-a-glance line counts the scripts on your checkout and how many lack an integrity (SRI) check, and a built-in explainer covers what Requirement 6.4.3 asks and what to do, in merchant language.
* **Drift monitoring (optional, off by default).** Emits a `Content-Security-Policy-Report-Only` header on the cart and checkout, built from a baseline of the scripts already on **your own** site. It never blocks anything. When a new or changed script appears, your browser reports it, so you can catch drift, the early signal of a skimmer, a rogue plugin update, or an unexpected third party.

How the baseline works, and why it is safe:

Checkout Script Monitor does not ship a list of "trusted" sources. That would mean deciding on your behalf which third parties are safe, which is exactly the risk you want to avoid (a trusted provider getting compromised is a real attack path). Instead, the baseline is the scripts already present on your own site on the day you set it. Report-Only then flags anything that later differs from that baseline. Honest note: the baseline is "what is here now," not a clean bill of health, so review your inventory and remove anything unwanted before you set it.

Who it is for:

WooCommerce store owners who want to see what runs on their payment page and be told when it changes, and the developers and agencies who support them. The 6.4.3 readout maps directly to the script-inventory requirement (mandatory for SAQ A-EP and D) and gives SAQ A merchants the evidence to make the "not susceptible to scripts" self-attestation from what they can see, not from hope.

Privacy: Checkout Script Monitor makes no outbound connections except scanning your own site's pages when you click "Scan my checkout." CSP reports are received and stored on your own WordPress site; the visitor IP address and referrer are dropped and never stored.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/checkout-script-monitor`, or install it from the Plugins screen.
2. Activate it. A **Checkout Script Monitor** menu appears in your admin sidebar.
3. Open **Checkout Script Monitor > Checkout Scripts** and follow the short 3-step setup: scan your checkout, mark the scripts it finds as trusted, and (optionally) turn on monitoring.
4. With monitoring on, any new or changed script on your checkout appears under **Alerts** for you to review. Trust it if you recognize it, or remove it from your store if you do not.

== Frequently Asked Questions ==

= Does this stop card skimming or Magecart? =

No, and be wary of any plugin that says it does. Checkout Script Monitor is a visibility and monitoring tool: it shows you every script on your checkout and alerts you when one changes, which is how you catch a skimmer early. It is Report-Only and never blocks. Deciding what belongs on your payment page, and removing what does not, stays your call.

= Is this a WooCommerce security plugin? =

It is a focused one. Rather than a broad firewall or malware scanner, it does one job well: inventory the scripts on your checkout and payment pages and alert you to changes, mapped to PCI DSS Requirement 6.4.3. It complements a general security plugin; it does not replace one.

= Does this make my store PCI compliant? =

No. It gives you visibility and a starting point for Requirement 6.4.3. Compliance is your responsibility and, depending on your setup, may involve your acquirer or a QSA.

= Will turning on monitoring break my checkout? =

No. Checkout Script Monitor only uses `Content-Security-Policy-Report-Only`, which reports but never blocks. Your customers see and experience no change.

= Why do some scripts not show up? =

The scan reads scripts written into the page HTML. Scripts injected later by other scripts (for example by a tag manager) may not appear. A full external scan can catch those.

= Where do my alerts go? =

To your own WordPress site's database. Nothing is sent to a third party. A future opt-in version may offer a hosted collector, disclosed separately.

== Screenshots ==

1. First-run setup: the guided 3-step onboarding on the Checkout Scripts screen — scan your checkout, mark the scripts it finds as trusted, then turn on monitoring.
2. Checkout Scripts: an at-a-glance count of the scripts on your checkout, then every script with its source domain, who loaded it (WordPress core, a plugin or theme, or an outside vendor), and its tamper-check (SRI) status; rows are shaded by trust once you set a trusted list.
3. Alerts: a new or changed script that is not on your trusted list, with times seen, last seen, and a one-click "Trust it".
4. Settings: the monitoring toggle (Content-Security-Policy-Report-Only) and the technical details of how it works.

== About the author ==

Checkout Script Monitor is built by CyberShield Studio, a founder-led PCI compliance practice for e-commerce merchants. Its maker, Dennis Wu, holds the CISSP and PCIP certifications and has 30+ years in security. The plugin is open source (GPLv2), so you can read every line yourself.

== Changelog ==

= 0.3.4 =
* Checkout Scripts: added an at-a-glance summary line above the script table — how many scripts run on your checkout, how many are from outside companies, and how many have no tamper (SRI) check — so the PCI 6.4.3 picture is visible without reading the whole table. Minor footer and settings-label polish.

= 0.3.3 =
* Author credentials (CISSP, PCIP) surfaced in the plugin metadata and a new "About the author" section. Corrected a leftover "ScriptGuard" identifier in the self-scan user-agent to "CheckoutScriptMonitor". No functional change.
* Checkout Scripts screen: added a "Source" column showing the domain each script loads from (the same value your trusted list uses), made "Loaded by" a click-to-sort heading so scripts from the same source group together, and shaded each row by trust (green for a domain you trust, grey for one not on your list yet) once a baseline is set. The trusted-sources list now sits above the full script table.
* Alerts: softened the guidance to "consider removing it from your store," renamed the per-alert button to "Trust it," and removed the SAQ A Readiness advisor link from the in-plugin tip. Added a friendly thank-you note in the footer of the plugin's own admin screens.

= 0.3.2 =
* The trusted list now stores the exact host each script loads from, including any "www." prefix, so a trusted domain matches what you see in the script list (e.g. www.googletagmanager.com, not googletagmanager.com). First-party detection still treats www and non-www as the same site. Root-cause follow-up to 0.3.1.

= 0.3.1 =
* Fix a false positive: a trusted domain now also covers its "www." form (and vice versa). Trusting "googletagmanager.com" no longer flags "www.googletagmanager.com" as a new script. CSP host matching is exact and does not imply www, so the policy now emits both.

= 0.3.0 =
* The scan now names the source of every script. Your own scripts are labeled by their WordPress plugin, theme, or core component (read from your actually installed plugins, so any plugin is recognized, not just popular ones), and outside scripts are tagged "external" next to the vendor. Much easier to recognize what runs on your checkout at a glance.

= 0.2.1 =
* The trusted list is now sticky. "Add anything new from my latest scan" merges new domains and never removes ones you already trust, so a script that is temporarily unavailable (or a manually-trusted domain) can no longer be dropped by a re-scan. Added a per-domain Remove button for deliberate pruning, and clarified that the list holds domains.

= 0.2.0 =
* Plain-language redesign for non-technical store owners. A 3-step guided setup (scan your checkout, mark scripts as trusted, turn on monitoring). Renamed screens: "Checkout Scripts" and "Alerts" (was Violations). The "Trust this" button on an alert adds a script to your trusted list and clears it. PCI/CSP wording moved into optional "why this matters" and "technical details" sections. No change to how monitoring works.

= 0.1.3 =
* Renamed the plugin to Checkout Script Monitor (display name). The tool monitors and inventories the scripts on your checkout (Report-Only, never blocks) rather than guarding or blocking them, and the name now reflects that. No functional change.

= 0.1.2 =
* Focus violation reports on off-baseline script LOADS (the drift signal), not the site's own inline scripts. Added 'unsafe-inline' to the Report-Only policy so the browser stops reporting the checkout's many legitimate inline blocks (no security effect, the policy is never enforced), and the report endpoint now drops inline/eval/data/blob reports as a safety net. The Alerts screen now shows only external hosts not in your confirmed baseline.

= 0.1.1 =
* Fix: the CSP Report-Only header now emits reliably on the cart/checkout pages. It was gated on a conditional (is_cart/is_checkout) evaluated too early in the request, so the header never went out. Moved header emission to template_redirect, where the page conditionals are reliable.

= 0.1.0 =
* Initial release: script inventory across home/shop/cart/checkout, plain-English PCI 6.4.3 readout, and optional CSP Report-Only monitoring with a self-baselined allowlist and local violation storage.
