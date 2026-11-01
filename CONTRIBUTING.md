# Contributing

Thank you for helping. This is a small client. We keep it small.

## Set up

```bash
composer install
```

You need PHP 8.2 or newer and Composer.

## Checks

Run all three before you open a pull request:

```bash
composer test      # PHPUnit, no network
composer analyse   # PHPStan, level max
composer lint      # Pint, style check; run `composer fix` to fix style
```

## Rules

- Every PHP file starts with `declare(strict_types=1);`.
- Keep runtime dependencies at the PSR interfaces and `php-http/discovery`. Ask first before you add one.
- Response classes keep the API's key names. Do not rename, reorder or clean any value from the API.
- A change in behavior needs a test. Tests never touch the network.
- The test cases in `tests/fixtures/` come from the shared Klaracase SDK conformance pack. Do not edit them here.
- Do not put a real token in any file. Use `klc_your_token_here`.

## Report a problem

Open an issue. For a security problem, read [SECURITY.md](SECURITY.md) instead.
