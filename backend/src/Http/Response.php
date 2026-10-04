<?php

declare(strict_types=1);

namespace Choppro\Http;

final class Response
{
    public function __construct(
        private readonly int $status,
        private readonly string $body,
        private readonly array $headers = [],
    ) {
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            $status,
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function problem(int $status, string $code, string $title): self
    {
        return new self(
            $status,
            (string) json_encode([
                'type' => 'about:blank',
                'title' => $title,
                'status' => $status,
                'code' => $code,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
        );
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
