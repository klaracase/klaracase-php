<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use DateTimeImmutable;
use Klaracase\Client;
use Klaracase\Exception\InsufficientCreditsException;
use Klaracase\Exception\RateLimitException;
use Klaracase\Exception\TransportException;
use Klaracase\Response\CitationCheckDocument;
use Klaracase\Testing\FakeClient;
use Klaracase\Testing\Fixtures;
use Klaracase\Testing\RecordedRequest;
use LogicException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class FakeClientTest extends TestCase
{
    public function testItIsAClientAndAnswersFromTheQueueInOrder(): void
    {
        $fake = new FakeClient();
        $fake->queue(Fixtures::decisionSearch())->queue(Fixtures::decision());

        $this->assertContains(Client::class, class_implements($fake));
        $search = $fake->decisions->search('Eigenbedarf', court: 'BGH');
        $decision = $fake->decisions->get('0193b0c4-5f1e-7a2b-9c3d-4e5f6a7b8c9d');

        $this->assertSame('1 StR 185/01', $search->results[0]->aktenzeichen ?? null);
        $this->assertSame($decision->id, Fixtures::decision()->id);
    }

    public function testItRecordsWhatTheCodeSent(): void
    {
        $fake = new FakeClient();
        $fake->queue(Fixtures::decisionSearch());

        $fake->decisions->search('Eigenbedarf', court: 'BGH', dateFrom: new DateTimeImmutable('2020-01-02'), limit: 3);

        $fake->assertSentCount(1);
        $fake->assertSent(static fn (RecordedRequest $r): bool => $r->method === 'GET'
            && $r->path === '/decisions/search'
            && $r->query['court'] === 'BGH'
            && $r->query['date_from'] === '2020-01-02'
            && $r->query['limit'] === 3);
    }

    public function testNothingSentCanBeAsserted(): void
    {
        $fake = new FakeClient();

        $fake->assertNothingSent();
        $this->assertSame([], $fake->requests());
    }

    public function testAFailedAssertionFailsTheTest(): void
    {
        $fake = new FakeClient();
        $fake->queue(Fixtures::corpusCoverage());
        $fake->corpus->coverage();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('GET /corpus/coverage');

        $fake->assertSent(static fn (RecordedRequest $r): bool => $r->path === '/search');
    }

    public function testAProblemArrayIsThrownAsTheMatchingException(): void
    {
        $fake = new FakeClient();
        $fake->queue(Fixtures::problem(402, 'insufficient_credits', ['cost' => 5, 'balance' => 2]));
        $fake->queue(Fixtures::problem(429, 'rate_limited', ['retry_after_seconds' => 3]));

        try {
            $fake->datapackages->create(['a']);
            $this->fail('expected an exception');
        } catch (InsufficientCreditsException $e) {
            $this->assertSame([5, 2, 'insufficient_credits'], [$e->cost, $e->balance, $e->errorCode]);
        }
        try {
            $fake->corpus->coverage();
            $this->fail('expected an exception');
        } catch (RateLimitException $e) {
            $this->assertSame(3, $e->retryAfterSeconds);
            $this->assertTrue($e->isRetryable());
        }
    }

    public function testAnExceptionIsThrownAsItIs(): void
    {
        $fake = new FakeClient();
        $fake->queue(new TransportException('network down'));

        $this->expectException(TransportException::class);

        $fake->corpus->coverage();
    }

    public function testAnEmptyQueueIsALoudError(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('GET /corpus/coverage');

        (new FakeClient())->corpus->coverage();
    }

    public function testPollingRunsWithoutWaitingAndTheWaitIsRecorded(): void
    {
        $fake = new FakeClient();
        $uploaded = Fixtures::citationCheckDocumentUploaded();
        $fake->queue($uploaded, 202);
        $fake->queue(CitationCheckDocument::fromArray(['id' => 'd', 'status' => 'extracting']));
        $fake->queue(Fixtures::citationCheckDocument());
        $fake->queue(Fixtures::citationCheck());

        $started = microtime(true);
        $run = $fake->citationChecks->createFromFile(__FILE__, pollInterval: 30.0, waitTimeout: 600.0);

        $this->assertLessThan(1.0, microtime(true) - $started);
        $this->assertTrue($run->isComplete());
        $fake->assertSentCount(4);
        $fake->assertSent(static fn (RecordedRequest $r): bool => $r->uploadFilename === 'FakeClientTest.php' && $r->uploadBytes !== null);
        $this->assertInstanceOf(\Klaracase\Testing\RecordingSleeper::class, $fake->sleeper);
        $this->assertSame([30.0], $fake->sleeper->sleeps);
    }

    public function testEveryFixtureLoads(): void
    {
        $fixtures = [
            Fixtures::decisionSearch(), Fixtures::search(), Fixtures::decision(), Fixtures::decisionCitations(), Fixtures::norm(),
            Fixtures::datapackage(), Fixtures::citationCheck(), Fixtures::citationCheckDocument(), Fixtures::citationCheckDocumentUploaded(),
            Fixtures::corpusCoverage(), Fixtures::corpusFacets(),
        ];

        $this->assertCount(11, $fixtures);
    }

    public function testAFixtureMirrorsTheExampleFolderOfTheTests(): void
    {
        $examples = glob(__DIR__ . '/../fixtures/examples/*.json');
        $this->assertNotFalse($examples);
        foreach ($examples as $file) {
            $this->assertFileEquals($file, __DIR__ . '/../../src/Testing/examples/' . basename($file), basename($file));
        }
        $mirrored = glob(__DIR__ . '/../../src/Testing/examples/*.json');
        $this->assertNotFalse($mirrored);
        $this->assertCount(12, $mirrored);
    }
}
