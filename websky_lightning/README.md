# Websky Lightning for OpenCart 3

Independent performance module for OpenCart 3.0.3.x. This first release provides a safe admin configuration surface and an OCMOD response optimizer that only applies when enabled and when the response is eligible (GET/HEAD HTML responses, no cookies, no admin/AJAX/API routes).

## Install

1. Upload `websky_lightning.ocmod.zip` through **Extensions → Installer**.
2. Refresh **Extensions → Modifications**.
3. Install and enable **Websky Lightning** under Extensions → Extensions → Modules.

No external services are required. Page cache is conservative (15 minutes and disabled for cookies/AJAX). The optional cron endpoint is `index.php?route=extension/module/websky_lightning_cron&token=...`; protect its token in admin configuration before scheduling it.
