<?php

declare(strict_types=1);

namespace Klaracase\Http;

/**
 * Builds a multipart/form-data body with one file part.
 *
 * @internal
 */
final readonly class Multipart
{
    public string $contentType;

    public string $body;

    public function __construct(
        public string $field,
        public string $filename,
        public string $fileContentType,
        public string $contents,
    ) {
        $field = $this->field;
        $filename = $this->filename;
        $fileContentType = $this->fileContentType;
        $contents = $this->contents;
        $boundary = 'klaracase-' . bin2hex(random_bytes(16));
        $this->contentType = 'multipart/form-data; boundary=' . $boundary;
        $this->body = '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . self::quote($field) . '"; filename="' . self::quote($filename) . "\"\r\n"
            . 'Content-Type: ' . self::headerValue($fileContentType) . "\r\n\r\n"
            . $contents . "\r\n"
            . '--' . $boundary . "--\r\n";
    }

    private static function quote(string $value): string
    {
        return str_replace(['"', "\r", "\n"], ['%22', '%0D', '%0A'], $value);
    }

    private static function headerValue(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }
}
