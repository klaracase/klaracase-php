# Klaracase PHP client

The official PHP client for the [Klaracase REST API](https://klaracase.de/docs/api). Use it to search German
court decisions and statutes, read them, follow their citations, and check the citations in a legal text.

Klaracase covers German law only. It is a research tool. It gives no legal advice and does not replace a
lawyer's review. A `not_found` verdict means the citation was not found in the corpus; it is not a claim that the
citation is fabricated.

- Package: `klaracase/klaracase` (Packagist)
- PHP 8.2 or newer
- HTTP through any PSR-18 client and PSR-17 factories, found with `php-http/discovery`
- Plain PHP. No framework needed.

This client is a REST client. It does not speak MCP. If your agent or app wants tools, connect its MCP client to
`https://klaracase.de/mcp` instead. The same token works there.

## Install

```bash
composer require klaracase/klaracase
```

The client needs a PSR-18 HTTP client and a PSR-17 factory. If your project has none, install these two:

```bash
composer require guzzlehttp/guzzle nyholm/psr7
```

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

$client = Klaracase::fromEnv(); // reads KLARACASE_API_TOKEN

$result = $client->decisions->search('Eigenbedarf Kündigung', court: 'BGH', limit: 5);

foreach ($result->results ?? [] as $hit) {
    echo $hit['aktenzeichen'], ' ', $hit['court'], ' ', $hit['date'], PHP_EOL;
}

echo 'Credits left: ', $result->credits?->credits_remaining, PHP_EOL;
```

You can also pass the token yourself: `new Klaracase(token: 'klc_your_token_here')`.

### Other calls

```php
// Statutes and decisions together
$client->search('Datenschutz Einwilligung', jurisdiction: 'EU', limit: 3);

// One decision: a UUID, an Aktenzeichen or an ECLI
$decision = $client->decisions->get('VIII ZR 271/17', section: 'Gründe', maxChars: 5000);
echo $decision->full_text;

// The citation graph around a decision
$graph = $client->decisions->citations('VIII ZR 271/17', direction: 'incoming');

// One statute norm
$norm = $client->norms->get('§ 535 BGB');
$article = $client->norms->get('Art. 6 DSGVO', jurisdiction: 'EU');

// Up to 10 decisions in one bundle
$package = $client->datapackages->create(['VIII ZR 271/17', '1 StR 185/01'], fullText: false);

// What the corpus holds (free)
$client->corpus->coverage(branch: 'ordentlich', top: 5);
$client->corpus->facets('court_name', prefix: 'BG');
```

Array and boolean filters are typed. `citedStatutes` takes a list such as `['StGB', 'StPO']`.
An argument you leave out, or set to `null`, is not sent.

Path values, such as an Aktenzeichen with a slash, are percent-encoded for you.

## Check the citations in a text

```php
$run = $client->citationChecks->create(
    text: $text, // 50 to 20,000 characters of German legal text
    referenceDate: '2024-05-01', // optional
);

foreach ($run->findings ?? [] as $finding) {
    $verdict = $finding['verdict'] ?? 'unknown';
    $cited = $finding['raw_text'] ?? '';

    switch ($verdict) {
        case 'verified_corpus':
        case 'verified_external':
            // The citation exists. This does not prove the quote or the legal point is right.
            echo "found: $cited\n";
            break;
        case 'not_found':
            // Not in the corpus. This does not mean the citation is invented.
            echo "not found in the corpus: $cited\n";
            break;
        default:
            // out_of_coverage, ambiguous, unverifiable, discrepancy, or a verdict added later.
            echo "$verdict: $cited\n";
    }
}

print_r($run->verdict_counts);
```

The verdicts are `verified_corpus`, `verified_external`, `not_found`, `out_of_coverage`, `ambiguous`,
`unverifiable` and `discrepancy`. Values are plain strings. The client never throws on a value it does not know.

Offsets in each finding (`start_offset`, `end_offset`) are Unicode codepoint offsets into the text you sent.

The price depends on the length of the text. Checking the same text again in the same month costs nothing more.

### Check an uploaded file

```php
$receipt = $client->citationCheckDocuments->upload(
    file_get_contents('schriftsatz.pdf'), // the file bytes, a stream resource, or a PSR-7 stream
    'schriftsatz.pdf',
    'application/pdf',
);

$ready = $client->citationCheckDocuments->waitUntilReady($receipt->id, pollInterval: 2.0, timeout: 120.0);
echo 'Price: ', $ready->quoted_credits, " credits\n";

$run = $client->citationChecks->create(documentId: $ready->id);
```

Uploads are free. PDF, DOCX, Markdown and plain text are accepted, up to 25 MB. An unused upload expires after
48 hours. `waitUntilReady()` throws `DocumentFailedException` when extraction fails, and `DocumentTimeoutException`
when the time runs out.

Send exactly one of `text` and `documentId`. The client refuses both or neither before it sends a request.

## Handle errors

Every API error is an exception that extends `Klaracase\Exception\KlaracaseException`.

```php
use Klaracase\Exception\InsufficientCreditsException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\RateLimitException;
use Klaracase\Exception\TransportException;
use Klaracase\Exception\ValidationException;

try {
    $run = $client->citationChecks->create(text: $text);
} catch (InsufficientCreditsException $e) {
    echo "This call costs {$e->cost} credits. You have {$e->balance}.\n";
} catch (RateLimitException $e) {
    echo "Slow down. Try again in {$e->retryAfterSeconds} seconds.\n";
} catch (ValidationException $e) {
    print_r($e->errors); // field name => list of messages
} catch (TransportException $e) {
    echo "No answer from Klaracase: {$e->getMessage()}\n";
} catch (KlaracaseException $e) {
    echo "{$e->status} {$e->errorCode}: {$e->getMessage()}\n";
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
| `RateLimitException` | 429, with `retryAfterSeconds` |
| `ServiceUnavailableException` | 503 |
| `ApiException` | any other status |
| `TransportException` | no answer came: connection, DNS, TLS |
| `TimeoutException` | a `TransportException` for a timeout |
| `DocumentFailedException` | `waitUntilReady()` saw status `failed`; read `document` |
| `DocumentTimeoutException` | `waitUntilReady()` ran out of time; read `document` |
| `ConfigurationException` | no token, a bad base URL, or no HTTP client. It does not extend `KlaracaseException`. |

Fields on every `KlaracaseException`: `status`, `type`, `title`, `detail`, `errorCode` (the problem `code`),
`apiRelease` and `extra`. `extra` holds every other problem member, such as `unit`, `cap` and `resets_at` on a
`volume_cap`, or `candidates` on an ambiguous Aktenzeichen. The message is the problem `detail`.

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
  calendar month.

```php
$client = new Klaracase(token: 'klc_your_token_here', maxRetries: 0); // no retries
```

Rate limits: 60 requests a minute per token. Three calls are limited to 10 a minute on top of that:
`datapackages->create`, `citationChecks->create` and `citationCheckDocuments->upload`.

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

## Responses

Each call returns a final readonly class with typed properties. Property names are the API's key names, unchanged
(`$result->total_available`, `$run->verdict_counts`). Nested rows are plain arrays.

```php
$result->meta->status;          // HTTP status
$result->meta->apiRelease;      // X-Klaracase-Api-Release, or null
$result->meta->requestDuration; // seconds
$result->credits?->cost;
$result->extra;                 // top-level keys this client version does not model
$result->toArray();             // the decoded body exactly as the API sent it
$result['results'];             // the same body through ArrayAccess (read only)
```

The API only adds keys. A new key lands in `extra` and never breaks your code. If a key is missing or holds an
unexpected JSON type, its typed property is `null`; `toArray()` still has the raw value.

## Deprecations

When an endpoint is deprecated, the API announces it with `Deprecation` and `Sunset` headers. The client calls the
`onDeprecation` hook once per process. The default raises an `E_USER_DEPRECATED` notice.

```php
use Klaracase\Http\DeprecationNotice;

$client = new Klaracase(
    token: 'klc_your_token_here',
    onDeprecation: function (DeprecationNotice $notice): void {
        error_log($notice->message()); // $notice->deprecation, ->sunset, ->link hold the raw header values
    },
);
```

## Options

| Argument | Default | Meaning |
|---|---|---|
| `token` | `KLARACASE_API_TOKEN` | A personal access token |
| `baseUrl` | `https://klaracase.de/api/v1` | API root |
| `timeout` | `30.0` | Seconds. See the note below. |
| `maxRetries` | `2` | See Retries |
| `userAgentSuffix` | `null` | Appended to the `User-Agent`, after a space. Put your app name here. |
| `httpClient` | discovered | Any PSR-18 client |
| `requestFactory`, `streamFactory` | discovered | Any PSR-17 factories |
| `onDeprecation` | notice | See Deprecations |
| `sleeper`, `clock` | real | Interfaces `Klaracase\Support\Sleeper` and `Clock`. Replace them in tests. |

The `User-Agent` is `klaracase-php/0.1.0 PHP/<version>`.

PSR-18 has no timeout setting. The `timeout` argument applies when the client builds its own Guzzle client. If you
pass your own `httpClient`, set its timeouts yourself.

## Laravel and other frameworks

The client is plain PHP and has no Laravel dependency. Bind it once in a service provider:

```php
use Klaracase\Klaracase;

public function register(): void
{
    $this->app->singleton(Klaracase::class, fn () => new Klaracase(
        token: config('services.klaracase.token'),
        userAgentSuffix: 'my-app/1.0',
    ));
}
```

Add the token to `config/services.php` and read it from your `.env` file:

```php
'klaracase' => ['token' => env('KLARACASE_API_TOKEN')],
```

Then type-hint `Klaracase` in a controller, job or command. Pass Laravel's own HTTP client stack through
`httpClient` only if you need it; any PSR-18 client works. Symfony, Slim and other containers bind it the same way.

## Test your own code

The client takes any PSR-18 client, so you can test with a mock such as `php-http/mock-client`. Pass a recording
`Sleeper` to skip the waits between retries. This repository's own tests do that, with no network.

```bash
composer install
composer test      # PHPUnit with the shared conformance cases
composer analyse   # PHPStan, level max
composer lint      # Pint style check
```

A smoke script runs the two free endpoints against the live API. It needs a token and is not part of the tests:

```bash
KLARACASE_API_TOKEN=klc_your_token_here composer smoke
```

## Stability and privacy

This SDK is 0.x and tracks API release 0.95.0. Minor versions may change the public surface until 1.0. Every change is
listed in [CHANGELOG.md](CHANGELOG.md). All Klaracase SDKs bump together when the API contract changes.

The SDK sends no telemetry. The only thing it adds to a request is the User-Agent header
`klaracase-php/<version> PHP/<version>`, for example `klaracase-php/0.1.0 PHP/8.4.1`.

Klaracase is not affiliated with any court or with the federal government.

## Links

- REST reference: <https://klaracase.de/docs/api>
- MCP server: <https://klaracase.de/mcp> (tools guide: <https://klaracase.de/docs/mcp>)
- Support: support@klaracase.de
- License: MIT. Copyright (c) 2026 SPARQ VENTURES UG.
