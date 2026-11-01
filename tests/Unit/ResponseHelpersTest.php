<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use Klaracase\Enum\Verdict;
use Klaracase\Response\CitationCheck;
use Klaracase\Response\DecisionSearchResult;
use Klaracase\Response\Finding;
use Klaracase\Tests\Support\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseHelpersTest extends TestCase
{
    private function loadRun(): CitationCheck
    {
        return CitationCheck::fromArray(Json::decodeFile(__DIR__ . '/../fixtures/examples/citation-checks.show.json'));
    }

    public function testExcerptCutsByCodePointsNotBytes(): void
    {
        $finding = Finding::fromArray(['id' => 'f1', 'raw_text' => 'Art. 6 DSGVO', 'start_offset' => 3, 'end_offset' => 15]);

        $this->assertSame('Art. 6 DSGVO', $finding->excerpt('äö Art. 6 DSGVO folgt'));
        $this->assertSame(' Art. 6 DSGV', $finding->excerpt('äöü Art. 6 DSGVO folgt'));
    }

    public function testExcerptFallsBackToTheRawTextWithoutOffsets(): void
    {
        $this->assertSame('§ 535 BGB', (new Finding(raw_text: '§ 535 BGB'))->excerpt('anything'));
        $this->assertSame('', (new Finding())->excerpt('anything'));
    }

    public function testFindingsAreGroupedByVerdict(): void
    {
        $run = CitationCheck::fromArray([
            'id' => 'r',
            'findings' => [
                ['id' => 'a', 'verdict' => 'verified_corpus'],
                ['id' => 'b', 'verdict' => 'not_found'],
                ['id' => 'c', 'verdict' => 'verified_corpus'],
                ['id' => 'd'],
            ],
        ]);

        $groups = $run->findingsByVerdict();

        $this->assertSame(['verified_corpus', 'not_found'], array_keys($groups));
        $this->assertCount(2, $groups['verified_corpus']);
        $this->assertSame('b', $groups['not_found'][0]->id);
    }

    public function testTheRunKnowsItIsCompleteAndCountsVerdicts(): void
    {
        $run = $this->loadRun();

        $this->assertTrue($run->isComplete());
        $this->assertSame(1, $run->count(Verdict::VerifiedCorpus));
        $this->assertSame(1, $run->count('not_found'));
        $this->assertSame(0, $run->count(Verdict::Ambiguous));
    }

    public function testARunInProgressIsNotComplete(): void
    {
        $this->assertFalse(CitationCheck::fromArray(['id' => 'r', 'status' => 'checking_external'])->isComplete());
    }

    /**
     * @return array<string, array{array<string, mixed>, bool, int|null}>
     */
    public static function pages(): array
    {
        $results = [['id' => 'd']];

        return [
            'first page of three' => [['results' => $results, 'page' => 1, 'limit' => 20, 'total_available' => ['value' => 42, 'is_floor' => false]], true, 2],
            'last page' => [['results' => $results, 'page' => 3, 'limit' => 20, 'total_available' => ['value' => 42, 'is_floor' => false]], false, null],
            'exact fit' => [['results' => $results, 'page' => 2, 'limit' => 20, 'total_available' => ['value' => 40, 'is_floor' => false]], false, null],
            'floor with results' => [['results' => $results, 'page' => 1, 'limit' => 20, 'total_available' => ['value' => 100, 'is_floor' => true]], true, 2],
            'floor and nothing returned' => [['results' => [], 'page' => 1, 'limit' => 20, 'total_available' => ['value' => 100, 'is_floor' => true]], false, null],
            'page 10 is the end' => [['results' => $results, 'page' => 10, 'limit' => 20, 'total_available' => ['value' => 900, 'is_floor' => false]], false, null],
            'no totals' => [['results' => $results, 'page' => 1, 'limit' => 20], false, null],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('pages')]
    public function testHasMoreAndNextPage(array $body, bool $hasMore, ?int $next): void
    {
        $result = DecisionSearchResult::fromArray($body);

        $this->assertSame($hasMore, $result->hasMore());
        $this->assertSame($next, $result->nextPage());
    }
}
