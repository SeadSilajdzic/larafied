<?php

declare(strict_types=1);

namespace Larafied\Commands;

use Illuminate\Console\Command;
use Larafied\Services\AssertionRunner;
use Larafied\Services\RequestProxy;
use Larafied\Data\ProxyResponse;
use Larafied\Storage\WorkspaceStorage;

final class TestCommand extends Command
{
    protected $signature = 'larafied:test
        {--collection= : Name of the collection to run (runs all if omitted)}
        {--env=        : Name of the Larafied environment to use for variable resolution}
        {--format=table : Output format: table or junit}';

    protected $description = 'Run saved requests with assertions against your application';

    private bool $anyFailed = false;

    public function handle(
        WorkspaceStorage $storage,
        RequestProxy     $proxy,
        AssertionRunner  $assertionRunner,
    ): int {
        $collectionName = $this->option('collection');
        $format         = $this->option('format') ?? 'table';
        $envName        = $this->option('env');

        $envVars     = $this->resolveEnvVars($storage, $envName);
        $collections = $this->resolveCollections($storage, $collectionName);

        if ($collections === null) {
            return 2;
        }

        $allResults = [];

        foreach ($collections as $collection) {
            $requests = $storage->requestsForCollection($collection['id']);
            $suiteResults = [];

            foreach ($requests as $request) {
                $data = $request['data'] ?? [];
                if (is_string($data)) {
                    $data = json_decode($data, true) ?? [];
                }

                $assertions = $data['assertions'] ?? [];
                if (empty($assertions)) {
                    continue;
                }

                $response      = $this->sendRequest($proxy, $data, $envVars);
                $assertResults = $assertionRunner->evaluate($assertions, $response);

                foreach ($assertResults as $r) {
                    if (! $r['passed']) {
                        $this->anyFailed = true;
                    }
                }

                $suiteResults[] = [
                    'name'     => $request['name'],
                    'response' => $response,
                    'results'  => $assertResults,
                ];
            }

            $allResults[] = [
                'collection' => $collection,
                'requests'   => $suiteResults,
            ];
        }

        if ($format === 'junit') {
            $this->outputJunit($allResults);
        } else {
            $this->outputTable($allResults);
        }

        return $this->anyFailed ? 1 : 0;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveCollections(WorkspaceStorage $storage, ?string $name): ?array
    {
        $all = $storage->collections()->all();

        if ($name === null) {
            return $all;
        }

        $matched = array_values(array_filter($all, fn ($c) => $c['name'] === $name));

        if (empty($matched)) {
            $this->error("Collection \"{$name}\" not found.");
            return null;
        }

        return $matched;
    }

    private function resolveEnvVars(WorkspaceStorage $storage, ?string $envName): array
    {
        $environments = $storage->environments();

        $env = $envName !== null
            ? $environments->first(fn ($e) => $e['name'] === $envName)
            : $environments->first(fn ($e) => $e['is_active'] ?? false);

        if ($env === null) {
            return [];
        }

        $vars = [];
        foreach ($env['variables'] ?? [] as $v) {
            $vars[$v['key']] = $v['value'];
        }

        return $vars;
    }

    private function interpolate(string $text, array $vars): string
    {
        return (string) preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            fn ($m) => $vars[$m[1]] ?? $m[0],
            $text,
        );
    }

    private function sendRequest(RequestProxy $proxy, array $data, array $envVars): ProxyResponse
    {
        $method  = strtoupper($data['method'] ?? 'GET');
        $url     = $this->interpolate($data['url'] ?? '', $envVars);
        $headers = [];

        foreach ($data['headers'] ?? [] as $key => $value) {
            $headers[$this->interpolate((string) $key, $envVars)] = $this->interpolate((string) $value, $envVars);
        }

        $body = isset($data['body']) ? $this->interpolate((string) $data['body'], $envVars) : null;

        try {
            return $proxy->sendRaw($method, $url, $headers, $body ?: null);
        } catch (\RuntimeException $e) {
            return new ProxyResponse(
                status:      0,
                headers:     [],
                body:        $e->getMessage(),
                durationMs:  0,
                contentType: '',
                size:        0,
            );
        }
    }

    // ── Output ────────────────────────────────────────────────────────────────

    private function outputTable(array $allResults): void
    {
        foreach ($allResults as $suite) {
            $this->line('');
            $this->line('<fg=white;options=bold>' . $suite['collection']['name'] . '</>');

            if (empty($suite['requests'])) {
                $this->line('  <fg=gray>No requests with assertions.</>');
                continue;
            }

            $rows = [];
            foreach ($suite['requests'] as $requestResult) {
                $passed = collect($requestResult['results'])->every(fn ($r) => $r['passed']);
                $icon   = $passed ? '<fg=green>✓</>' : '<fg=red>✗</>';
                $status = $requestResult['response']->status ?: 'ERR';

                $rows[] = [$icon, $requestResult['name'], $status, $passed ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'];

                if (! $passed) {
                    foreach ($requestResult['results'] as $r) {
                        if (! $r['passed']) {
                            $rows[] = ['', '<fg=gray>  → ' . $r['message'] . '</>', '', ''];
                        }
                    }
                }
            }

            $this->table(['', 'Request', 'Status', 'Result'], $rows);
        }
    }

    private function outputJunit(array $allResults): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<testsuites>' . PHP_EOL;

        foreach ($allResults as $suite) {
            $tests    = count($suite['requests']);
            $failures = 0;
            $cases    = '';

            foreach ($suite['requests'] as $r) {
                $allPassed = collect($r['results'])->every(fn ($res) => $res['passed']);
                if (! $allPassed) {
                    $failures++;
                }

                $cases .= '    <testcase name="' . htmlspecialchars($r['name']) . '" status="' . $r['response']->status . '">' . PHP_EOL;

                foreach ($r['results'] as $res) {
                    if (! $res['passed']) {
                        $cases .= '      <failure message="' . htmlspecialchars($res['message']) . '"/>' . PHP_EOL;
                    }
                }

                $cases .= '    </testcase>' . PHP_EOL;
            }

            $xml .= '  <testsuite name="' . htmlspecialchars($suite['collection']['name']) . '" tests="' . $tests . '" failures="' . $failures . '">' . PHP_EOL;
            $xml .= $cases;
            $xml .= '  </testsuite>' . PHP_EOL;
        }

        $xml .= '</testsuites>';
        $this->line($xml);
    }
}
