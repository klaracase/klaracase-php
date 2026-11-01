<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use Klaracase\Exception\DocumentFailedException;
use Klaracase\Exception\DocumentTimeoutException;
use Klaracase\Exception\RateLimitException;
use Klaracase\Response\DecisionHit;
use Klaracase\Tests\Support\Json;
use Klaracase\Tests\Support\UsesMockClient;
use PHPUnit\Framework\TestCase;

/**
 * Pagination generators, the upload-and-check helper and polling.
 */
final class WorkflowTest extends TestCase
{
    use UsesMockClient;

    protected function setUp(): void
    {
        $this->setUpMockClient();
    }

    /**
     * @param list<string> $ids
     * @return array<array-key, mixed>
     */
    private function searchPage(array $ids, int $page, int $limit, int $total, bool $floor = false): array
    {
        $body = Json::decodeFile(__DIR__ . '/../fixtures/examples/decisions.search.json');
        $template = Json::array(Json::array($body['results'])[0]);
        $body['results'] = array_map(static fn (string $id): array => ['id' => $id] + $template, $ids);
        $body['page'] = $page;
        $body['limit'] = $limit;
        $body['total_available'] = ['value' => $total, 'is_floor' => $floor];

        return $body;
    }

    private function queueBody(int $status, mixed $body): void
    {
        $this->queueJson($status, json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function testSearchAllWalksThePagesAndStopsWhenTheTotalsRunOut(): void
    {
        $this->queueBody(200, $this->searchPage(['a', 'b'], 1, 2, 5));
        $this->queueBody(200, $this->searchPage(['c', 'd'], 2, 2, 5));
        $this->queueBody(200, $this->searchPage(['e'], 3, 2, 5));
        $client = $this->mockedClient();

        $ids = [];
        foreach ($client->decisions->searchAll('Untreue', maxPages: 10, court: 'BGH', limit: 2) as $key => $hit) {
            $this->assertInstanceOf(DecisionHit::class, $hit);
            $ids[$key] = $hit->id;
        }

        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $ids);
        $this->assertCount(3, $this->mock->getRequests(), 'no paid call for a page past the end');
        parse_str($this->mock->getRequests()[2]->getUri()->getQuery(), $query);
        $this->assertSame('3', $query['page']);
        $this->assertSame('BGH', $query['court']);
    }

    public function testSearchAllNeverFetchesMoreThanMaxPages(): void
    {
        $this->queueBody(200, $this->searchPage(['a'], 1, 1, 100));
        $this->queueBody(200, $this->searchPage(['b'], 2, 1, 100));
        $this->queueBody(200, $this->searchPage(['c'], 3, 1, 100));

        $hits = iterator_to_array($this->mockedClient()->decisions->searchAll('Untreue', maxPages: 2, limit: 1), false);

        $this->assertCount(2, $hits);
        $this->assertCount(2, $this->mock->getRequests());
    }

    public function testSearchAllClampsToTheTenPagesTheServerServes(): void
    {
        for ($page = 1; $page <= 10; $page++) {
            $this->queueBody(200, $this->searchPage(['x' . $page], $page, 1, 10_000));
        }

        $hits = iterator_to_array($this->mockedClient()->decisions->searchAll('Untreue', maxPages: 500, limit: 1), false);

        $this->assertCount(10, $hits);
        $this->assertCount(10, $this->mock->getRequests());
    }

    public function testSearchAllStopsOnAnEmptyPageEvenWhenTheTotalIsAFloor(): void
    {
        $this->queueBody(200, $this->searchPage(['a'], 1, 1, 100, true));
        $this->queueBody(200, $this->searchPage([], 2, 1, 100, true));
        $this->queueBody(200, $this->searchPage(['never'], 3, 1, 100, true));

        $hits = iterator_to_array($this->mockedClient()->decisions->searchAll('Untreue', maxPages: 5, limit: 1), false);

        $this->assertCount(1, $hits);
        $this->assertCount(2, $this->mock->getRequests());
    }

    public function testSearchAllFetchesNothingUntilTheGeneratorIsRead(): void
    {
        $generator = $this->mockedClient()->decisions->searchAll('Untreue', maxPages: 3);

        $this->assertCount(0, $this->mock->getRequests());
        unset($generator);
    }

    public function testSearchAllPropagatesAnErrorOnALaterPage(): void
    {
        $this->queueBody(200, $this->searchPage(['a'], 1, 1, 9));
        $this->queueJson(429, '{"type":"t","title":"T","status":429,"detail":"d","code":"volume_cap","unit":"rows","cap":10,"resets_at":"x","retry_after_seconds":5000}');

        $this->expectException(RateLimitException::class);

        iterator_to_array($this->mockedClient()->decisions->searchAll('Untreue', maxPages: 3, limit: 1), false);
    }

    public function testCitationsAllWalksPagesWhileTheListsHaveMore(): void
    {
        $body = Json::decodeFile(__DIR__ . '/../fixtures/examples/decisions.citations.json');
        $first = $body;
        $first['pagination'] = Json::array($body['pagination']) + [];
        $first['pagination']['has_more_references'] = true;
        $second = $body;
        $this->queueBody(200, $first);
        $this->queueBody(200, $second);
        $this->queueBody(200, $body);

        $edges = iterator_to_array($this->mockedClient()->decisions->citationsAll('x', maxPages: 20), false);

        $this->assertCount(4, $edges, 'references then referenced_by, two pages');
        $this->assertCount(2, $this->mock->getRequests());
        parse_str($this->mock->getRequests()[1]->getUri()->getQuery(), $query);
        $this->assertSame('2', $query['page']);
        $this->assertSame('1', $query['depth']);
    }

    public function testCitationsAllStopsWithoutPaginationInfo(): void
    {
        $body = Json::decodeFile(__DIR__ . '/../fixtures/examples/decisions.citations.json');
        unset($body['pagination']);
        $this->queueBody(200, $body);
        $this->queueBody(200, $body);

        $edges = iterator_to_array($this->mockedClient()->decisions->citationsAll('x', maxPages: 20), false);

        $this->assertCount(2, $edges);
        $this->assertCount(1, $this->mock->getRequests(), 'an unbounded paid walk is never started on a guess');
    }

    private function documentBody(string $status, string $id = 'doc-1'): string
    {
        $body = Json::decodeFile(__DIR__ . '/../fixtures/examples/citation-check-documents.show.json');
        $body['id'] = $id;
        $body['status'] = $status;
        if ($status === 'failed') {
            $body['error_code'] = 'encrypted_pdf';
        }

        return json_encode($body, JSON_THROW_ON_ERROR);
    }

    public function testCreateFromFileUploadsWaitsAndChecks(): void
    {
        $path = sys_get_temp_dir() . '/klc-brief-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($path, str_repeat('Der Beschluss des BGH. ', 10));
        $this->queueJson(202, $this->documentBody('uploaded'));
        $this->queueJson(200, $this->documentBody('extracting'));
        $this->queueJson(200, $this->documentBody('ready'));
        $this->queueExample('citation-checks.store');

        try {
            $run = $this->mockedClient()->citationChecks->createFromFile($path, referenceDate: '2026-01-31', pollInterval: 0.5);
        } finally {
            unlink($path);
        }

        $requests = $this->mock->getRequests();
        $this->assertCount(4, $requests);
        $this->assertSame(['POST', 'GET', 'GET', 'POST'], array_map(static fn ($r): string => $r->getMethod(), $requests));
        $this->assertSame('/api/v1/citation-check-documents/doc-1', $requests[2]->getUri()->getPath());
        $this->assertSame(['document_id' => 'doc-1', 'reference_date' => '2026-01-31'], json_decode((string) $requests[3]->getBody(), true));
        $this->assertStringContainsString('Content-Type: text/plain', (string) $requests[0]->getBody());
        $this->assertSame([0.5], $this->sleeper->sleeps, 'one wait, on the test clock');
        $this->assertTrue($run->isComplete());
    }

    public function testCreateFromFileStopsWhenTheUploadFails(): void
    {
        $path = sys_get_temp_dir() . '/klc-brief-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, '%PDF');
        $this->queueJson(202, $this->documentBody('uploaded'));
        $this->queueJson(200, $this->documentBody('failed'));

        try {
            $this->mockedClient()->citationChecks->createFromFile($path);
            $this->fail('expected an exception');
        } catch (DocumentFailedException $e) {
            $this->assertSame('encrypted_pdf', $e->errorCode);
            $this->assertCount(2, $this->mock->getRequests(), 'no paid check is created');
        } finally {
            unlink($path);
        }
    }

    public function testCreateFromFileGivesUpAfterTheWaitTimeout(): void
    {
        $path = sys_get_temp_dir() . '/klc-brief-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, '%PDF');
        $this->queueJson(202, $this->documentBody('uploaded'));
        for ($i = 0; $i < 20; $i++) {
            $this->queueJson(200, $this->documentBody('ocr_pending'));
        }

        try {
            $this->mockedClient()->citationChecks->createFromFile($path, waitTimeout: 3.0, pollInterval: 1.0);
            $this->fail('expected an exception');
        } catch (DocumentTimeoutException) {
            $this->assertSame([1.0, 1.0, 1.0], $this->sleeper->sleeps);
        } finally {
            unlink($path);
        }
    }

    public function testWaitUntilReadyAcceptsAReceiptOrAnId(): void
    {
        $this->queueJson(200, $this->documentBody('ready', 'doc-9'));
        $this->queueJson(200, $this->documentBody('ready', 'doc-9'));
        $client = $this->mockedClient();
        $receipt = \Klaracase\Response\CitationCheckDocument::fromArray(Json::array(json_decode($this->documentBody('uploaded', 'doc-9'), true)));

        $fromReceipt = $client->citationCheckDocuments->waitUntilReady($receipt);
        $fromId = $client->citationCheckDocuments->waitUntilReady('doc-9');

        $this->assertSame('ready', $fromReceipt->status);
        $this->assertSame('ready', $fromId->status);
        foreach ($this->mock->getRequests() as $request) {
            $this->assertSame('/api/v1/citation-check-documents/doc-9', $request->getUri()->getPath());
        }
    }
}
