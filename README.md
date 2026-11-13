# Tritonia Koha Library Card Renewal — v1.0

A PHP 8.2+ web application for renewing Tritonia library cards through the Koha 26.05 REST API.

The application provides a Finnish, Swedish, and English interface, light and dark themes, library card and PIN authentication, email-based one-time password (OTP) verification, and 1-, 2-, and 5-year renewal options.

**Deployment status:** The Koha API operations and SMTP gateway acceptance have been tested separately. Complete end-to-end testing, eligibility checks, and security review must be completed before public access is enabled.

## Features

- Finnish, Swedish, and English user interface
- Light and dark themes
- Library card number and PIN authentication
- Koha REST API integration with OAuth2 client credentials
- Six-digit email OTP, valid for 15 minutes, with a maximum of five incorrect attempts
- One-, two-, and five-year renewal options
- Configurable maximum expiry date and server-side rate limiting
- Demo and production modes

## Requirements

- PHP 8.2 or newer
- Apache HTTP Server with PHP-FPM and HTTPS
- Outbound HTTPS connectivity to the Koha REST API
- A working mail transport for PHP `mail()`
- Koha OAuth2 client credentials with appropriate permissions
- A protected, writable `storage/` directory

No separate PHP development server or dedicated TCP listener is needed.

## Installation

Deploy the application within an existing Apache VirtualHost, for example:

```text
/var/www/html/uusiminen-fornyelse-renewal/
```

The entry point is `index.php`. Ensure PHP-FPM is enabled and Apache processes the included `.htaccess` rules. The application must be served over HTTPS.

On the current AlmaLinux deployment, `storage/` is owned by `apache:apache`. Protect `src/config.php` and its backups from HTTP access and Git tracking. Never publish production credentials.

## Configuration

Configure `src/config.php` for the deployment. Relevant settings include:

| Setting | Purpose |
| --- | --- |
| `mode` | `demo` or `production` |
| `koha_base_url` | Koha API base URL |
| `koha_client_id`, `koha_client_secret` | OAuth2 client credentials (keep secret) |
| `koha_token_path` | OAuth2 token endpoint |
| `koha_password_path` | PIN validation endpoint |
| `koha_patron_path` | Patron record endpoint |
| `renewal_method` | Koha expiry-update method |
| `sender_email` | Visible sender email address |
| `max_expiry_years_from_today` | Maximum permitted expiry date |
| `otp_ttl_seconds` | OTP validity in seconds |
| `session_ttl_seconds` | Session validity in seconds |

The currently configured integration settings are:

```php
'mode' => 'production',
'koha_base_url' => 'https://app1.tritonia.koha.csc.fi',
'renewal_method' => 'put_expiry_date',
'sender_email' => 'loans@uwasa.fi',
'max_expiry_years_from_today' => 10,
'otp_ttl_seconds' => 900,
'session_ttl_seconds' => 900,
```

These settings do not mean that the complete application workflow has been approved for public deployment.

## Koha REST API integration

The following endpoints have been tested individually against the configured Koha environment:

- `POST /api/v1/oauth/token` — obtain an OAuth2 access token
- `POST /api/v1/auth/password/validation` — validate the patron PIN
- `GET /api/v1/patrons/{patron_id}` — retrieve the patron record
- `PUT /api/v1/patrons/{patron_id}` — update the expiry date with required patron fields
- `GET /api/v1/patrons/{patron_id}` — verify the updated expiry date

The `PUT` method is used because `PATCH` was not supported in the tested environment.

**Important:** Updating `expiry_date` directly is not necessarily equivalent to Koha's complete membership-renewal workflow. Patron eligibility, restrictions, debt, renewal authorization, and library policy must be validated before public use.

## Email and OTP

The application sends a six-digit verification code to the patron's email address. The OTP is valid for 15 minutes and allows a maximum of five incorrect attempts.

The visible sender is configured as `Tritonia - Loans <loans@uwasa.fi>`. The technical envelope sender may differ.

The current Sendmail relay is `mailgw.uwasa.fi` on TCP port 25. TLS 1.3 and acceptance by the university SMTP gateway have been verified. SMTP gateway acceptance does not, by itself, confirm delivery to the recipient's mailbox.

## Demo mode

Set `mode` to `demo` in `src/config.php`.

- Library card: `1234567890`
- PIN: `12345`

Demo mode displays the OTP on screen and does not modify Koha records.

## Security

- Use HTTPS for all production traffic.
- Restrict application access during integration testing.
- Keep `src/config.php`, backups, Git metadata, and `storage/` inaccessible over HTTP.
- Ensure Apache is configured to honor the relevant `.htaccess` restrictions, or configure equivalent VirtualHost rules.
- Prefer moving configuration and storage outside the public DocumentRoot when practical.
- Ensure only the PHP service account has the necessary storage write access.
- Do not commit OAuth2 secrets, OTP data, or other sensitive information.

## Project structure

| Path | Purpose |
| --- | --- |
| `index.php` | HTTP entry point |
| `assets/style.css` | Styling and themes |
| `assets/app.js` | Client-side functionality |
| `assets/img/logo.svg` | Tritonia logo |
| `src/core.php` | Application and Koha integration logic |
| `src/config.php` | Configuration and secrets |
| `storage/` | Rate-limiting and application data |
| `.htaccess` | Apache access restrictions |
| `.gitignore` | Exclusions for local files and secrets |

The bundled `assets/img/logo.svg` is used in both light and dark modes.

## Release status — v1.0

Version 1.0 documents the implemented Koha and SMTP integrations and the existing multilingual renewal interface. The following acceptance checks remain before public release:

1. Test the complete browser workflow with an authorized Koha test patron.
2. Verify OTP delivery and confirmation.
3. Confirm renewal eligibility and library policy enforcement.
4. Verify date calculations and renewal limits.
5. Complete a security review and controlled acceptance test.

## License

This project is licensed under the MIT License.

Copyright (c) 2026 Jani Päivärinta

See the [LICENSE](LICENSE) file for details.
