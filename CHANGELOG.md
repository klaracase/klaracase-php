# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- First version of the Klaracase PHP client (0.1.0), for the Klaracase REST API v1 (API release 0.95.0).
- All twelve operations: `decisions->search`, `search`, `decisions->get`, `decisions->citations`,
  `norms->get`, `datapackages->create`, `citationChecks->create` and `->get`,
  `citationCheckDocuments->upload`, `->get` and `->waitUntilReady`, `corpus->coverage` and `->facets`.
- Typed response classes that keep unknown keys in `$extra` and the whole body in `toArray()`.
- One exception class per error status, plus transport, timeout, document and configuration errors.
- Retries on HTTP 429 and 503 with backoff, `Retry-After` support and an injectable sleeper and clock.
- A hook for `Deprecation` and `Sunset` headers.
- PSR-18 and PSR-17 transport, found with `php-http/discovery`.
