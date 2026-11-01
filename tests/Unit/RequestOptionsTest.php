<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use Klaracase\Exception\ConfigurationException;
use Klaracase\RequestOptions;
use Klaracase\Tests\Support\UsesMockClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestOptionsTest extends TestCase
{
    use UsesMockClient;

    protected function setUp(): void
    {
        $this->setUpMockClient();
    }

    /**
     * @return array<string, array{callable(): RequestOptions}>
     */
    public static function badOptions(): array
    {
        return [
            'zero timeout' => [static fn (): RequestOptions => RequestOptions::with(timeout: 0.0)],
            'negative retries' => [static fn (): RequestOptions => RequestOptions::with(maxRetries: -1)],
            'authorization header' => [static fn (): RequestOptions => RequestOptions::with(extraHeaders: ['authorization' => 'Bearer x'])],
        ];
    }

    /**
     * @param callable(): RequestOptions $build
     */
    #[DataProvider('badOptions')]
    public function testBadValuesAreAConfigurationError(callable $build): void
    {
        $this->expectException(ConfigurationException::class);

        $build();
    }

    public function testMergeLetsTheSetFieldsOfTheCallWin(): void
    {
        $client = RequestOptions::with(timeout: 30.0, maxRetries: 2, extraHeaders: ['X-App' => 'a', 'X-Keep' => 'k'], extraQuery: ['a' => '1', 'b' => '2']);
        $call = RequestOptions::with(maxRetries: 0, extraHeaders: ['x-app' => 'b'], extraQuery: ['b' => '3']);

        $merged = $client->merge($call);

        $this->assertSame(30.0, $merged->timeout);
        $this->assertSame(0, $merged->maxRetries);
        $this->assertSame(['X-Keep' => 'k', 'x-app' => 'b'], $merged->extraHeaders);
        $this->assertSame(['a' => '1', 'b' => '3'], $merged->extraQuery);
        $this->assertSame($client, $client->merge(null));
    }

    public function testExtraHeadersAndQueryReachTheRequest(): void
    {
        $this->queueExample('corpus.coverage');

        $this->mockedClient()->corpus->coverage(
            top: 3,
            options: RequestOptions::with(extraHeaders: ['X-Request-Id' => 'abc'], extraQuery: ['trace' => 'on', 'top' => '9']),
        );

        $request = $this->lastRequest();
        $this->assertSame('abc', $request->getHeaderLine('X-Request-Id'));
        $this->assertSame('Bearer klc_test_token', $request->getHeaderLine('Authorization'));
        $this->assertSame('top=9&trace=on', $request->getUri()->getQuery(), 'an extra query value wins over the parameter of the same name');
    }

    public function testClientDefaultsApplyAndTheCallOverridesThem(): void
    {
        $this->queueJson(503, '{"type":"t","title":"T","status":503,"detail":"d","code":"backend_unavailable"}');
        $this->queueExample('corpus.coverage');
        $client = $this->factoryOverMock()->withHeader('X-Team', 'research')->withMaxRetries(0)->make();

        $client->corpus->coverage(options: RequestOptions::with(maxRetries: 1));

        $this->assertCount(2, $this->mock->getRequests(), 'the call allows one retry although the client allows none');
        foreach ($this->mock->getRequests() as $request) {
            $this->assertSame('research', $request->getHeaderLine('X-Team'));
        }
    }

    public function testZeroRetriesForOneCallTurnsRetriesOff(): void
    {
        $this->queueJson(503, '{"type":"t","title":"T","status":503,"detail":"d","code":"backend_unavailable"}');

        try {
            $this->mockedClient()->corpus->coverage(options: RequestOptions::with(maxRetries: 0));
            $this->fail('expected an exception');
        } catch (\Klaracase\Exception\ServiceUnavailableException) {
            $this->assertCount(1, $this->mock->getRequests());
        }
    }
}
