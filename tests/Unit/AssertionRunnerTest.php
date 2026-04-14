<?php

declare(strict_types=1);

use Larafied\Data\ProxyResponse;
use Larafied\Services\AssertionRunner;

function makeResponse(
    int    $status      = 200,
    string $body        = '',
    string $contentType = 'application/json',
    array  $headers     = [],
): ProxyResponse {
    return new ProxyResponse(
        status:      $status,
        headers:     array_merge(['Content-Type' => $contentType], $headers),
        body:        $body,
        durationMs:  10.0,
        contentType: $contentType,
        size:        strlen($body),
    );
}

// ── status_equals ─────────────────────────────────────────────────────────────

it('passes status_equals when status matches', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'status_equals', 'value' => '200']],
        makeResponse(200),
    );

    expect($result[0]['passed'])->toBeTrue();
});

it('fails status_equals when status does not match', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'status_equals', 'value' => '201']],
        makeResponse(200),
    );

    expect($result[0]['passed'])->toBeFalse();
    expect($result[0]['message'])->toContain('200');
    expect($result[0]['message'])->toContain('201');
});

// ── body_contains ─────────────────────────────────────────────────────────────

it('passes body_contains when text is present', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'body_contains', 'value' => 'success']],
        makeResponse(200, '{"status":"success"}'),
    );

    expect($result[0]['passed'])->toBeTrue();
});

it('fails body_contains when text is absent', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'body_contains', 'value' => 'success']],
        makeResponse(200, '{"status":"error"}'),
    );

    expect($result[0]['passed'])->toBeFalse();
    expect($result[0]['message'])->toContain('success');
});

// ── json_path_equals ──────────────────────────────────────────────────────────

it('passes json_path_equals when value matches', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'json_path_equals', 'path' => 'data.id', 'value' => '42']],
        makeResponse(200, '{"data":{"id":42}}'),
    );

    expect($result[0]['passed'])->toBeTrue();
});

it('fails json_path_equals when value does not match', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'json_path_equals', 'path' => 'data.id', 'value' => '99']],
        makeResponse(200, '{"data":{"id":42}}'),
    );

    expect($result[0]['passed'])->toBeFalse();
    expect($result[0]['message'])->toContain('42');
    expect($result[0]['message'])->toContain('99');
});

it('fails json_path_equals when path does not exist', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'json_path_equals', 'path' => 'missing.key', 'value' => 'x']],
        makeResponse(200, '{"other":"value"}'),
    );

    expect($result[0]['passed'])->toBeFalse();
    expect($result[0]['message'])->toContain('missing.key');
});

it('fails json_path_equals when body is not valid JSON', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'json_path_equals', 'path' => 'id', 'value' => '1']],
        makeResponse(200, 'not-json', 'text/plain'),
    );

    expect($result[0]['passed'])->toBeFalse();
});

// ── header_equals ─────────────────────────────────────────────────────────────

it('passes header_equals when header value matches', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'header_equals', 'path' => 'Content-Type', 'value' => 'application/json']],
        makeResponse(200, '', 'application/json'),
    );

    expect($result[0]['passed'])->toBeTrue();
});

it('passes header_equals case-insensitively on header name', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'header_equals', 'path' => 'content-type', 'value' => 'application/json']],
        makeResponse(200, '', 'application/json'),
    );

    expect($result[0]['passed'])->toBeTrue();
});

it('fails header_equals when header is absent', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'header_equals', 'path' => 'X-Missing', 'value' => 'yes']],
        makeResponse(200),
    );

    expect($result[0]['passed'])->toBeFalse();
    expect($result[0]['message'])->toContain('X-Missing');
});

// ── unknown type ──────────────────────────────────────────────────────────────

it('marks unknown assertion type as failed with a descriptive message', function () {
    $result = (new AssertionRunner())->evaluate(
        [['type' => 'unknown_type', 'value' => 'x']],
        makeResponse(200),
    );

    expect($result[0]['passed'])->toBeFalse();
    expect($result[0]['message'])->toContain('unknown_type');
});

// ── multiple assertions ───────────────────────────────────────────────────────

it('evaluates multiple assertions independently', function () {
    $results = (new AssertionRunner())->evaluate(
        [
            ['type' => 'status_equals', 'value' => '200'],
            ['type' => 'body_contains', 'value' => 'missing'],
            ['type' => 'json_path_equals', 'path' => 'id', 'value' => '1'],
        ],
        makeResponse(200, '{"id":1}'),
    );

    expect($results)->toHaveCount(3);
    expect($results[0]['passed'])->toBeTrue();
    expect($results[1]['passed'])->toBeFalse();
    expect($results[2]['passed'])->toBeTrue();
});

it('returns empty array when no assertions given', function () {
    $results = (new AssertionRunner())->evaluate([], makeResponse(200));

    expect($results)->toBeEmpty();
});
