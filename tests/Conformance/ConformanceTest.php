<?php

declare(strict_types=1);

namespace Klaracase\Tests\Conformance;

use Generator;
use Http\Message\RequestMatcher;
use Http\Mock\Client as MockClient;
use Klaracase\Exception\KlaracaseException;
use Klaracase\Exception\ValidationException;
use Klaracase\Http\DeprecationNotice;
use Klaracase\Klaracase;
use Klaracase\Support\Clock;
use Klaracase\Support\NativeSleeper;
use Klaracase\Support\Sleeper;
use Klaracase\Support\SystemClock;
use Klaracase\Testing\FakeClock;
use Klaracase\Testing\RecordingSleeper;
use Klaracase\Tests\Support\Canonical;
use Klaracase\Tests\Support\FieldPath;
use Klaracase\Tests\Support\Json;
use Klaracase\Tests\Support\NetworkFailure;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use Throwable;

/**
 * Runs every case of the shared conformance pack (tests/fixtures/cases) against the client.
 * An HTTP mock client serves the case's responses in order and records the requests.
 */
final class ConformanceTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures';

    private const TOKEN = 'test_token_123';

    /**
     * Cases whose arguments this SDK refuses before any request, because the pack lets the server answer them
     * and the SDK checks them first (`dateFrom` after `dateTo`). They expect a `client_validation` error and zero
     * requests here. The 422 mapping they cover is tested in ClientTest with a valid call.
     */
    private const REFUSED_BY_THE_CLIENT = ['errors.validation_failed_multiple'];

    private const BASE = 'http://127.0.0.1:9/api/v1';

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        putenv(Klaracase::TOKEN_ENV_VAR);
        unset($_ENV[Klaracase::TOKEN_ENV_VAR], $_SERVER[Klaracase::TOKEN_ENV_VAR]);
        Klaracase::resetDeprecationWarnings();
    }

    protected function tearDown(): void
    {
        putenv(Klaracase::TOKEN_ENV_VAR);
    }

    /**
     * @return Generator<string, array{string}>
     */
    public static function cases(): Generator
    {
        $files = glob(self::FIXTURES . '/conformance/cases/*.json');
        self::assertNotFalse($files);
        sort($files);
        foreach ($files as $file) {
            yield basename($file, '.json') => [$file];
        }
    }

    #[DataProvider('cases')]
    public function testCase(string $file): void
    {
        $case = Json::decodeFile($file);
        $sdk = Json::array($case['sdk']);
        $options = Json::array($sdk['options'] ?? []);
        $expect = Json::array($case['expect']);
        $runner = Json::array($case['runner'] ?? []);
        $responses = array_values(Json::array($case['responses']));

        foreach (Json::array($sdk['env'] ?? []) as $name => $value) {
            putenv($name . '=' . Json::string($value));
        }

        $realClock = ($runner['real_clock'] ?? false) === true;
        $fakeClock = new FakeClock();
        $clock = $realClock ? new SystemClock() : $fakeClock;
        $sleeper = $realClock ? new NativeSleeper() : new RecordingSleeper($fakeClock);
        $mock = $this->mockClient($file, $responses, ($runner['repeat_last_response'] ?? false) === true, $options);
        $deprecations = [];

        $outcome = null;
        $error = null;
        try {
            $client = $this->buildClient($options, $mock, $sleeper, $clock, static function (DeprecationNotice $notice) use (&$deprecations): void {
                $deprecations[] = $notice;
            });
            for ($i = 0, $repeat = Json::int($sdk['repeat'] ?? 1); $i < $repeat; $i++) {
                $outcome = $this->call($client, $sdk, dirname($file));
            }
        } catch (Throwable $e) {
            $error = $e;
        }

        if (in_array(basename($file, '.json'), self::REFUSED_BY_THE_CLIENT, true)) {
            $this->assertInstanceOf(ValidationException::class, $error);
            $this->assertSame('client_validation', $error->errorCode);
            $this->assertNull($error->status);
            $this->assertCount(0, $mock->getRequests(), 'the client refuses before any request');

            return;
        }

        $this->assertRequests($expect, $mock);
        if ($sleeper instanceof RecordingSleeper && array_key_exists('sleeps', $expect)) {
            $this->assertSleeps(array_values(Json::array($expect['sleeps'])), $sleeper->sleeps);
        }

        $expected = Json::array($case['expect_result']);
        if ($expected['kind'] === 'success') {
            $this->assertNull($error, $error === null ? '' : $error::class . ': ' . $error->getMessage());
            $this->assertSuccess($expected, $outcome, $file, $responses, $deprecations);

            return;
        }
        $this->assertNotNull($error, 'Expected an error of class ' . Json::string($expected['class']));
        $this->assertError($expected, $error, $responses, dirname($file));
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function buildClient(array $options, MockClient $mock, Sleeper $sleeper, Clock $clock, callable $hook): Klaracase
    {
        $token = array_key_exists('token', $options) ? Json::stringOrNull($options['token']) : self::TOKEN;
        $baseUrl = self::BASE . (($options['base_url_trailing_slash'] ?? false) === true ? '/' : '');
        $maxRetries = array_key_exists('max_retries', $options) ? Json::int($options['max_retries']) : 2;
        $timeout = array_key_exists('timeout', $options) ? Json::float($options['timeout']) : 30.0;
        $suffix = array_key_exists('user_agent_suffix', $options) ? Json::stringOrNull($options['user_agent_suffix']) : null;

        return new Klaracase(
            token: $token,
            baseUrl: $baseUrl,
            timeout: $timeout,
            maxRetries: $maxRetries,
            userAgentSuffix: $suffix,
            httpClient: $mock,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
            onDeprecation: $hook,
            sleeper: $sleeper,
            clock: $clock,
        );
    }

    /**
     * @param array<array-key, mixed> $sdk
     */
    private function call(Klaracase $client, array $sdk, string $caseDir): mixed
    {
        $args = [];
        foreach (Json::array($sdk['args']) as $name => $value) {
            if (is_array($value) && isset($value['$file'])) {
                $bytes = file_get_contents($caseDir . '/../' . Json::string($value['$file']));
                $this->assertIsString($bytes);
                $value = $bytes;
            }
            $args[self::argumentName($sdk, (string) $name)] = $value;
        }

        $method = FieldPath::camel(Json::string($sdk['method']));
        if ($method === 'upload') {
            $method = 'uploadBytes';
        }
        $target = match ($sdk['group']) {
            null => $client,
            'decisions' => $client->decisions,
            'norms' => $client->norms,
            'datapackages' => $client->datapackages,
            'citation_checks' => $client->citationChecks,
            'citation_check_documents' => $client->citationCheckDocuments,
            'corpus' => $client->corpus,
            default => $this->fail('Unknown group ' . Json::string($sdk['group'])),
        };

        return (new ReflectionMethod($target, $method))->invokeArgs($target, $args);
    }

    /**
     * The pack names the argument by its API parameter. In PHP `upload` with file content is `uploadBytes(bytes:)`,
     * and `waitUntilReady` takes a receipt or an id as `documentOrId`.
     *
     * @param array<array-key, mixed> $sdk
     */
    private static function argumentName(array $sdk, string $name): string
    {
        if ($sdk['group'] === 'citation_check_documents' && $name === 'document_id') {
            return $sdk['method'] === 'wait_until_ready' ? 'documentOrId' : 'id';
        }
        if ($sdk['group'] === 'citation_check_documents' && $sdk['method'] === 'upload' && $name === 'file') {
            return 'bytes';
        }

        return FieldPath::camel($name);
    }

    /**
     * Serves the responses in order, whether a response or a thrown network error. Requests are recorded.
     *
     * @param list<mixed> $responses
     * @param array<array-key, mixed> $options
     */
    private function mockClient(string $file, array $responses, bool $repeatLast, array $options): MockClient
    {
        $mock = new MockClient($this->factory);
        $served = 0;
        $timeout = isset($options['timeout']) ? Json::float($options['timeout']) : 30.0;
        $factory = $this->factory;
        $self = $this;
        $mock->on(
            new class () implements RequestMatcher {
                public function matches(RequestInterface $request): bool
                {
                    return true;
                }
            },
            static function (RequestInterface $request) use (&$served, $responses, $repeatLast, $file, $timeout, $factory, $self): ResponseInterface {
                $index = $served++;
                if ($index >= count($responses) && $repeatLast && $responses !== []) {
                    $index = count($responses) - 1;
                }
                if (! isset($responses[$index])) {
                    return $factory->createResponse(599, 'Mock exhausted');
                }

                return $self->serve($request, Json::array($responses[$index]), $file, $timeout);
            },
        );

        return $mock;
    }

    /**
     * @param array<array-key, mixed> $spec
     */
    private function serve(RequestInterface $request, array $spec, string $file, float $timeout): ResponseInterface
    {
        if (($spec['drop_connection'] ?? false) === true) {
            throw new NetworkFailure($request, 'cURL error 52: Empty reply from server');
        }
        if (isset($spec['delay_seconds']) && Json::float($spec['delay_seconds']) > $timeout) {
            throw new NetworkFailure($request, 'cURL error 28: Operation timed out after ' . $timeout . ' seconds');
        }

        $headers = [];
        foreach (Json::array($spec['headers'] ?? []) as $name => $value) {
            $headers[(string) $name] = Json::string($value);
        }
        $body = '';
        if (array_key_exists('body', $spec)) {
            $body = json_encode($spec['body'], JSON_THROW_ON_ERROR);
        } elseif (isset($spec['body_file'])) {
            $contents = file_get_contents(dirname($file) . '/' . Json::string($spec['body_file']));
            $this->assertIsString($contents);
            $body = $contents;
        } elseif (isset($spec['body_text'])) {
            $body = Json::string($spec['body_text']);
        }
        if (! array_key_exists('content-type', $headers) && ! isset($spec['body_text']) && (array_key_exists('body', $spec) || str_ends_with(Json::string($spec['body_file'] ?? ''), '.json'))) {
            $headers['content-type'] = 'application/json';
        }

        $response = $this->factory->createResponse(Json::int($spec['status'] ?? 200));
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response->withBody($this->factory->createStream($body));
    }

    /**
     * @param array<array-key, mixed> $expect
     */
    private function assertRequests(array $expect, MockClient $mock): void
    {
        $requests = $mock->getRequests();
        $count = $expect['request_count'];
        if (is_array($count)) {
            $this->assertGreaterThanOrEqual(Json::int($count['min'] ?? null), count($requests), 'request count');
            $this->assertLessThanOrEqual(Json::int($count['max'] ?? null), count($requests), 'request count');
        } else {
            $this->assertCount(Json::int($count), $requests, 'request count');
        }
        if ($expect['request'] === null) {
            $this->assertCount(0, $requests);

            return;
        }
        foreach ($requests as $request) {
            $this->assertRequest(Json::array($expect['request']), $request);
        }
    }

    /**
     * @param array<array-key, mixed> $expected
     */
    private function assertRequest(array $expected, RequestInterface $request): void
    {
        $this->assertSame($expected['method'], $request->getMethod());

        $uri = $request->getUri();
        $this->assertSame('/api/v1' . Json::string($expected['path']), $uri->getPath(), 'raw request path');
        $this->assertSame('127.0.0.1', $uri->getHost());

        /** @var array<string, string|list<string>> $actualQuery */
        $actualQuery = [];
        foreach ($uri->getQuery() === '' ? [] : explode('&', $uri->getQuery()) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($name);
            $value = urldecode($value);
            if (str_ends_with($name, '[]')) {
                $list = $actualQuery[$name] ?? [];
                $this->assertIsArray($list);
                $list[] = $value;
                $actualQuery[$name] = $list;
            } else {
                $actualQuery[$name] = $value;
            }
        }
        $expectedQuery = [];
        foreach (Json::array($expected['query'] ?? []) as $name => $value) {
            $expectedQuery[$name] = is_array($value) ? array_values($value) : $value;
        }
        ksort($actualQuery);
        ksort($expectedQuery);
        $this->assertSame($expectedQuery, $actualQuery, 'query');

        foreach (Json::array($expected['headers']) as $name => $value) {
            $name = (string) $name;
            $actual = $request->getHeaderLine($name);
            switch (strtolower($name)) {
                case 'user-agent':
                    $this->assertTrue(str_starts_with($actual, Json::string($value)), 'user-agent prefix');
                    $this->assertMatchesRegularExpression('#^klaracase-php/0\.1\.0 PHP/\d+\.\d+#', $actual);
                    if (isset($expected['user_agent_suffix'])) {
                        $this->assertStringEndsWith(' ' . Json::string($expected['user_agent_suffix']), $actual);
                    }
                    break;
                case 'content-type':
                    $this->assertSame(strtolower(Json::string($value)), strtolower(trim(explode(';', $actual)[0])));
                    break;
                default:
                    $this->assertSame($value, $actual, 'header ' . $name);
            }
        }
        if (! array_key_exists('content-type', array_change_key_case(Json::array($expected['headers']), CASE_LOWER))) {
            $this->assertFalse($request->hasHeader('Content-Type'), 'a request without a body sends no content-type');
        }

        $body = (string) $request->getBody();
        if (array_key_exists('json', $expected)) {
            $this->assertSame(json_encode($expected['json']), json_encode(json_decode($body, true)), 'json body');
            $this->assertEquals($expected['json'], json_decode($body, true), 'json body');
        }
        if (array_key_exists('multipart', $expected)) {
            $this->assertSame($expected['multipart'], self::multipartParts($request->getHeaderLine('Content-Type'), $body));
        }
    }

    /**
     * @return list<array{name: string, filename: string, content_type: string}>
     */
    private static function multipartParts(string $contentType, string $body): array
    {
        if (preg_match('/boundary=([^;]+)/', $contentType, $m) !== 1) {
            self::fail('No multipart boundary in ' . $contentType);
        }
        $parts = [];
        foreach (explode('--' . $m[1], $body) as $chunk) {
            if (! str_contains($chunk, 'Content-Disposition')) {
                continue;
            }
            if (preg_match('/name="([^"]*)"; filename="([^"]*)"\r\nContent-Type: ([^\r\n]*)\r\n\r\n/', $chunk, $p) !== 1) {
                self::fail('Cannot parse the multipart part: ' . $chunk);
            }
            $parts[] = ['name' => $p[1], 'filename' => $p[2], 'content_type' => $p[3]];
        }

        return $parts;
    }

    /**
     * @param list<mixed> $expected
     * @param list<float> $actual
     */
    private function assertSleeps(array $expected, array $actual): void
    {
        $this->assertCount(count($expected), $actual, 'sleeps: ' . json_encode($actual));
        foreach ($expected as $i => $range) {
            $range = Json::array($range);
            if (array_key_exists('exact', $range)) {
                $this->assertEqualsWithDelta(Json::float($range['exact']), $actual[$i], 1e-9, "sleep $i");

                continue;
            }
            $this->assertGreaterThanOrEqual(Json::float($range['min']), $actual[$i], "sleep $i");
            $this->assertLessThanOrEqual(Json::float($range['max']), $actual[$i], "sleep $i");
        }
    }

    /**
     * @param array<array-key, mixed> $expected
     * @param list<mixed> $responses
     * @param list<DeprecationNotice> $deprecations
     */
    private function assertSuccess(array $expected, mixed $result, string $file, array $responses, array $deprecations): void
    {
        $this->assertIsObject($result);
        foreach (Json::array($expected['assert'] ?? []) as $path => $want) {
            $this->assertValue($want, FieldPath::get($result, (string) $path), (string) $path);
        }
        if (($expected['body_roundtrip'] ?? false) === true) {
            $last = Json::array(end($responses));
            $this->assertTrue(method_exists($result, 'toArray'));
            $body = file_get_contents(dirname($file) . '/' . Json::string($last['body_file']));
            $this->assertIsString($body);
            $this->assertSame(Canonical::json($body), Canonical::json(json_encode($result, JSON_THROW_ON_ERROR)), 'the JSON form of the response equals the body');
            $this->assertSame(Canonical::sortKeys(Json::decodeFile(dirname($file) . '/' . Json::string($last['body_file']))), Canonical::sortKeys($result->toArray()), 'toArray() equals the body');
        }
        if (array_key_exists('deprecation_warning', $expected)) {
            $this->assertSame($expected['deprecation_warning'], $deprecations !== []);
        }
        if (array_key_exists('deprecation_warning_count', $expected)) {
            $this->assertCount(Json::int($expected['deprecation_warning_count']), $deprecations);
        }
        if (isset($expected['deprecation'])) {
            $want = Json::array($expected['deprecation']);
            $this->assertNotEmpty($deprecations);
            $this->assertSame($want['deprecation'] ?? null, $deprecations[0]->deprecation);
            $this->assertSame($want['sunset'] ?? null, $deprecations[0]->sunset);
            $this->assertSame($want['link'] ?? null, $deprecations[0]->link);
        }
    }

    /**
     * @param array<array-key, mixed> $expected
     * @param list<mixed> $responses
     */
    private function assertError(array $expected, Throwable $error, array $responses, string $caseDir): void
    {
        $class = $this->exceptionClass(Json::string($expected['class']));
        $this->assertInstanceOf($class, $error, $error::class . ': ' . $error->getMessage());

        if ($class === \Klaracase\Exception\ConfigurationException::class) {
            $this->assertNotInstanceOf(KlaracaseException::class, $error);
        } else {
            $this->assertInstanceOf(KlaracaseException::class, $error);
            $this->assertSame($expected['code'], $error->errorCode, 'error code');
            $this->assertSame($expected['status'], $error->status, 'error status');
            $this->assertStringNotContainsString(self::TOKEN, $error->getMessage());
        }
        foreach (Json::array($expected['fields'] ?? []) as $path => $want) {
            $this->assertValue($want, FieldPath::get($error, (string) $path), (string) $path);
        }

        if ($error instanceof KlaracaseException && $error->status !== null && $responses !== []) {
            $last = Json::array(end($responses));
            $body = $this->problemBody($last, $caseDir);
            if ($body !== null) {
                $this->assertSame($body['type'] ?? null, $error->type);
                $this->assertSame($body['title'] ?? null, $error->title);
                $this->assertSame($body['detail'] ?? null, $error->detail);
                $this->assertSame($body['code'], $error->errorCode);
                $release = Json::array($last['headers'] ?? [])['x-klaracase-api-release'] ?? null;
                $this->assertSame($release, $error->apiRelease);
                $extra = array_diff_key($body, array_flip(['type', 'title', 'status', 'detail', 'code']));
                $this->assertEquals($extra, $error->extra);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $response
     * @return array<array-key, mixed>|null
     */
    private function problemBody(array $response, string $caseDir): ?array
    {
        $decoded = null;
        if (isset($response['body_file']) && str_ends_with(Json::string($response['body_file']), '.json')) {
            $decoded = Json::decodeFile($caseDir . '/' . Json::string($response['body_file']));
        } elseif (isset($response['body']) && is_array($response['body'])) {
            $decoded = $response['body'];
        }

        return is_array($decoded) && is_string($decoded['code'] ?? null) ? $decoded : null;
    }

    /**
     * @return class-string<Throwable>
     */
    private function exceptionClass(string $name): string
    {
        $name = $name === 'PermissionError' ? 'ForbiddenError' : $name;
        $class = 'Klaracase\\Exception\\' . str_replace('Error', 'Exception', $name);
        $this->assertTrue(class_exists($class), "Exception class $class exists");
        $this->assertTrue(is_a($class, Throwable::class, true));

        return $class;
    }

    private function assertValue(mixed $want, mixed $actual, string $path): void
    {
        if (is_array($want) && $want !== [] && array_reduce(array_keys($want), static fn (bool $all, int|string $k): bool => $all && str_starts_with((string) $k, '$'), true)) {
            foreach ($want as $matcher => $arg) {
                match ($matcher) {
                    '$type' => $this->assertSame($arg, self::jsonType($actual), "$path type"),
                    '$min' => $this->assertGreaterThanOrEqual($arg, $actual, "$path min"),
                    '$length' => $this->assertSame($arg, is_string($actual) ? mb_strlen($actual, 'UTF-8') : (is_array($actual) ? count($actual) : -1), "$path length"),
                    '$contains' => $this->assertStringContainsString(Json::string($arg), Json::string($actual), "$path contains"),
                    '$starts_with' => $this->assertTrue(str_starts_with(Json::string($actual), Json::string($arg)), "$path starts_with"),
                    default => $this->fail("Unknown matcher $matcher"),
                };
            }

            return;
        }
        $this->assertEquals($want, $actual, "value at $path");
    }

    private static function jsonType(mixed $value): string
    {
        return match (true) {
            is_int($value), is_float($value) => 'number',
            is_string($value) => 'string',
            is_bool($value) => 'boolean',
            is_array($value) => array_is_list($value) ? 'array' : 'object',
            default => 'null',
        };
    }
}
