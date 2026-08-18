# Websky Lightning for OpenCart 3

Independent performance module for OpenCart 3.0.3.x. The page cache uses stable customer-group and device-class profiles, so a new browser can reuse an already-generated safe profile without mixing customer-group prices or responsive HTML. Logged-in customers and sessions with cart contents remain excluded from full-page caching to prevent personal data leakage.

## Install

1. Upload `websky_lightning.ocmod.zip` through **Extensions → Installer**.
2. Refresh **Extensions → Modifications**.
3. Install and enable **Websky Lightning** under Extensions → Extensions → Modules.

No external services are required. Page cache is conservative and excludes AJAX, account, checkout, API, logged-in, and cart-bearing requests. Admin pre-generation warms modern and legacy browser variants for the desktop, mobile, and tablet profiles of the default customer group. The optional cron endpoint is `index.php?route=extension/module/websky_lightning_cron&token=...`; protect its token in admin configuration before scheduling it.
