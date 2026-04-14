<?php

declare(strict_types=1);

namespace Larafied\Services;

use Larafied\Data\ProxyResponse;
use Larafied\Http\Requests\ProxyRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

final class RequestProxy
{
    public function __construct(
        private readonly SsrfGuard $ssrfGuard,
        private readonly Client $httpClient,
    ) {}

    public function send(ProxyRequest $request, bool $debug = false): ProxyResponse
    {
        $url = $request->validated('url');

        $auth  = $request->validated('auth') ?? [];
        $query = [];

        if (! empty($auth)) {
            $headers = $request->validated('headers', []) ?? [];
            [$headers, $query] = $this->applyAuth($auth, $headers);
        } else {
            $headers = $request->validated('headers', []) ?? [];
        }

        if ($debug) {
            $headers['X-Larafied-Debug'] = '1';
        }

        $body   = $request->validated('body');
        $method = strtoupper($request->validated('method'));

        return $this->dispatch($method, $url, $headers, $body, $query, $debug);
    }

    public function sendRaw(
        string  $method,
        string  $url,
        array   $headers = [],
        ?string $body    = null,
    ): ProxyResponse {
        return $this->dispatch(strtoupper($method), $url, $headers, $body, [], false);
    }

    private function dispatch(
        string  $method,
        string  $url,
        array   $headers,
        mixed   $body,
        array   $query,
        bool    $debug,
    ): ProxyResponse {
        $this->ssrfGuard->validate($url);

        $options = $this->buildOptions($method, $url, $headers, $body, $query);

        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request($method, $url, $options);
        } catch (ConnectException $e) {
            throw new \RuntimeException("Could not connect to {$url}: ".$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
            } else {
                throw new \RuntimeException("Request failed: ".$e->getMessage(), 0, $e);
            }
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $body       = (string) $response->getBody();

        $queries = [];
        if ($debug) {
            $raw = $response->getHeaderLine('X-Larafied-Debug-Queries');
            if ($raw !== '') {
                $queries = json_decode($raw, true) ?? [];
            }
        }

        return new ProxyResponse(
            status:      $response->getStatusCode(),
            headers:     $this->flattenHeaders($response),
            body:        $body,
            durationMs:  $durationMs,
            contentType: $response->getHeaderLine('Content-Type'),
            size:        strlen($body),
            queries:     $queries,
        );
    }

    private function buildOptions(
        string  $method,
        string  $url,
        array   $headers,
        mixed   $body,
        array   $query,
    ): array {
        $options = [
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => false,
            'headers'         => $headers,
        ];

        if (! empty($query)) {
            $options['query'] = $query;
        }

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = collect($headers)
                ->mapWithKeys(fn ($v, $k) => [strtolower($k) => $v])
                ->get('content-type', '');

            if (is_array($body)) {
                $options['json'] = $body;
            } elseif (str_contains(strtolower((string) $contentType), 'application/json')) {
                $options['json'] = json_decode($body, true) ?? $body;
            } else {
                $options['body'] = $body;
            }
        }

        return $options;
    }

    /**
     * @return array{0: array<string,string>, 1: array<string,string>}
     */
    private function applyAuth(array $auth, array $headers): array
    {
        $query = [];

        match ($auth['type'] ?? 'none') {
            'bearer' => $headers['Authorization'] = 'Bearer '.($auth['token'] ?? ''),
            'basic'  => $headers['Authorization'] = 'Basic '.base64_encode(
                ($auth['username'] ?? '').':'.($auth['password'] ?? '')
            ),
            'apikey' => match ($auth['in'] ?? 'header') {
                'query'  => $query[$auth['key'] ?? ''] = $auth['value'] ?? '',
                default  => $headers[$auth['key'] ?? '']  = $auth['value'] ?? '',
            },
            default  => null,
        };

        return [$headers, $query];
    }

    private function flattenHeaders(ResponseInterface $response): array
    {
        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return $headers;
    }
}
