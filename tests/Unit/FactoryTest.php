<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use Klaracase\Exception\ConfigurationException;
use Klaracase\Http\HttpTransport;
use Klaracase\Klaracase;
use Klaracase\RequestOptions;
use Klaracase\Support\NativeSleeper;
use Klaracase\Tests\Support\UsesMockClient;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class FactoryTest extends TestCase
{
    use UsesMockClient;

    protected function setUp(): void
    {
        $this->setUpMockClient();
    }

    protected function tearDown(): void
    {
        putenv(Klaracase::TOKEN_ENV_VAR);
    }

    public function testClientTakesJustATokenAndFindsAnHttpClient(): void
    {
        $this->expectNotToPerformAssertions();

        Klaracase::client('klc_token');
    }

    public function testEverySettingReachesTheRequest(): void
    {
        $this->queueExample('corpus.coverage');

        Klaracase::factory()
            ->withToken('klc_other')
            ->withBaseUrl('https://example.invalid/v9/')
            ->withHttpClient($this->mock)
            ->withRequestFactory($this->psr17)
            ->withStreamFactory($this->psr17)
            ->withUserAgentSuffix('my-app/2.1')
            ->withHeader('X-Team', 'research')
            ->withTimeout(5.0)
            ->withMaxRetries(0)
            ->make()
            ->corpus->coverage();

        $request = $this->lastRequest();
        $this->assertSame('https://example.invalid/v9/corpus/coverage', (string) $request->getUri());
        $this->assertSame('Bearer klc_other', $request->getHeaderLine('Authorization'));
        $this->assertStringEndsWith(' my-app/2.1', $request->getHeaderLine('User-Agent'));
        $this->assertSame('research', $request->getHeaderLine('X-Team'));
    }

    public function testTheFactoryIsImmutable(): void
    {
        $base = Klaracase::factory()->withHttpClient($this->mock)->withRequestFactory($this->psr17)->withStreamFactory($this->psr17);
        $withToken = $base->withToken('t');

        $this->assertNotSame($base, $withToken);
        $this->expectException(ConfigurationException::class);
        $base->make();
    }

    public function testTheTokenFallsBackToTheEnvironment(): void
    {
        putenv(Klaracase::TOKEN_ENV_VAR . '=klc_env');
        $this->queueExample('corpus.coverage');

        $this->factoryOverMock()->withToken('klc_explicit')->make()->corpus->coverage();
        $this->assertSame('Bearer klc_explicit', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testAnAuthorizationHeaderCannotBeSetThroughTheFactory(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->factoryOverMock()->withHeader('Authorization', 'Bearer other')->make();
    }

    public function testOneSleeperAndOneClockServeTheTransportAndTheDocumentPolling(): void
    {
        $client = Klaracase::factory()->withToken('t')->withHttpClient($this->mock)->withRequestFactory($this->psr17)->withStreamFactory($this->psr17)->make();

        $transport = (new ReflectionProperty($client, 'apiTransport'))->getValue($client);
        $documents = $client->citationCheckDocuments;
        $this->assertInstanceOf(HttpTransport::class, $transport);
        $fromTransport = (new ReflectionProperty($transport, 'sleeper'))->getValue($transport);
        $fromDocuments = (new ReflectionProperty($documents, 'sleeper'))->getValue($documents);

        $this->assertInstanceOf(NativeSleeper::class, $fromTransport);
        $this->assertSame($fromTransport, $fromDocuments);
        $this->assertSame(
            (new ReflectionProperty($transport, 'clock'))->getValue($transport),
            (new ReflectionProperty($documents, 'clock'))->getValue($documents),
        );
    }

    public function testFromEnvDelegatesToTheFactory(): void
    {
        putenv(Klaracase::TOKEN_ENV_VAR . '=klc_from_env');
        $this->queueExample('corpus.coverage');

        Klaracase::fromEnv(
            baseUrl: 'https://example.invalid/api/v1',
            httpClient: $this->mock,
            requestFactory: $this->psr17,
            streamFactory: $this->psr17,
            userAgentSuffix: 'cli',
            maxRetries: 0,
        )->corpus->coverage(options: RequestOptions::with(maxRetries: 0));

        $this->assertSame('Bearer klc_from_env', $this->lastRequest()->getHeaderLine('Authorization'));
        $this->assertStringEndsWith(' cli', $this->lastRequest()->getHeaderLine('User-Agent'));
    }

    public function testTheClientInterfaceGivesTheSameResourcesAsTheProperties(): void
    {
        $client = $this->mockedClient();

        $this->assertSame($client->decisions, $client->decisions());
        $this->assertSame($client->norms, $client->norms());
        $this->assertSame($client->datapackages, $client->datapackages());
        $this->assertSame($client->citationChecks, $client->citationChecks());
        $this->assertSame($client->citationCheckDocuments, $client->citationCheckDocuments());
        $this->assertSame($client->corpus, $client->corpus());
    }
}
