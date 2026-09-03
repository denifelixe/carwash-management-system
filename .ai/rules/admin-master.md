---
paths:
  - '{app/Http/Controllers/Admin/Master/ReceiptController.php,app/Http/Controllers/Admin/Master/AppSettingController.php,app/Actions/Admin/UpdateReceiptSettings.php,app/Actions/Admin/UpdateAppBranding.php,resources/js/pages/admin/master/Receipt.vue,resources/js/pages/admin/master/AppSettings.vue}'
---

# Admin Master

## Slip settings live in Master &gt; Struk, not Master &gt; Aplikasi
The receipt settings (business name, footer note, logo image and width, plus the print-logo / print-QR switches) are their own module `master_receipt`, page `admin/master/Receipt`, routes `admin.master.receipt.index|update` on `master/struk`. UpdateReceiptSettings owns those writes; UpdateAppBranding must stay about branding assets only, and UpdateAppSettingRequest no longer validates receipt fields. Do not move them back. Both forms POST rather than PATCH: each carries an upload, and PHP does not populate $_FILES on a multipart PUT/PATCH, so a browser upload would arrive empty while a Laravel feature test (which builds the request directly) still passed.

The slip has its own mark: receipt_photo, uploaded on the Receipt page and written by UpdateReceiptSettings. AppSettings::receiptPhotoUrl() falls back to the app photo when it is unset, so removing it hands the slip back rather than blanking it, and the console keeps brand.photo either way. The page reads `settings.appPhotoUrl` only to preview that fallback.

Adding a Master child means five places: the seed migration (sort_order after master_app_settings), AdminShell::MODULE_ICONS + moduleEntry(), ModuleGroups::MODULE_GROUPS, the AppServiceProvider gate loop, and AdminLayout.vue's moduleIcons. Demo parity also needs Demo\ReceiptController, a routes/demo.php entry with `demo.module:master_receipt`, and a RoleAccess::modules()/matrix() row — AdminRoleSchemaTest compares admin_modules row-for-row against RoleAccess::modules(), so name and description must match the migration exactly.
