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

    public function send(ProxyRequest $request): ProxyResponse
    {
        $url = $request->validated('url');

        $this->ssrfGuard->validate($url);

        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request(
                $request->validated('method'),
                $url,
                $this->buildOptions($request),
            );
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

        return new ProxyResponse(
            status:      $response->getStatusCode(),
            headers:     $this->flattenHeaders($response),
            body:        $body,
            durationMs:  $durationMs,
            contentType: $response->getHeaderLine('Content-Type'),
            size:        strlen($body),
        );
    }

    private function buildOptions(ProxyRequest $request): array
    {
        $options = [
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => false,
            'headers'         => $request->validated('headers', []) ?? [],
        ];

        $body   = $request->validated('body');
        $method = strtoupper($request->validated('method'));

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = collect($request->validated('headers', []) ?? [])
                ->mapWithKeys(fn ($v, $k) => [strtolower($k) => $v])
                ->get('content-type', '');

            if (is_array($body)) {
                $options['json'] = $body;
            } elseif (str_contains(strtolower($contentType), 'application/json')) {
                $options['json'] = json_decode($body, true) ?? $body;
            } else {
                $options['body'] = $body;
            }
        }

        return $options;
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
