<?php

declare(strict_types=1);

namespace Klaracase\Testing;

use Klaracase\RequestOptions;

/**
 * One call the test double received. Query values are exactly what the SDK would put on the URL, so a
 * `DateTimeInterface` shows up as `YYYY-MM-DD` and an enum as its string.
 */
final readonly class RecordedRequest
{
    /**
     * @param string $method GET or POST.
     * @param string $path Encoded path below the base URL, for example `/decisions/search`.
     * @param array<string, scalar|list<scalar>|null> $query
     * @param array<string, mixed>|null $json The JSON body of a POST.
     * @param string|null $uploadFilename The file name of an upload.
     * @param string|null $uploadContentType The content type of an upload.
     * @param string|null $uploadBytes The bytes of an upload.
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public ?array $json,
        public RequestOptions $options,
        public ?string $uploadFilename = null,
        public ?string $uploadContentType = null,
        public ?string $uploadBytes = null,
    ) {
    }
}
