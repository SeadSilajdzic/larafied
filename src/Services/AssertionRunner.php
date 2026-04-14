<?php

declare(strict_types=1);

namespace Larafied\Services;

use Larafied\Data\ProxyResponse;

/**
 * Evaluates a list of assertions against a ProxyResponse.
 *
 * Each assertion is an array with at least:
 *   'type'  — one of: status_equals, body_contains, json_path_equals, header_equals
 *   'value' — expected value (string)
 *   'path'  — (json_path_equals, header_equals) dot-notation path or header name
 *
 * Returns an array of result records:
 *   ['assertion' => [...], 'passed' => bool, 'message' => string]
 */
final class AssertionRunner
{
    public function evaluate(array $assertions, ProxyResponse $response): array
    {
        $results = [];

        foreach ($assertions as $assertion) {
            $results[] = $this->run($assertion, $response);
        }

        return $results;
    }

    private function run(array $assertion, ProxyResponse $response): array
    {
        $type    = $assertion['type'] ?? '';
        $value   = (string) ($assertion['value'] ?? '');
        $path    = (string) ($assertion['path'] ?? '');

        [$passed, $message] = match ($type) {
            'status_equals'    => $this->assertStatusEquals($value, $response),
            'body_contains'    => $this->assertBodyContains($value, $response),
            'json_path_equals' => $this->assertJsonPathEquals($path, $value, $response),
            'header_equals'    => $this->assertHeaderEquals($path, $value, $response),
            default            => [false, "Unknown assertion type: {$type}"],
        };

        return [
            'assertion' => $assertion,
            'passed'    => $passed,
            'message'   => $message,
        ];
    }

    private function assertStatusEquals(string $expected, ProxyResponse $response): array
    {
        $actual = (string) $response->status;

        if ($actual === $expected) {
            return [true, "Status is {$expected}"];
        }

        return [false, "Expected status {$expected}, got {$actual}"];
    }

    private function assertBodyContains(string $needle, ProxyResponse $response): array
    {
        if (str_contains($response->body, $needle)) {
            return [true, "Body contains \"{$needle}\""];
        }

        return [false, "Body does not contain \"{$needle}\""];
    }

    private function assertJsonPathEquals(string $path, string $expected, ProxyResponse $response): array
    {
        $decoded = json_decode($response->body, true);

        if (! is_array($decoded)) {
            return [false, "Response body is not valid JSON"];
        }

        $actual = $this->resolveDotPath($decoded, $path);

        if ($actual === null) {
            return [false, "Path \"{$path}\" not found in response"];
        }

        $actualStr = (string) (is_bool($actual) ? ($actual ? 'true' : 'false') : $actual);

        if ($actualStr === $expected) {
            return [true, "Path \"{$path}\" equals \"{$expected}\""];
        }

        return [false, "Path \"{$path}\": expected \"{$expected}\", got \"{$actualStr}\""];
    }

    private function assertHeaderEquals(string $headerName, string $expected, ProxyResponse $response): array
    {
        $headers = array_change_key_case($response->headers, CASE_LOWER);
        $key     = strtolower($headerName);

        if (! array_key_exists($key, $headers)) {
            return [false, "Header \"{$headerName}\" not present in response"];
        }

        $actual = $headers[$key];

        if ($actual === $expected) {
            return [true, "Header \"{$headerName}\" equals \"{$expected}\""];
        }

        return [false, "Header \"{$headerName}\": expected \"{$expected}\", got \"{$actual}\""];
    }

    private function resolveDotPath(array $data, string $path): mixed
    {
        $segments = explode('.', $path);

        $current = $data;
        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}
