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
- One exception class per error status, plus transport, timeout, document, response shape and configuration errors.
- Retries on HTTP 429 and 503 with backoff, `Retry-After` support and an injectable sleeper and clock.
- A hook for `Deprecation` and `Sunset` headers.
- PSR-18 and PSR-17 transport, found with `php-http/discovery`.

#### Developer experience round

- Typed responses at every depth: 49 `final readonly` classes (`Finding`, `CitationEdge`, `Count` and so on) and 17
  enums (`Verdict`, `RunStatus`, `Mode`, `Branch`, `Section`, `Statute` and others). Enum accessors such as
  `Finding::verdictEnum()` keep the raw string on the property. Server-extensible enums have an `Unknown` case.
- A reader that never throws on an unexpected key: a new key or a wrong-typed value lands in `extra` at its depth,
  an explicit `null` is recorded in `nulls`, and `toArray()` and `json_encode()` re-emit the body, including `{}`.
- Helpers: `CitationCheck::isComplete()`, `count()` and `findingsByVerdict()`, `Finding::excerpt()` (cuts by code
  point), `DecisionSearchResult::hasMore()` and `nextPage()`.
- Drift guards: the golden example bodies, the conformance bodies and the first JSON block of each contract section
  must round-trip through the models, so a server change that the models miss fails a test.
- `RequestOptions` (per-call timeout, retries, headers and query) on every method, and as client defaults.
- Typed parameters: enums or strings for filters, `DateTimeInterface` or `YYYY-MM-DD` for dates. Wrong arguments the
  client can see (id count, `text` against `documentId`, a bad or inverted date range) throw `ValidationException`
  with the code `client_validation` before any request.
- `Klaracase::client($token)`, `Klaracase::factory()` and an immutable `Factory`, with `withLogger`, `withMiddleware`,
  `withHeader`, `withUserAgentSuffix` and more. `interface Client` is implemented by `Klaracase` and
  `Testing\FakeClient`.
- A `Transport` interface with an `HttpTransport` for PSR-18, a per-request timeout for Guzzle, and timeout detection
  along the exception chain.
- `ResponseMeta` carries response `headers`, `attempts` and `retriedFor`.
- Exceptions carry `headers`, `body`, typed extras (`candidates`, `successor`, `unit`, `cap`, `resetsAt`),
  `isRetryable()` and `toArray()`. The marker interface `KlaracaseThrowable` covers `KlaracaseException` and
  `ConfigurationException`.
- PSR-3 logging of retries and deprecations, with no token, header value or body.
- `decisions->searchAll()` and `decisions->citationsAll()` generators with a required, clamped `maxPages` (10 for
  search, 20 for citations).
- `citationCheckDocuments->upload()` reads a path or an `SplFileInfo`; `uploadBytes()` sends bytes;
  `waitUntilReady()` takes a receipt or an id; `citationChecks->createFromFile()` does upload, wait and check.
- `Testing\FakeClient`, `Testing\Fixtures`, `Testing\RecordingSleeper` and `Testing\FakeClock` for tests of your own
  code.
- Docblocks with `@throws` on every public method, a PHPStan-clean quick start in `tests/typing/consumer.php`
  analysed in CI, an architecture test, and README sections for the factory, request options, pagination, uploads,
  errors, logging, queue jobs, testing and Laravel.
- Tooling: `phpstan-strict-rules`, `phpstan-phpunit` and `phpstan-deprecation-rules`; PHPUnit in random order with
  strictness flags; `.gitattributes` with `export-ignore`; CI runs `composer validate --strict`, `composer audit`, a
  `--prefer-lowest` job and a concurrency group; a `composer check` script.

### Changed

- `composer.json` requires `psr/log` and a PSR-18 and PSR-17 implementation. The `conflict` entry is gone.
- Response objects no longer expose nested rows as plain arrays. Use the typed properties. `$response['key']` still
  works through `ArrayAccess` and is marked deprecated.
- A string given to `upload()` is a path and is read as a file. It was content before. Use `uploadBytes()` for bytes.
  Stream resources and PSR-7 streams are no longer accepted.
- Paging docs corrected: paging the same query within a credit period costs no extra credits, and a changed query,
  filter or mode is a new charge. `maxPages` stays required as a guard against a runaway walk.

### Deferred

- `DateTimeImmutable` accessors on date fields.
- A separate `klaracase/laravel` package. The README shows the service provider binding instead.

