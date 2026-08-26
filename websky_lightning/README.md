# Websky Lightning for OpenCart 3

Independent performance module for OpenCart 3.0.3.x. The page cache uses stable customer-group and device-class profiles, so a new browser can reuse an already-generated safe profile without mixing customer-group prices or responsive HTML. Logged-in customers and sessions with cart contents remain excluded from full-page caching to prevent personal data leakage.

## Install

1. Upload `websky_lightning.ocmod.zip` through **Extensions → Installer**.
2. Refresh **Extensions → Modifications**.
3. Install and enable **Websky Lightning** under Extensions → Extensions → Modules.

No external services are required. Page cache is conservative and excludes AJAX, account, checkout, API, logged-in, and cart-bearing requests. Admin pre-generation warms modern and legacy browser variants for the desktop, mobile, and tablet profiles of the default customer group. The optional cron endpoint is `index.php?route=extension/module/websky_lightning_cron&token=...`; protect its token in admin configuration before scheduling it.

Full-page cache files do not expire or prune automatically. Product edits invalidate the matching product cache plus listing pages, while category edits invalidate the affected category and homepage cache. The next eligible guest request stores the replacement. The admin **Cache all pages** action walks every product and category in small resumable batches and warms the safe desktop, mobile, and tablet variants. Browser capability headers do not create separate HTML page caches. Administrators can still clear every page explicitly with the module's clear-cache action.

Cache hits for anonymous pages now replace session-only `no-store` headers with a one-year public cache policy and remove response cookies, while authenticated, cart, checkout, and account requests remain excluded.
