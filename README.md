# Klaracase PHP client

The official PHP client for the [Klaracase REST API](https://klaracase.de/docs/api). Use it to search German
court decisions and statutes, read them, follow their citations, and check the citations in a legal text.

Klaracase covers German law only. It is a research tool. It gives no legal advice and does not replace a
lawyer's review. A `not_found` verdict means the citation was not found in the corpus; it is not a claim that the
citation is fabricated.

- Package: `klaracase/klaracase` (Packagist)
- PHP 8.2 or newer
- HTTP through any PSR-18 client and PSR-17 factories
- Plain PHP. No framework needed.
- Typed responses: every object, at every depth, is a `final readonly` class. PHPStan level max passes on your code.

This client is a REST client. It does not speak MCP. If your agent or app wants tools, connect its MCP client to
`https://klaracase.de/mcp` instead. The same token works there.

## Install

```bash
composer require klaracase/klaracase
```

The package needs a PSR-18 HTTP client and a PSR-17 factory. Composer refuses the install when your project has
none. Install these two if you have no preference:

```bash
composer require guzzlehttp/guzzle nyholm/psr7
```

Guzzle is the best fit: with it, the timeout settings take effect (see Request options). Other PSR-18 clients, such
as `symfony/http-client` with its `Psr18Client`, work too.

## Get a token

1. Sign in at <https://klaracase.de/settings/api-tokens> (your email must be verified).
2. Create a token. The token belongs to the organization you have open. Every call is billed to that organization.
3. Pick the abilities the token needs: `search`, `read`, `graph`, `datapackage`, `citation-check`. All five are ticked
   by default.
4. Pick an expiry. The default is 365 days.

Keep the token out of your code. Put it in the environment:

```bash
export KLARACASE_API_TOKEN=klc_your_token_here
```

## Quick start

```php
<?php

declare(strict_types=1);

use Klaracase\Klaracase;

require __DIR__ . '/vendor/autoload.php';

$client = Klaracase::fromEnv(); // reads KLARACASE_API_TOKEN; or Klaracase::client($token)

$result = $client->decisions->search('Eigenbedarf Kündigung', court: 'BGH', limit: 5);

foreach ($result->results ?? [] as $hit) {
    echo $hit->aktenzeichen, ' ', $hit->court, ' ', $hit->date, PHP_EOL;
}

echo 'Credits left: ', $result->credits?->credits_remaining, PHP_EOL;
```

`$hit` is a `DecisionHit`. Its properties carry the API's key names (`aktenzeichen`, `document_type`) and are
nullable, because a key may be missing. `tests/typing/consumer.php` in this repository is the same code, written for
PHPStan level max, and CI analyses it.

### Other calls

```php
use Klaracase\Enum\Branch;
use Klaracase\Enum\Direction;
use Klaracase\Enum\FacetField;
use Klaracase\Enum\Section;
use Klaracase\Enum\Statute;

// Statutes and decisions together
$client->search('Datenschutz Einwilligung', jurisdiction: 'EU', limit: 3);

// Filters take enums, or plain strings. Dates take 'YYYY-MM-DD' or any DateTimeInterface.
$client->decisions->search(
    'Untreue',
    branch: Branch::Ordentlich,
    citedStatutes: [Statute::StGB, 'StPO'],
    dateFrom: new DateTimeImmutable('2020-01-01'),
);

// One decision: a UUID, an Aktenzeichen or an ECLI
$decision = $client->decisions->get('VIII ZR 271/17', section: Section::Gruende, maxChars: 5000);
echo $decision->full_text;

// The citation graph around a decision
$graph = $client->decisions->citations('VIII ZR 271/17', direction: Direction::Incoming);

// One statute norm
$norm = $client->norms->get('§ 535 BGB');
$article = $client->norms->get('Art. 6 DSGVO', jurisdiction: 'EU');

// Up to 10 decisions in one bundle
$package = $client->datapackages->create(['VIII ZR 271/17', '1 StR 185/01'], fullText: false);

// What the corpus holds (free)
$client->corpus->coverage(branch: Branch::Ordentlich, top: 5);
$client->corpus->facets(FacetField::CourtName, prefix: 'BG');
```

An argument you leave out, or set to `null`, is not sent. Path values, such as an Aktenzeichen with a slash, are
percent-encoded for you.

The client refuses some wrong arguments before it sends a request. It throws `ValidationException` with the code
`client_validation` for: no ids or more than 10 ids, both or neither of `text` and `documentId`, a date that is not
`YYYY-MM-DD`, `dateFrom` after `dateTo`, and `rnFrom` after `rnTo`. It does not check enum membership or ranges the
server owns; the server answers those with a 422.

## Factory

`Klaracase::client($token)` covers the common case. For more settings use the factory. Every `with...` call returns
a changed copy.

```php
$client = Klaracase::factory()
    ->withToken($token)                  // default: the KLARACASE_API_TOKEN variable
    ->withBaseUrl('https://klaracase.de/api/v1')
    ->withTimeout(10.0)                  // Guzzle clients; see Request options
    ->withMaxRetries(1)
    ->withUserAgentSuffix('my-app/2.1')  // appended to the User-Agent
    ->withHeader('X-Team', 'research')   // sent with every request
    ->withLogger($psr3Logger)            // see Logging
    ->withHttpClient($psr18Client)       // also withRequestFactory(), withStreamFactory()
    ->withMiddleware($traceMiddleware)   // wraps every HTTP attempt
    ->make();
```

A middleware is `fn (RequestInterface $request, callable $next): ResponseInterface`. The first one added is the
outermost. `withDeprecationHandler()`, `withSleeper()` and `withClock()` complete the list. `new Klaracase(...)` with
the same named arguments still works.

## Request options

Every method takes `options: RequestOptions::with(...)` as its last argument. The same object works as a client
default through the factory (`withTimeout`, `withMaxRetries`, `withHeader`).

```php
use Klaracase\RequestOptions;

$client->citationChecks->create(
    text: $text,
    options: RequestOptions::with(
        timeout: 60.0,                         // seconds
        maxRetries: 0,                         // for this call only
        extraHeaders: ['X-Request-Id' => $id],
        extraQuery: ['trace' => '1'],
    ),
);
```

The per-call `timeout` reaches the HTTP client when it is a Guzzle client, which is what the SDK builds when Guzzle
is installed. PSR-18 has no hook for a timeout per request. With any other client the timeout of that client stays in
force, and you set it when you build that client.

## Pagination

`search()` takes `page`. To walk the pages, use a generator:

```php
foreach ($client->decisions->searchAll('Untreue', maxPages: 3, court: 'BGH') as $hit) {
    echo $hit->aktenzeichen, PHP_EOL;
}

foreach ($client->decisions->citationsAll('VIII ZR 271/17', maxPages: 2) as $edge) {
    echo $edge->reference_canonical, PHP_EOL;
}
```

Paging the same query within a credit period costs no extra credits. A changed query, filter or mode is a new charge.
Check `credits` on each response.

`maxPages` is required. It guards against a runaway walk. The client clamps it to 10 for search and 20 for
citations, the last pages the server serves. The walk stops when a page is empty or the totals say no page follows,
and stops fetching when you stop reading. A result also has `hasMore()` and `nextPage()` if you page by hand.

## Check the citations in a text

```php
use Klaracase\Enum\Verdict;

$run = $client->citationChecks->create(
    text: $text, // 50 to 20,000 characters of German legal text
    referenceDate: '2024-05-01', // optional; a DateTimeInterface works too
);

foreach ($run->findings ?? [] as $finding) {
    $cited = $finding->excerpt($text); // the span of your text this finding points at

    switch ($finding->verdictEnum()) {
        case Verdict::VerifiedCorpus:
        case Verdict::VerifiedExternal:
            // The citation exists. This does not prove the quote or the legal point is right.
            echo "found: $cited\n";
            break;
        case Verdict::NotFound:
            // Not in the corpus. This does not mean the citation is invented.
            echo "not found in the corpus: $cited\n";
            break;
        default:
            // out_of_coverage, ambiguous, unverifiable, discrepancy, or a verdict added later (Verdict::Unknown).
            echo "{$finding->verdict}: $cited\n";
    }
}

echo $run->count(Verdict::NotFound), " citations not found\n";
$byVerdict = $run->findingsByVerdict(); // array<string, list<Finding>>
```

`excerpt()` cuts by Unicode code points, because the offsets (`start_offset`, `end_offset`) count code points, not
bytes. The price depends on the length of the text. Checking the same text again in the same credit period costs nothing
more.

## Uploads and polling

```php
// A file on disk. The name and content type come from the path.
$run = $client->citationChecks->createFromFile('schriftsatz.pdf', referenceDate: '2024-05-01');

// The same in steps
$receipt = $client->citationCheckDocuments->upload('schriftsatz.pdf');
$ready = $client->citationCheckDocuments->waitUntilReady($receipt, pollInterval: 2.0, timeout: 120.0);
echo 'Price: ', $ready->quoted_credits, " credits\n";
$run = $client->citationChecks->create(documentId: $ready->id);

// Bytes you already hold
$receipt = $client->citationCheckDocuments->uploadBytes($bytes, 'schriftsatz.pdf');
```

A string given to `upload()` is a path, and the file is read. It is never sent as content. A path that does not
exist or cannot be read throws `ValidationException` (code `client_validation`) before any request. Use
`uploadBytes()` to send bytes.

Uploads are free. PDF, DOCX, Markdown and plain text are accepted, up to 25 MB. An unused upload expires after 48
hours. `waitUntilReady()` takes a receipt or an id. It throws `DocumentFailedException` when extraction fails, and
`DocumentTimeoutException` when the time runs out.

Send exactly one of `text` and `documentId`. The client refuses both or neither before it sends a request.

## Handle errors

Every API error is an exception that extends `Klaracase\Exception\KlaracaseException`. Both it and
`ConfigurationException` implement the marker interface `KlaracaseThrowable`, so one catch can cover both.

```php
use Klaracase\Exception\InsufficientCreditsException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\RateLimitException;
use Klaracase\Exception\ValidationException;

try {
    $run = $client->citationChecks->create(text: $text);
} catch (InsufficientCreditsException $e) {
    echo "This call costs {$e->cost} credits. You have {$e->balance}.\n";
} catch (RateLimitException $e) {
    echo 'Try again in ', $e->retryAfterSeconds ?? 0, " seconds.\n"; // null when the server named no wait
} catch (ValidationException $e) {
    print_r($e->errors); // field name => list of messages
} catch (KlaracaseException $e) {
    if ($e->isRetryable()) {
        // a rate limit (not the daily volume cap), a 503, or no answer at all
    }
    error_log(json_encode($e->toArray(), JSON_THROW_ON_ERROR)); // code, status, detail, retry_after_seconds
}
```

| Class | When |
|---|---|
| `AuthenticationException` | 401 |
| `ForbiddenException` | 403, with `requiredAbility` when a token ability is missing |
| `NotFoundException` | 404 |
| `ConflictException` | 409 |
| `ValidationException` | 422, with `errors`; also raised before any request when your arguments are wrong (`errorCode` is `client_validation`) |
| `InsufficientCreditsException` | 402, with `cost` and `balance` |
| `RateLimitException` | 429, with `retryAfterSeconds` (nullable) |
| `ServiceUnavailableException` | 503 |
| `ApiException` | any other status |
| `ResponseShapeException` | a success answer without `id`, `reference` or `field` |
| `TransportException` | no answer came: connection, DNS, TLS |
| `TimeoutException` | a `TransportException` for a timeout |
| `DocumentFailedException` | `waitUntilReady()` saw status `failed`; read `document` |
| `DocumentTimeoutException` | `waitUntilReady()` ran out of time; read `document` |
| `ConfigurationException` | no token, a bad base URL, or no HTTP client. It does not extend `KlaracaseException`. |

Fields on every `KlaracaseException`: `status`, `type`, `title`, `detail`, `errorCode` (the problem `code`),
`apiRelease`, `headers` (lower-case names, never the token), `body` (the whole problem) and `extra` (every member that
is not `type`, `title`, `status`, `detail` or `code`). Typed shortcuts read from the body, null when absent:
`candidates` (an ambiguous Aktenzeichen), `successor` (a superseded statute), and `unit`, `cap` and `resetsAt` (a
`volume_cap`). The message is the problem `detail`.

`isRetryable()` is true for a 429 that is not a `volume_cap`, for a 503, and for a failure to reach the API. It is
false for everything else. `retryAfterSeconds` stays nullable: write `$e->retryAfterSeconds ?? 0`.

If the server answers with something that is not a problem body, such as an HTML 502 from a proxy, you get an
`ApiException` with `errorCode` set to `unknown`. `detail` holds the first 1,000 characters of the body.

The token never appears in an exception message.

## Retries

The client retries on HTTP 429 and 503 up to `maxRetries` times (default 2). It waits `retry_after_seconds` from the
body, or the `Retry-After` header, or else an exponential backoff of 0.5 s, 1 s, 2 s with full jitter, capped at
8 s. Both statuses mean that nothing ran and nothing was charged, so a retry is safe for every call, including POST.

- A wait the server states above 30 seconds is not slept. The client throws `RateLimitException` at once, and you decide.
- `volume_cap` (a daily limit) is also thrown at once.
- A 402 or any other 4xx is never retried. A 500 or 502 is not retried.
- A network error with no answer is retried for GET only. A POST whose connection dropped is not repeated on its own.
  Repeating it by hand is free in the same period, because one charge covers each operation and subject once per
  credit period.

`$response->meta->attempts` and `->retriedFor` tell you how many requests a call took and how many seconds the
client waited.

Rate limits: 60 requests a minute per token. Three calls are limited to 10 a minute on top of that:
`datapackages->create`, `citationChecks->create` and `citationCheckDocuments->upload`.

## Logging

Give the factory a PSR-3 logger. The client writes one debug line per retry (method, path, status, attempt, wait) and
one warning when the API announces a deprecation. It never logs the token, a header value or a body.

```php
$client = Klaracase::factory()->withToken($token)->withLogger($logger)->make();
```

## Queue jobs

A queue worker has its own retry loop. Turn the client's retries off and hand the wait back to the queue:

```php
public function handle(): void
{
    $client = Klaracase::factory()->withToken($this->token)->withMaxRetries(0)->make();

    try {
        $client->citationChecks->create(text: $this->text);
    } catch (RateLimitException|ServiceUnavailableException|TransportException $e) {
        if ($e->isRetryable()) {
            $this->release($e instanceof RateLimitException ? ($e->retryAfterSeconds ?? 60) : 60);

            return;
        }
        throw $e;
    }
}
```

`release()` is the Laravel queue call; other queues have an equivalent.

## Credits

Most calls cost credits. These do not: `corpus->coverage`, `corpus->facets`, `citationChecks->get`,
`citationCheckDocuments->upload` and `->get`.

| Call | Credits |
|---|---|
| `decisions->search`, `search`, `decisions->get`, `norms->get` | 1 |
| `decisions->citations` | 2 |
| `datapackages->create` | 5 |
| `citationChecks->create` | 2 for up to 10,000 characters, 3 up to 15,000, 4 up to 20,000 |

Every served answer has `credits` with `metered`, `cost` and `credits_remaining`. `cost` and `credits_remaining` are
`null` when the account is not metered.

Credits renew per credit period: the UTC calendar month for free organizations, the paid billing period for subscribers. Unused credits do not roll over.

## Typed responses and enums

Each call returns a `final readonly` class. Nested objects are classes too (`Finding`, `CitationEdge`, `Count`), and
lists are plain `list<T>`. Property names are the API's key names, unchanged (`$result->total_available->value`,
`$run->verdict_counts`).

```php
$result->meta->status;          // HTTP status
$result->meta->apiRelease;      // X-Klaracase-Api-Release, or null
$result->meta->requestDuration; // seconds
$result->meta->headers;         // response headers, lower-case names
$result->credits?->cost;
$result->extra;                 // keys this client version does not model
$result->toArray();             // the body, with the keys in model order and unknown keys last
json_encode($result);           // the same body as JSON; {} stays {}
```

The API only adds keys. A new key lands in `extra` at its depth and never breaks your code. If a key is missing, or
holds an unexpected JSON type, its property is `null`; the raw value of a wrong-typed key stays in `extra`, and
`nulls` lists the keys that came as an explicit `null`. Identity keys (`id`, `reference`, `field`) are the exception:
a success answer without them throws `ResponseShapeException`.

Enum values stay raw strings on the property. Each has an accessor that returns an enum:
`$finding->verdictEnum()`, `$run->statusEnum()`, `$result->modeEnum()`. For values the server may extend, such as
verdicts and statuses, the enum has an `Unknown` case, so a new server value never throws. For the filters that you
also send (`Mode`, `Branch`, `DocumentType`, `Direction`), an unknown value gives `null`.

`$response['key']` still reads the body like an array. It is kept for old code and marked deprecated.

## Testing

`Klaracase\Testing\FakeClient` answers from a queue and records what your code sent. It implements
`Klaracase\Client`, an interface with the six resources as methods (`$client->decisions()->search(...)`), so type-hint
`Client` where you want to swap it.

```php
use Klaracase\Testing\FakeClient;
use Klaracase\Testing\Fixtures;
use Klaracase\Testing\RecordedRequest;

$client = new FakeClient();
$client->queue(Fixtures::decisionSearch());
$client->queue(Fixtures::problem(429, 'rate_limited', ['retry_after_seconds' => 3]));

$result = $client->decisions->search('Eigenbedarf', court: 'BGH');
// the next call throws RateLimitException

$client->assertSentCount(1);
$client->assertSent(fn (RecordedRequest $r) => $r->path === '/decisions/search' && $r->query['court'] === 'BGH');
$client->assertNothingSent();
```

You can queue a response object, a problem array or an exception. `Fixtures` holds the example bodies of the API
documentation as response objects. The sleeper and the clock of a `FakeClient` do not wait, so `waitUntilReady()` and
`createFromFile()` run in no time. `Testing\RecordingSleeper` and `Testing\FakeClock` are public if you build a
`Klaracase` yourself with `withSleeper()` and `withClock()`. Mocks of the `Client` interface work too.

To test the real client against HTTP, use a PSR-18 mock such as `php-http/mock-client`, as this repository does.

```bash
composer install
composer check     # Pint, PHPStan level max, PHPUnit
```

A smoke script runs the two free endpoints against the live API. It needs a token and is not part of the tests:

```bash
KLARACASE_API_TOKEN=klc_your_token_here composer smoke
```

## Deprecations

When an endpoint is deprecated, the API announces it with `Deprecation` and `Sunset` headers. The client calls the
deprecation hook once per process. The default raises an `E_USER_DEPRECATED` notice.

```php
use Klaracase\Http\DeprecationNotice;

$client = Klaracase::factory()
    ->withToken($token)
    ->withDeprecationHandler(function (DeprecationNotice $notice): void {
        error_log($notice->message()); // $notice->deprecation, ->sunset, ->link hold the raw header values
    })
    ->make();
```

## Laravel

The client is plain PHP and has no Laravel dependency, and there is no separate Laravel package. Bind it once in a
service provider. Put the token in `config/services.php`:

```php
'klaracase' => ['token' => env('KLARACASE_API_TOKEN')],
```

```php
use Klaracase\Client;
use Klaracase\Klaracase;

public function register(): void
{
    $this->app->singleton(Klaracase::class, fn () => Klaracase::factory()
        ->withToken((string) config('services.klaracase.token'))
        ->withUserAgentSuffix('my-app/1.0')
        ->withLogger($this->app->make('log'))
        ->make());
    $this->app->alias(Klaracase::class, Client::class);
}
```

Then type-hint `Client` in a controller, job or command. In tests, `$this->app->instance(Client::class, new FakeClient())`.
Symfony, Slim and other containers bind it the same way.

## Requirements

- PHP 8.2 to 8.5, with `ext-json` and `ext-mbstring`
- A PSR-18 client and a PSR-17 factory (`psr/http-client-implementation`, `psr/http-factory-implementation`)
- `psr/log` 1, 2 or 3

The `User-Agent` is `klaracase-php/0.1.0 PHP/<version>`.

## Versioning

This SDK is 0.x and tracks API release 0.95.0. In 0.x a minor version may change the public surface; a patch version
only fixes bugs. After 1.0 the package follows semantic versioning. Every change is listed in
[CHANGELOG.md](CHANGELOG.md). All Klaracase SDKs bump together when the API contract changes.

## Stability and privacy

The SDK sends no telemetry. The only thing it adds to a request is the User-Agent header
`klaracase-php/<version> PHP/<version>`, for example `klaracase-php/0.1.0 PHP/8.4.1`, plus what you add yourself.

Klaracase is not affiliated with any court or with the federal government.

## Links

- REST reference: <https://klaracase.de/docs/api>
- MCP server: <https://klaracase.de/mcp> (tools guide: <https://klaracase.de/docs/mcp>)
- Support: support@klaracase.de
- License: MIT. Copyright (c) 2026 SPARQ VENTURES UG.
