# Changelog

All notable changes to `FeexpayPhp` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added
- `getLastError()`: returns the API error message and full response when `paiementLocal()` fails

### Changed
- All requests now target the Feexpay API v2 (`https://api-v2.feexpay.me`); the v1 host `https://api.feexpay.me` is no longer available
- Requests are authenticated with the `Authorization: Bearer <token>` header and sent as JSON (the token is no longer sent in the request body)
- `getPaiementStatus()` uses the v2 route `/api/transactions/public/single/status/{reference}`
- `paiementCard()` uses the v2 route `/api/transactions/public/requesttopay/init`
- `init()` loads the JavaScript SDK from the v2 host and passes the configured mode instead of always `LIVE`
- `requestToPayWeb()` and `paiementCard()` no longer use a 4 second timeout
- `requestToPayWeb()` returns `false` on an invalid or `FAILED` response; `cancel_url` / `return_url` default to `https://feexpay.me/en`
- `getPaiementStatus()` returns `false` when the reference is empty

### Deprecated
- Nothing

### Fixed
- `paiementLocal()` no longer hides API errors: it returns `null` and exposes the reason through `getLastError()` instead of emitting a warning
- `paiementLocal()` normalizes the phone number (removes spaces, dashes, dots, parentheses, leading `+` / `00`)
- Fatal error "Cannot redeclare curl_post()" when calling `paiementLocal()`, `requestToPayWeb()` or `paiementCard()` more than once in the same request
- Removed the unused shop lookup in `paiementLocal()` (one less HTTP call per payment)
- Declared class properties (dynamic properties are deprecated since PHP 8.2)
- README: correct `paiementLocal()` signature (7 required arguments) and error handling example

### Removed
- Nothing

### Security
- Nothing
