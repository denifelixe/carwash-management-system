---
paths:
  - '{app/Support/AppSettings.php,app/Support/Demo/Brand.php,app/Http/Controllers/Demo/**}'
---

# Demo

## Saved branding dresses the live domains only; the demo keeps the shipped brand
The demo and the live console share one installation, so everything an owner saves in Master > App Settings (name, app photo, favicon set, social image, meta title/description, WhatsApp, Instagram) would otherwise leak onto the demo through app_settings.

AppSettings::brand() answers every branding key as unset when the request is on the demo domain (or APP_TYPE=DEMO), which drops each getter back to the shipped ZenWash defaults. Read branding through the getters (appName, appPhotoUrl, favicon*Url, meta*, whatsapp, instagram), never AppSettings::get() on a branding key — Demo\AppSettingController derives hasAppPhoto/hasMetaImage from the URL getters for exactly this reason. The timezone is not branding and still reads straight from the settings.

Tests that assert saved branding reaches the rendered <head> must request a live domain page (e.g. admin.master.app-settings.index), not route('demo.home').
