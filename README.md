# Tritonia Library Card Renewal — v4

PHP 8.2+ application for Koha 26.05, with Finnish, Swedish and English UI, light/dark themes, library-card/PIN login, email OTP, and 1/2/5-year extension selection. **Demo only until Koha integration and authorization rules have been verified.**

## Install under existing Apache website (no dedicated port)

Copy the **contents** of this directory to the website document root subfolder, for example `/var/www/html/app-folder/` (adjust for your existing VirtualHost DocumentRoot). The entry point is now directly `index.php` in that folder, so visit `https://www.tritonia.fi/app-folder/index.php`. Apache must have PHP-FPM configured and serve `.php` files. No separate `php -S` process or port 8088 is required.

**Security:** This package includes Apache `.htaccess` deny rules for `src/` and `storage/`, but they only work if `AllowOverride` permits them. **Prefer moving `src/` and `storage/` outside the public DocumentRoot and updating `require` and storage paths**, or configure explicit `<Directory>` denies in the VirtualHost. Verify `https://.../src/config.php` and `https://.../storage/` cannot be accessed before deployment. Never expose API secrets. Ensure `storage/` is writable by the PHP service account, not publicly accessible. Production must use HTTPS.

## Logo

The supplied `assets/img/logo.svg` is bundled and displayed in both light and dark modes. No separate theme logos are required. To replace it, overwrite that file with the updated Tritonia logo.

## Demo login

Card: `1234567890` (exactly 10 digits); PIN: `12345` (exactly 5 digits). Demo displays OTP on screen and **never changes Koha**. OTP is valid for **15 minutes**; 5 incorrect attempts maximum.

## Production integration — incomplete

1. Set `src/config.php` for your environment, restrict access to it, and ensure the PHP runtime can send email.
2. Verify Koha 26.05's password validation endpoint request/response schema and minimum OAuth2 permissions against the installed API specification.
3. Implement and test patron category eligibility, restrictions, debt, and maximum validity checks server-side.
4. Validate whether `PATCH /api/v1/patrons/{patron_id}` supports the intended expiry update. Directly updating `expiry_date` is not necessarily equivalent to Koha's membership renewal workflow. Add appropriate audit and confirmation notifications.
5. Do not enable production until tested on a staging Koha and security-reviewed.

## Files

- `index.php`: HTTP entry point
- `assets/style.css`, `assets/app.js`: branding, language/theme UI and date preview
- `assets/img/`: supply your own logo assets
- `src/core.php`, `src/config.php`: server-side logic and configuration (must be protected)
- `storage/`: rate-limit data (must be protected and writable)

Use Apache's normal HTTPS VirtualHost and existing PHP-FPM; no additional TCP listener is necessary.

## Code formatting (v4)

Version 4 reformats PHP, JavaScript, CSS and the mixed PHP/HTML entry point for easier editing with nano. Application behavior, demo settings, API integration status, and the single `assets/img/logo.svg` remain unchanged. PHP syntax was checked with `php -l`. The code is not production-ready until the Koha authorization and renewal logic are implemented and tested.

### v4.1: Five-year demo renewal fix

The maximum expiry date was previously limited to five years from today, which prevented a five-year extension when the card already had remaining validity. The configurable limit is now **10 years from today**, allowing the 1-, 2-, and 5-year demo options to work with the supplied demo account. This limit also applies to production if enabled: review it against library policy before deployment. Production Koha renewal integration is not yet validated.

## License

This project is licensed under the MIT License.

Copyright (c) 2026 Jani Päivärinta

See the [LICENSE](LICENSE) file for details.
