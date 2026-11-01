<?php

declare(strict_types=1);

namespace Klaracase\Tests\Unit;

use BadMethodCallException;
use Http\Mock\Client as MockClient;
use Klaracase\Enum\RunStatus;
use Klaracase\Enum\Verdict;
use Klaracase\Exception\ApiException;
use Klaracase\Exception\AuthenticationException;
use Klaracase\Exception\ConfigurationException;
use Klaracase\Exception\ConflictException;
use Klaracase\Exception\DocumentFailedException;
use Klaracase\Exception\DocumentTimeoutException;
use Klaracase\Exception\ForbiddenException;
use Klaracase\Exception\InsufficientCreditsException;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\NotFoundException;
use Klaracase\Exception\RateLimitException;
use Klaracase\Exception\ServiceUnavailableException;
use Klaracase\Exception\TimeoutException;
use Klaracase\Exception\TransportException;
use Klaracase\Exception\ValidationException;
use Klaracase\Klaracase;
use Klaracase\Tests\Support\Canonical;
use Klaracase\Tests\Support\Json;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use RuntimeException;

final class ClientTest extends TestCase
{
    private Psr17Factory $factory;

    private MockClient $mock;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->mock = new MockClient($this->factory);
        putenv(Klaracase::TOKEN_ENV_VAR);
        Klaracase::resetDeprecationWarnings();
    }

    protected function tearDown(): void
    {
        putenv(Klaracase::TOKEN_ENV_VAR);
    }

    private function client(?string $token = 'klc_test_token'): Klaracase
    {
        return new Klaracase(
            token: $token,
            baseUrl: 'https://example.invalid/api/v1/',
            httpClient: $this->mock,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );
    }

    private function queue(int $status, string $body, string $contentType = 'application/json'): void
    {
        $this->mock->addResponse(
            $this->factory->createResponse($status)
                ->withHeader('Content-Type', $contentType)
                ->withHeader('X-Klaracase-Api-Release', '0.95.0')
                ->withBody($this->factory->createStream($body)),
        );
    }

    private function lastRequest(): RequestInterface
    {
        $request = $this->mock->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    private function example(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/../fixtures/examples/' . $name . '.json');
        $this->assertIsString($contents);

        return $contents;
    }

    public function testFromEnvReadsTheTokenVariable(): void
    {
        putenv(Klaracase::TOKEN_ENV_VAR . '=klc_from_env');
        $client = Klaracase::fromEnv(
            baseUrl: 'https://example.invalid/api/v1',
            httpClient: $this->mock,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );
        $this->queue(200, $this->example('corpus.coverage'));

        $client->corpus->coverage();

        $this->assertSame('Bearer klc_from_env', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testFromEnvWithoutTheVariableIsAConfigurationError(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('KLARACASE_API_TOKEN');

        Klaracase::fromEnv(httpClient: $this->mock, requestFactory: $this->factory, streamFactory: $this->factory);
    }

    #[DataProvider('badBaseUrls')]
    public function testABadBaseUrlIsAConfigurationError(string $url): void
    {
        $this->expectException(ConfigurationException::class);

        new Klaracase(token: 'x', baseUrl: $url, httpClient: $this->mock, requestFactory: $this->factory, streamFactory: $this->factory);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badBaseUrls(): array
    {
        return ['no scheme' => ['klaracase.de/api/v1'], 'ftp' => ['ftp://klaracase.de'], 'empty' => ['']];
    }

    public function testNegativeRetriesAreRefused(): void
    {
        $this->expectException(ConfigurationException::class);

        new Klaracase(token: 'x', maxRetries: -1, httpClient: $this->mock, requestFactory: $this->factory, streamFactory: $this->factory);
    }

    public function testTheDefaultUserAgentNamesTheSdkAndPhp(): void
    {
        $this->queue(200, $this->example('corpus.coverage'));

        $this->client()->corpus->coverage();

        $this->assertSame('klaracase-php/0.1.0 PHP/' . PHP_VERSION, $this->lastRequest()->getHeaderLine('User-Agent'));
    }

    public function testTheTokenNeverAppearsInAnExceptionMessage(): void
    {
        $this->queue(401, '{"type":"t","title":"Unauthorized","status":401,"detail":"Authentication is required.","code":"unauthenticated"}', 'application/problem+json');

        try {
            $this->client('klc_secret_value')->corpus->coverage();
            $this->fail('expected an exception');
        } catch (AuthenticationException $e) {
            $this->assertStringNotContainsString('klc_secret_value', $e->getMessage() . $e->getTraceAsString());
            $this->assertSame('Authentication is required.', $e->getMessage());
        }
    }

    public function testResponsesKeepUnknownKeysAndAreImmutable(): void
    {
        $body = Json::decodeFile(__DIR__ . '/../fixtures/examples/corpus.facets.json');
        $body['brand_new_key'] = ['a' => 1];
        $this->queue(200, json_encode($body, JSON_THROW_ON_ERROR));

        $facets = $this->client()->corpus->facets('court_name');

        $this->assertSame(['brand_new_key' => ['a' => 1]], $facets->extra);
        $this->assertSame(['a' => 1], $facets['brand_new_key']);
        $this->assertSame(Canonical::sortKeys($body), Canonical::sortKeys($facets->toArray()));
        $this->assertSame(Canonical::sortKeys($body), Canonical::sortKeys(json_decode(json_encode($facets, JSON_THROW_ON_ERROR), true)));
        $this->assertSame('court_name', $facets->field);
        $this->assertSame(3, $facets->total_values);
        $this->assertSame(0, $facets->credits?->cost);

        $this->expectException(BadMethodCallException::class);
        $facets['field'] = 'x';
    }

    public function testAnUnknownEnumValueIsNeverRejected(): void
    {
        $body = Json::decodeFile(__DIR__ . '/../fixtures/examples/citation-checks.show.json');
        $body['status'] = 'a_status_from_the_future';
        $findings = Json::array($body['findings']);
        $finding = Json::array($findings[0]);
        $finding['verdict'] = 'a_verdict_from_the_future';
        $body['findings'] = [$finding];
        $this->queue(200, json_encode($body, JSON_THROW_ON_ERROR));

        $run = $this->client()->citationChecks->get('0193b0c5-1a2b-7c3d-8e4f-5a6b7c8d9e0f');

        $this->assertSame('a_status_from_the_future', $run->status);
        $this->assertSame(RunStatus::Unknown, $run->statusEnum());
        $this->assertNotNull($run->findings);
        $this->assertSame('a_verdict_from_the_future', $run->findings[0]->verdict);
        $this->assertSame(Verdict::Unknown, $run->findings[0]->verdictEnum());
    }

    public function testAKeyWithTheWrongJsonTypeReadsAsNullAndStaysInTheRawBody(): void
    {
        $this->queue(200, '{"id":"d1","filename":123,"status":"ready"}');

        $document = $this->client()->citationCheckDocuments->get('x');

        $this->assertNull($document->filename);
        $this->assertSame(123, $document['filename']);
        $this->assertSame(123, $document->extra['filename']);
    }

    public function testTheDefaultDeprecationHookRaisesOneUserDeprecation(): void
    {
        $raised = [];
        set_error_handler(static function (int $level, string $message) use (&$raised): bool {
            $raised[] = [$level, $message];

            return true;
        });
        foreach ([1, 2] as $_) {
            $this->mock->addResponse(
                $this->factory->createResponse(200)
                    ->withHeader('Sunset', 'Sat, 01 Aug 2026 00:00:00 GMT')
                    ->withBody($this->factory->createStream($this->example('corpus.coverage'))),
            );
        }
        $client = $this->client();

        try {
            $client->corpus->coverage();
            $client->corpus->coverage();
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $raised);
        $this->assertSame(E_USER_DEPRECATED, $raised[0][0]);
        $this->assertStringContainsString('/corpus/coverage', $raised[0][1]);
        $this->assertStringContainsString('Sunset: Sat, 01 Aug 2026', $raised[0][1]);
    }

    #[DataProvider('statusClasses')]
    public function testEachStatusMapsToItsExceptionClass(int $status, string $class): void
    {
        $this->queue($status, '{"type":"t","title":"T","status":' . $status . ',"detail":"d","code":"some_code"}', 'application/problem+json');
        $client = new Klaracase(token: 'x', maxRetries: 0, baseUrl: 'https://example.invalid/api/v1', httpClient: $this->mock, requestFactory: $this->factory, streamFactory: $this->factory);

        try {
            $client->corpus->coverage();
            $this->fail('expected an exception');
        } catch (KlaracaseException $e) {
            $this->assertSame($class, $e::class);
            $this->assertSame($status, $e->status);
            $this->assertSame($status, $e->getCode());
            $this->assertSame('some_code', $e->errorCode);
        }
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function statusClasses(): array
    {
        return [
            'unauthenticated' => [401, AuthenticationException::class],
            'insufficient credits' => [402, InsufficientCreditsException::class],
            'forbidden' => [403, ForbiddenException::class],
            'not found' => [404, NotFoundException::class],
            'conflict' => [409, ConflictException::class],
            'validation' => [422, ValidationException::class],
            'rate limit' => [429, RateLimitException::class],
            'unavailable' => [503, ServiceUnavailableException::class],
            'bad request' => [400, ApiException::class],
            'server error' => [500, ApiException::class],
        ];
    }

    public function testEveryApiErrorExtendsTheBaseAndConfigurationDoesNot(): void
    {
        $classes = [
            AuthenticationException::class, ForbiddenException::class, NotFoundException::class, ConflictException::class,
            ValidationException::class, InsufficientCreditsException::class, RateLimitException::class,
            ServiceUnavailableException::class, ApiException::class, TransportException::class, TimeoutException::class,
            DocumentFailedException::class, DocumentTimeoutException::class,
        ];
        foreach ($classes as $class) {
            $this->assertTrue((new ReflectionClass($class))->isSubclassOf(KlaracaseException::class), $class);
        }
        $this->assertTrue((new ReflectionClass(TimeoutException::class))->isSubclassOf(TransportException::class));
        $this->assertTrue((new ReflectionClass(KlaracaseException::class))->isSubclassOf(RuntimeException::class));
    }

    public function testAnUploadAcceptsBytesAResourceAndAStream(): void
    {
        $bytes = "%PDF-1.4\n\xFF\x00binary\n";
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, $bytes);
        rewind($stream);

        $sources = ['string' => $bytes, 'resource' => $stream, 'psr7' => $this->factory->createStream($bytes)];
        foreach ($sources as $label => $source) {
            $this->queue(202, $this->example('citation-check-documents.store'));
            $this->client()->citationCheckDocuments->upload($source, 'a "quoted"\r\nname.pdf', 'application/pdf');

            $request = $this->lastRequest();
            $body = (string) $request->getBody();
            $this->assertStringContainsString($bytes, $body, $label);
            $this->assertStringContainsString('filename="a %22quoted%22\r\nname.pdf"', $body, $label);
            $this->assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        }
    }

    public function testAnUploadOfAnotherTypeIsRefusedBeforeAnyRequest(): void
    {
        try {
            $this->client()->citationCheckDocuments->upload(42, 'a.pdf', 'application/pdf');
            $this->fail('expected an exception');
        } catch (ValidationException $e) {
            $this->assertSame('client_validation', $e->errorCode);
            $this->assertCount(0, $this->mock->getRequests());
        }
    }

    public function testAnUnreadableJsonBodyIsAnErrorNotAFatal(): void
    {
        $this->queue(200, 'not json');

        try {
            $this->client()->corpus->coverage();
            $this->fail('expected an exception');
        } catch (ApiException $e) {
            $this->assertSame('invalid_response', $e->errorCode);
            $this->assertSame(200, $e->status);
        }
    }

    public function testInvalidUtf8InTheTextIsAClientValidationError(): void
    {
        try {
            $this->client()->citationChecks->create(text: "bad \xB1 byte");
            $this->fail('expected an exception');
        } catch (ValidationException $e) {
            $this->assertSame('client_validation', $e->errorCode);
            $this->assertCount(0, $this->mock->getRequests());
        }
    }

    public function testTheValidationErrorsMapIsTyped(): void
    {
        $this->queue(422, '{"type":"t","title":"U","status":422,"detail":"d","code":"validation_failed","errors":{"query":["too short"]}}', 'application/problem+json');
        $client = new Klaracase(token: 'x', maxRetries: 0, baseUrl: 'https://example.invalid/api/v1', httpClient: $this->mock, requestFactory: $this->factory, streamFactory: $this->factory);

        try {
            $client->decisions->search('x');
            $this->fail('expected an exception');
        } catch (ValidationException $e) {
            $this->assertSame(['query' => ['too short']], $e->errors);
        }
    }
}
