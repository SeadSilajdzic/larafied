<?php

declare(strict_types=1);

namespace Larafied\Data;

final readonly class ProxyResponse
{
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public float $durationMs,
        public string $contentType,
        public int $size,
        public array $queries = [],
    ) {}

    public function toArray(): array
    {
        return [
            'status'       => $this->status,
            'headers'      => $this->headers,
            'body'         => $this->body,
            'duration_ms'  => $this->durationMs,
            'content_type' => $this->contentType,
            'size'         => $this->size,
            'queries'      => $this->queries,
        ];
    }
}
