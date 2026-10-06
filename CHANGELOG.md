# Changelog

All notable changes to `FeexpayPhp` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 2.1.0 - 2026-10-06

### Added
- `getLastError()`: returns the API error message and full response when `paiementLocal()` or `paiementCard()` fails
- `paiementCard()` result now also contains a `payment_url` key (same value as `url`)

### Changed
- All requests now target the Feexpay API v2 (`https://api-v2.feexpay.me`); the v1 host `https://api.feexpay.me` is no longer available
- Requests are authenticated with the `Authorization: Bearer <token>` header and sent as JSON (the token is no longer sent in the request body)
- `getPaiementStatus()` uses the v2 route `/api/transactions/public/single/status/{reference}`
- `paiementCard()` uses the v2 route `/api/transactions/public/requesttopay/init`
- **Breaking:** new `paiementCard()` signature matching the v2 API body, all arguments required: `paiementCard(amount, currency, firstName, lastName, email, phoneNumber, city, zip, country)` (`typeCard`, `address`, `district`, `callback_info` and `custom_id` are removed). It returns `null` on failure (reason in `getLastError()`) instead of echoing an error
- `init()` loads the JavaScript SDK from the v2 host and passes the configured mode instead of always `LIVE`
- `requestToPayWeb()` and `paiementCard()` no longer use a 4 second timeout
- `requestToPayWeb()` returns `false` on an invalid or `FAILED` response; `cancel_url` / `return_url` default to `https://feexpay.me/en`
- `getPaiementStatus()` returns `false` when the reference is empty
- `composer.json`: declares the requirements (PHP >= 7.1, `ext-curl`, `ext-json`)
- `.gitignore` rewritten; the `.idea` folder is no longer versioned

### Deprecated
- Nothing

### Fixed
- `paiementLocal()` no longer hides API errors: it returns `null` and exposes the reason through `getLastError()` instead of emitting a warning
- `paiementLocal()` normalizes the phone number (removes spaces, dashes, dots, parentheses, leading `+` / `00`)
- Fatal error "Cannot redeclare curl_post()" when calling `paiementLocal()`, `requestToPayWeb()` or `paiementCard()` more than once in the same request
- Removed the unused shop lookup in `paiementLocal()` (one less HTTP call per payment)
- Declared class properties (dynamic properties are deprecated since PHP 8.2)
- README: correct `paiementLocal()` signature (7 required arguments) and error handling example
- README: correct `paiementCard()` examples (12 required arguments), document `requestToPayWeb()`, the `getPaiementStatus()` result and the requirements

### Removed
- Nothing

### Security
- Nothing
