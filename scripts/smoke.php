<?php

declare(strict_types=1);

/**
 * Smoke test against the live API. Free endpoints only (corpus coverage and corpus facets), so it costs no credits.
 * Needs KLARACASE_API_TOKEN. It is not part of CI and not part of the test suite.
 *
 *     KLARACASE_API_TOKEN=<token> composer smoke
 */

use Klaracase\Exception\ConfigurationException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Klaracase;

require __DIR__ . '/../vendor/autoload.php';

try {
    $client = Klaracase::fromEnv(userAgentSuffix: 'klaracase-php-smoke');

    $coverage = $client->corpus->coverage(top: 3);
    printf(
        "coverage: %s decisions, %s courts returned, api release %s, %.0f ms\n",
        (string) ($coverage->total_decisions['value'] ?? '?'),
        (string) ($coverage->courts_returned ?? '?'),
        $coverage->meta->apiRelease ?? 'unknown',
        $coverage->meta->requestDuration * 1000,
    );

    $facets = $client->corpus->facets('court_name', prefix: 'BG');
    printf("facets: %s values for court_name starting with BG\n", (string) ($facets->total_values ?? '?'));
} catch (ConfigurationException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(2);
} catch (KlaracaseException $e) {
    fwrite(STDERR, sprintf("%s (status %s, code %s)\n", $e->getMessage(), $e->status ?? 'none', $e->errorCode ?? 'none'));
    exit(1);
}

echo "smoke ok\n";
