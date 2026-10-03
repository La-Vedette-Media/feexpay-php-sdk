# Changelog

All notable changes to `FeexpayPhp` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added
- `getLastError()`: returns the API error message and full response when `paiementLocal()` fails

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
