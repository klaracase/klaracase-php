<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use Generator;
use Klaracase\Response\ApiBudget;
use Klaracase\Response\CitationCheck;
use Klaracase\Response\CitationCheckDocument;
use Klaracase\Response\CorpusCoverage;
use Klaracase\Response\CorpusFacets;
use Klaracase\Response\Credits;
use Klaracase\Response\Datapackage;
use Klaracase\Response\Decision;
use Klaracase\Response\DecisionCitations;
use Klaracase\Response\DecisionSearchResult;
use Klaracase\Response\Model;
use Klaracase\Response\Norm;
use Klaracase\Response\Reader;
use Klaracase\Response\SearchResult;
use Klaracase\Response\Wire;
use Klaracase\Tests\Support\DriftGuard;
use Klaracase\Tests\Support\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Drift guards. The typed models must cover every key of every golden body (the examples, the conformance
 * bodies and the first JSON block of each section of CONTRACT.md), keep every unknown key, and give the body back.
 */
final class ModelDriftTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures';

    /** Which model reads which golden body, by the start of the file name. */
    private const CLASSES = [
        'citation-check-documents' => CitationCheckDocument::class,
        'citation-checks' => CitationCheck::class,
        'corpus.coverage' => CorpusCoverage::class,
        'corpus.facets' => CorpusFacets::class,
        'datapackages' => Datapackage::class,
        'decisions.citations' => DecisionCitations::class,
        'decisions.search' => DecisionSearchResult::class,
        'decisions.show' => Decision::class,
        'documents' => CitationCheckDocument::class,
        'norms' => Norm::class,
        'search' => SearchResult::class,
        'post-citation-check-documents' => CitationCheckDocument::class,
        'get-citation-check-documents-document' => CitationCheckDocument::class,
        'post-citation-checks' => CitationCheck::class,
        'get-citation-checks-run' => CitationCheck::class,
        'get-corpus-coverage' => CorpusCoverage::class,
        'get-corpus-facets-field' => CorpusFacets::class,
        'post-datapackages' => Datapackage::class,
        'get-decisions-id-citations' => DecisionCitations::class,
        'get-decisions-id' => Decision::class,
        'get-decisions-search' => DecisionSearchResult::class,
        'get-norms-reference' => Norm::class,
        'get-search' => SearchResult::class,
    ];

    /** The conformance body that carries keys on purpose. */
    private const DELIBERATE_FUTURE_KEYS = 'decisions.search.future_key';

    /**
     * @return Generator<string, array{class-string<Model>, string, string}>
     */
    public static function goldenBodies(): Generator
    {
        $dirs = ['examples' => '*.json', 'conformance/bodies' => '*.json', 'contract' => '*.json'];
        foreach ($dirs as $dir => $pattern) {
            $files = glob(self::FIXTURES . '/' . $dir . '/' . $pattern);
            self::assertNotFalse($files);
            sort($files);
            foreach ($files as $file) {
                $name = basename($file, '.json');
                $class = self::classFor($name);
                if ($class === null || $name === self::DELIBERATE_FUTURE_KEYS) {
                    continue;
                }
                $body = file_get_contents($file);
                self::assertIsString($body);
                yield $dir . '/' . $name => [$class, $body, $dir];
            }
        }
    }

    /**
     * @return class-string<Model>|null
     */
    private static function classFor(string $name): ?string
    {
        foreach (self::CLASSES as $prefix => $class) {
            if (str_starts_with($name, $prefix)) {
                return $class;
            }
        }

        return null;
    }

    public function testEveryGoldenBodyIsCovered(): void
    {
        $bodies = iterator_to_array(self::goldenBodies());

        $this->assertCount(12 + 9 + 12, $bodies);
    }

    /**
     * @param class-string<Model> $class
     */
    #[DataProvider('goldenBodies')]
    public function testNoKeyOfAGoldenBodyFallsIntoExtra(string $class, string $body): void
    {
        $model = $class::fromArray(Json::array(json_decode($body, true, 512, JSON_THROW_ON_ERROR)));

        $this->assertSame([], DriftGuard::extras($model));
    }

    /**
     * @param class-string<Model> $class
     */
    #[DataProvider('goldenBodies')]
    public function testAGoldenBodyRoundTripsExactly(string $class, string $body): void
    {
        $model = $class::fromArray(Json::array(json_decode($body, true, 512, JSON_THROW_ON_ERROR)));

        $this->assertTrue(DriftGuard::jsonRoundTrips($model, $body), 'the JSON form differs from the body');
        $this->assertTrue(DriftGuard::arrayRoundTrips($model, $body), 'toArray() differs from the body');
    }

    /**
     * @param class-string<Model> $class
     */
    #[DataProvider('goldenBodies')]
    public function testEveryEnumValueOfAGoldenBodyIsACase(string $class, string $body, string $source): void
    {
        $model = $class::fromArray(Json::array(json_decode($body, true, 512, JSON_THROW_ON_ERROR)));

        $this->assertSame([], DriftGuard::unknownEnumValues($model));
    }

    public function testTheDeliberateFutureKeysStayInExtraAtEveryDepth(): void
    {
        $body = (string) file_get_contents(self::FIXTURES . '/conformance/bodies/decisions.search.future_key.json');

        $model = DecisionSearchResult::fromArray(Json::array(json_decode($body, true, 512, JSON_THROW_ON_ERROR)));

        $this->assertCount(2, DriftGuard::extras($model));
        $this->assertTrue(DriftGuard::jsonRoundTrips($model, $body));
    }

    public function testTheApiBudgetIsTypedAbsentWhenTheServerOmitsItAndKeepsItsUnknownKeys(): void
    {
        $with = Credits::fromArray([
            'metered' => true,
            'cost' => 1,
            'credits_remaining' => 0,
            'api_budget' => ['cents' => 4991, 'rate_cents_per_credit' => 9, 'credits_at_rate' => 554, 'on_hold' => false],
        ]);

        $this->assertInstanceOf(ApiBudget::class, $with->api_budget);
        $this->assertSame(4991, $with->api_budget->cents);
        $this->assertSame(9, $with->api_budget->rate_cents_per_credit);
        $this->assertSame(554, $with->api_budget->credits_at_rate);
        $this->assertSame(['on_hold' => false], $with->api_budget->extra);
        $this->assertSame(0, $with->credits_remaining, 'the plan credits never count the budget');
        $this->assertSame(['cents' => 4991, 'rate_cents_per_credit' => 9, 'credits_at_rate' => 554, 'on_hold' => false], $with->toArray()['api_budget']);

        $without = Credits::fromArray(['metered' => true, 'cost' => 1, 'credits_remaining' => 40]);

        $this->assertNull($without->api_budget, 'absent is not an invented zero');
        $this->assertArrayNotHasKey('api_budget', $without->toArray());
        $this->assertStringNotContainsString('api_budget', json_encode($without, JSON_THROW_ON_ERROR));
    }

    public function testAZeroBudgetIsStillABudget(): void
    {
        $credits = Credits::fromArray(['metered' => true, 'cost' => 1, 'credits_remaining' => 0, 'api_budget' => ['cents' => -30, 'rate_cents_per_credit' => 9, 'credits_at_rate' => 0]]);

        $this->assertSame(-30, $credits->api_budget?->cents);
        $this->assertSame(0, $credits->api_budget->credits_at_rate);
    }

    public function testTheGuardFailsWhenAModelLacksAKey(): void
    {
        $body = '{"metered":true,"cost":2,"credits_remaining":40}';
        $double = CreditsWithoutRemaining::fromArray(Json::array(json_decode($body, true, 512, JSON_THROW_ON_ERROR)));

        $this->assertSame(['$ has extra keys: credits_remaining'], DriftGuard::extras($double));
        $this->assertTrue(DriftGuard::jsonRoundTrips($double, $body), 'the key is kept in extra, so nothing is lost');
        $this->assertSame([], DriftGuard::extras(Credits::fromArray(Json::array(json_decode($body, true)))), 'the real model has the key');
    }

    public function testTheGuardFailsWhenAModelDropsAKey(): void
    {
        $body = '{"metered":true,"cost":2,"credits_remaining":40}';
        $double = CreditsThatDropsUnknownKeys::fromArray(Json::array(json_decode($body, true, 512, JSON_THROW_ON_ERROR)));

        $this->assertFalse(DriftGuard::jsonRoundTrips($double, $body));
        $this->assertFalse(DriftGuard::arrayRoundTrips($double, $body));
    }

    public function testAnEmptyMapStaysAnObjectInJson(): void
    {
        $body = '{"id":"r1","verdict_counts":{}}';
        $run = CitationCheck::fromArray(Json::array(json_decode($body, true, 512, JSON_THROW_ON_ERROR)));

        $this->assertSame([], $run->verdict_counts);
        $this->assertStringContainsString('"verdict_counts":{}', json_encode($run, JSON_THROW_ON_ERROR));
        $this->assertTrue(DriftGuard::jsonRoundTrips($run, $body));
        $this->assertTrue(DriftGuard::arrayRoundTrips($run, $body));
    }

    public function testTheGuardSeesAnEmptyObjectTurnedIntoAList(): void
    {
        $body = '{"counts":{}}';
        $double = CountsAsPlainArray::fromArray(Json::array(json_decode($body, true, 512, JSON_THROW_ON_ERROR)));

        $this->assertFalse(DriftGuard::jsonRoundTrips($double, $body));
        $this->assertTrue(DriftGuard::arrayRoundTrips($double, $body), 'toArray() cannot tell {} from []; the JSON form can');
    }

    public function testAnUnknownKeyAndAWrongTypeSurviveAtDepth(): void
    {
        $body = Json::decodeFile(self::FIXTURES . '/examples/citation-checks.show.json');
        $findings = Json::array($body['findings']);
        $finding = Json::array($findings[0]);
        $finding['brand_new'] = ['deep' => [1, 2]];
        $finding['start_offset'] = '118';
        $matched = Json::array($finding['matched_decision'] ?? []);
        $matched['next_year_key'] = true;
        $finding['matched_decision'] = $matched;
        $body['findings'] = [$finding];

        $run = CitationCheck::fromArray($body);

        $this->assertNotNull($run->findings);
        $parsed = $run->findings[0];
        $this->assertSame(['deep' => [1, 2]], $parsed->extra['brand_new']);
        $this->assertNull($parsed->start_offset, 'a wrong type reads as null');
        $this->assertSame('118', $parsed->extra['start_offset'], 'and the raw value stays');
        $this->assertSame(['next_year_key' => true], $parsed->matched_decision?->extra);
        $out = Json::array(Json::array($run->toArray()['findings'])[0]);
        $this->assertSame('118', $out['start_offset']);
        $this->assertSame(['deep' => [1, 2]], $out['brand_new']);
    }

    public function testAnExplicitNullStaysAnExplicitNull(): void
    {
        $credits = Credits::fromArray(['metered' => false, 'cost' => null, 'credits_remaining' => null]);

        $this->assertNull($credits->cost);
        $this->assertSame(['metered' => false, 'cost' => null, 'credits_remaining' => null], $credits->toArray());
    }

    public function testAMissingIdentityKeyIsAShapeError(): void
    {
        $this->expectException(\Klaracase\Exception\ResponseShapeException::class);

        Decision::fromArray(['aktenzeichen' => 'VIII ZR 1/20']);
    }
}

/**
 * Doubles of `Credits`: one lacks a key, one drops what it does not know, one forgets that a map is a map.
 */
final readonly class CreditsWithoutRemaining implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra
     * @param list<string> $nulls
     */
    public function __construct(public ?bool $metered = null, public ?int $cost = null, public array $extra = [], public array $nulls = [])
    {
    }

    public static function fromArray(array $data): self
    {
        $r = new Reader($data);

        return new self($r->bool('metered'), $r->int('cost'), $r->rest(), $r->nulls());
    }
}

final readonly class CreditsThatDropsUnknownKeys implements Model
{
    use Wire;

    /**
     * @param array<string, mixed> $extra
     * @param list<string> $nulls
     */
    public function __construct(public ?bool $metered = null, public ?int $cost = null, public array $extra = [], public array $nulls = [])
    {
    }

    public static function fromArray(array $data): self
    {
        $r = new Reader($data);

        return new self($r->bool('metered'), $r->int('cost'));
    }
}

final readonly class CountsAsPlainArray implements Model
{
    use Wire;

    /**
     * @param array<string, int>|null $counts
     * @param array<string, mixed> $extra
     * @param list<string> $nulls
     */
    public function __construct(public ?array $counts = null, public array $extra = [], public array $nulls = [])
    {
    }

    public static function fromArray(array $data): self
    {
        $r = new Reader($data);

        return new self($r->ints('counts'), $r->rest(), $r->nulls());
    }
}
