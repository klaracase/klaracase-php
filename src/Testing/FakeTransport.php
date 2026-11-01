<?php

declare(strict_types=1);

namespace Klaracase\Testing;

use Klaracase\Http\ErrorFactory;
use Klaracase\Http\Request;
use Klaracase\Http\Response;
use Klaracase\Http\Transport;
use Klaracase\RequestOptions;
use Klaracase\Response\Model;
use Klaracase\Response\ResponseMeta;
use LogicException;
use Throwable;

/**
 * Serves queued answers in order and records every call. No network, no retries, no waiting.
 *
 * @internal Use `FakeClient`.
 */
final class FakeTransport implements Transport
{
    /** @var list<array{Model|array<array-key, mixed>|Throwable, int}> */
    private array $queue = [];

    /** @var list<RecordedRequest> */
    private array $recorded = [];

    /**
     * @param Model|array<array-key, mixed>|Throwable $answer A response object (served as its JSON), a problem
     *        array (a list with an integer `status` of 400 or more and a string `code`; thrown as the matching
     *        exception), a plain body array, or an exception to throw as it is.
     * @param int $status HTTP status of a success answer (use 202 for an upload receipt).
     */
    public function queue(Model|array|Throwable $answer, int $status = 200): void
    {
        $this->queue[] = [$answer, $status];
    }

    /**
     * @return list<RecordedRequest>
     */
    public function requests(): array
    {
        return $this->recorded;
    }

    public function send(Request $request, RequestOptions $options): Response
    {
        $multipart = $request->multipart;
        $this->recorded[] = new RecordedRequest(
            $request->method,
            $request->path,
            [...$request->query, ...$options->extraQuery],
            $request->json,
            $options,
            $multipart?->filename,
            $multipart?->fileContentType,
            $multipart?->contents,
        );

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new LogicException(sprintf('FakeClient has no queued answer for %s %s. Queue one with queue().', $request->method, $request->path));
        }
        [$answer, $status] = $next;

        if ($answer instanceof Throwable) {
            throw $answer;
        }
        if ($answer instanceof Model) {
            return new Response($answer->toArray(), new ResponseMeta($status, null, 0.0));
        }
        $problemStatus = $answer['status'] ?? null;
        if (is_int($problemStatus) && $problemStatus >= 400 && is_string($answer['code'] ?? null)) {
            /** @var array<string, mixed> $answer */
            throw ErrorFactory::fromProblem($problemStatus, $answer);
        }

        return new Response($answer, new ResponseMeta($status, null, 0.0));
    }
}
