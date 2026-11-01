<?php

declare(strict_types=1);

/**
 * The README quick start, written the way an application at PHPStan level max would write it.
 * It is never run. `composer analyse` checks that it type-checks, which proves the typed responses work for a
 * consumer: `$hit->court`, `$finding->verdictEnum()` and the generators need no cast and no `@var`.
 */

namespace Klaracase\Tests\Typing;

use Klaracase\Client;
use Klaracase\Enum\Branch;
use Klaracase\Enum\Verdict;
use Klaracase\Exception\InsufficientCreditsException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\KlaracaseThrowable;
use Klaracase\Exception\RateLimitException;
use Klaracase\Exception\TokenLimitReachedException;
use Klaracase\Klaracase;
use Klaracase\RequestOptions;
use Klaracase\Response\Finding;

/**
 * @return list<string> The courts of the first page of hits.
 */
function courtsOfTheFirstPage(Client $client): array
{
    $courts = [];
    $result = $client->decisions()->search('Eigenbedarf Kündigung', branch: Branch::Ordentlich, limit: 5);
    foreach ($result->results ?? [] as $hit) {
        if ($hit->court !== null) {
            $courts[] = $hit->court;
        }
        $aktenzeichen = $hit->aktenzeichen ?? 'unknown';
        $courts[] = strtoupper($aktenzeichen);
    }

    return $courts;
}

/**
 * @return list<Finding> The citations the corpus does not know.
 */
function unknownCitations(Klaracase $client, string $brief): array
{
    $run = $client->citationChecks->create(text: $brief, options: RequestOptions::with(timeout: 20.0));
    if (! $run->isComplete()) {
        return [];
    }
    $byVerdict = $run->findingsByVerdict();

    return $byVerdict[Verdict::NotFound->value] ?? [];
}

function quotedSpan(Finding $finding, string $brief): string
{
    return $finding->excerpt($brief);
}

function firstHitOfAnyPage(Klaracase $client): ?string
{
    foreach ($client->decisions->searchAll('Untreue', maxPages: 2) as $hit) {
        return $hit->id;
    }

    return null;
}

function remainingCredits(Klaracase $client): ?int
{
    return $client->corpus->coverage()->credits?->credits_remaining;
}

/**
 * @return int Seconds to wait before the caller tries again, 0 when retrying cannot help.
 */
function secondsToWait(Klaracase $client): int
{
    try {
        $client->norms->get('§ 535 BGB');
    } catch (RateLimitException $e) {
        return $e->isRetryable() ? ($e->retryAfterSeconds ?? 60) : 0;
    } catch (TokenLimitReachedException) {
        return 0; // a key limit: stop and tell your user, a retry cannot help
    } catch (InsufficientCreditsException $e) {
        return ($e->balance ?? 0) > 0 ? 1 : 0;
    } catch (KlaracaseException $e) {
        return $e->isRetryable() ? 1 : 0;
    }

    return 0;
}

/**
 * @return int|null The API budget left in euro cents, null when the organization has none.
 */
function budgetInCents(Klaracase $client): ?int
{
    return $client->decisions->search('Untreue')->credits?->api_budget?->cents;
}

function describe(KlaracaseThrowable $e): string
{
    return $e->getMessage();
}
