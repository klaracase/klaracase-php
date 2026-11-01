<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Klaracase\Enum\Branch;
use Klaracase\Enum\Direction;
use Klaracase\Enum\DocumentType;
use Klaracase\Enum\FacetField;
use Klaracase\Enum\Mode;
use Klaracase\Enum\Section;
use Klaracase\Enum\Statute;
use Klaracase\Exception\ValidationException;
use Klaracase\Klaracase;
use Klaracase\Tests\Support\Json;
use Klaracase\Tests\Support\UsesMockClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ParametersTest extends TestCase
{
    use UsesMockClient;

    protected function setUp(): void
    {
        $this->setUpMockClient();
    }

    /**
     * @return array<string, array{callable(Klaracase): mixed}>
     */
    public static function refusedCalls(): array
    {
        $text = str_repeat('a', 60);

        return [
            'no ids' => [static fn (Klaracase $c) => self::callLoosely($c->datapackages, 'create', [])],
            'eleven ids' => [static fn (Klaracase $c) => $c->datapackages->create(array_fill(0, 11, 'id'))],
            'an empty id' => [static fn (Klaracase $c) => $c->datapackages->create(['ok', ' '])],
            'text and documentId' => [static fn (Klaracase $c) => $c->citationChecks->create(text: $text, documentId: 'd')],
            'neither text nor documentId' => [static fn (Klaracase $c) => $c->citationChecks->create()],
            'referenceDate not a date' => [static fn (Klaracase $c) => $c->citationChecks->create(text: $text, referenceDate: '01.11.2026')],
            'referenceDate not on the calendar' => [static fn (Klaracase $c) => $c->citationChecks->create(text: $text, referenceDate: '2026-02-30')],
            'dateFrom not a date' => [static fn (Klaracase $c) => $c->decisions->search('Untreue', dateFrom: '2026-1-5')],
            'dateTo not a date' => [static fn (Klaracase $c) => $c->search('Untreue', dateTo: 'tomorrow')],
            'dateFrom after dateTo' => [static fn (Klaracase $c) => $c->decisions->search('Untreue', dateFrom: '2020-01-01', dateTo: '2019-12-31')],
            'dateFrom after dateTo, cross-type' => [static fn (Klaracase $c) => $c->search('Untreue', dateFrom: new DateTimeImmutable('2021-01-01'), dateTo: '2020-01-01')],
            'decision date not a date' => [static fn (Klaracase $c) => $c->decisions->get('x', date: '2020/01/01')],
            'citations date not a date' => [static fn (Klaracase $c) => $c->decisions->citations('x', date: '2020/01/01')],
            'rnFrom after rnTo' => [static fn (Klaracase $c) => $c->decisions->get('x', rnFrom: 9, rnTo: 3)],
            'maxPages zero' => [static fn (Klaracase $c) => self::callLoosely($c->decisions, 'searchAll', 'Untreue', 0)],
            'citations maxPages zero' => [static fn (Klaracase $c) => self::callLoosely($c->decisions, 'citationsAll', 'x', 0)],
            'pollInterval zero' => [static fn (Klaracase $c) => $c->citationCheckDocuments->waitUntilReady('d', pollInterval: 0.0)],
        ];
    }

    /**
     * Calls a method with arguments its PHPDoc types rule out, the way an untyped caller could.
     */
    private static function callLoosely(object $target, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($target, $method))->invokeArgs($target, $arguments);
    }

    /**
     * @param callable(Klaracase): mixed $call
     */
    #[DataProvider('refusedCalls')]
    public function testBadArgumentsAreRefusedBeforeAnyRequest(callable $call): void
    {
        try {
            $call($this->mockedClient());
            $this->fail('expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('client_validation', $e->errorCode);
            $this->assertNull($e->status);
            $this->assertCount(0, $this->mock->getRequests());
        }
    }

    public function testTheBoundsMayBeEqualAndTenIdsAreAllowed(): void
    {
        $this->queueExample('datapackages.store');
        $this->queueExample('decisions.search');
        $client = $this->mockedClient();

        $client->datapackages->create(array_fill(0, 10, 'id'));
        $client->decisions->search('Untreue', dateFrom: '2020-05-05', dateTo: '2020-05-05', court: 'BGH');

        $this->assertCount(2, $this->mock->getRequests());
    }

    public function testEnumsDatesAndStatutesAreSentAsTheirWireStrings(): void
    {
        $this->queueExample('decisions.search');

        $this->mockedClient()->decisions->search(
            'Untreue',
            branch: Branch::Ordentlich,
            documentType: DocumentType::Beschluss,
            citedStatutes: [Statute::StGB, 'StPO'],
            dateFrom: new DateTimeImmutable('2020-03-04 23:30', new DateTimeZone('Europe/Berlin')),
            dateTo: '2021-01-01',
            mode: Mode::Keyword,
            limit: 5,
            page: 2,
            bridge: false,
        );

        parse_str($this->lastRequest()->getUri()->getQuery(), $query);
        $this->assertSame([
            'query' => 'Untreue',
            'branch' => 'ordentlich',
            'document_type' => 'Beschluss',
            'cited_statutes' => ['StGB', 'StPO'],
            'date_from' => '2020-03-04',
            'date_to' => '2021-01-01',
            'mode' => 'keyword',
            'limit' => '5',
            'page' => '2',
            'bridge' => 'false',
        ], $query);
        $this->assertStringContainsString('cited_statutes%5B%5D=StGB&cited_statutes%5B%5D=StPO', $this->lastRequest()->getUri()->getQuery());
    }

    public function testPlainStringsStillWorkAndAreNotCheckedForMembership(): void
    {
        $this->queueExample('decisions.search');

        $this->mockedClient()->decisions->search('Untreue', branch: 'a_branch_from_the_future', mode: 'quantum');

        parse_str($this->lastRequest()->getUri()->getQuery(), $query);
        $this->assertSame('a_branch_from_the_future', $query['branch']);
        $this->assertSame('quantum', $query['mode']);
    }

    public function testTheOtherEnumsAndDatesReachTheRequest(): void
    {
        $this->queueExample('decisions.show');
        $this->queueExample('decisions.citations');
        $this->queueExample('corpus.facets');
        $this->queueExample('corpus.coverage');
        $this->queueExample('citation-checks.store');
        $client = $this->mockedClient();

        $client->decisions->get('x', section: Section::Gruende, date: new DateTimeImmutable('2019-06-12'));
        $this->assertSame('section=Gr%C3%BCnde&date=2019-06-12', $this->lastRequest()->getUri()->getQuery());

        $client->decisions->citations('x', direction: Direction::Outgoing);
        $this->assertSame('direction=outgoing', $this->lastRequest()->getUri()->getQuery());

        $client->corpus->facets(FacetField::CourtName);
        $this->assertSame('/api/v1/corpus/facets/court_name', $this->lastRequest()->getUri()->getPath());

        $client->corpus->coverage(Branch::Sozial);
        $this->assertSame('branch=sozial', $this->lastRequest()->getUri()->getQuery());

        $client->citationChecks->create(text: str_repeat('a', 60), referenceDate: new DateTimeImmutable('2024-02-29'));
        $this->assertSame('2024-02-29', Json::array(json_decode((string) $this->lastRequest()->getBody(), true))['reference_date']);
    }
}
