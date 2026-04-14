<?php

declare(strict_types=1);

namespace Larafied\Services;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Larafied\Exceptions\LicenseException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LicenseValidator
{
    private const CACHE_FILE = 'license.json';

    public function __construct(
        private readonly Client $httpClient,
        private readonly string $storagePath,
        private readonly string $licenseKey,
        private readonly string $cloudUrl,
        private readonly int $gracePeriodDays,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function validate(string $key, string $domain): array
    {
        try {
            $response = $this->httpClient->post(
                $this->cloudUrl.'/api/v1/licenses/validate',
                ['json' => ['key' => $key, 'domain' => $domain]],
            );

            $data = json_decode((string) $response->getBody(), true);

            if (! $data['valid']) {
                throw new LicenseException($data['reason'] ?? 'invalid_key');
            }

            $result = [
                'key'          => $key,
                'tier'         => $data['tier'],
                'features'     => $data['features'],
                'expires_at'   => $data['expires_at'],
                'validated_at' => (new DateTime())->format(DateTime::ATOM),
                'grace_until'  => null,
            ];

            $this->cache($result);

            return $result;

        } catch (LicenseException $e) {
            throw $e;
        } catch (GuzzleException) {
            return $this->handleCloudFailure($key);
        }
    }

    public function cache(array $data): void
    {
        $payload = json_encode(array_diff_key($data, ['hmac' => true]));
        $hmac    = hash_hmac('sha256', $payload, $this->licenseKey);

        file_put_contents(
            $this->cachePath(),
            json_encode([...$data, 'hmac' => $hmac], JSON_PRETTY_PRINT),
        );
    }

    public function readCache(): ?array
    {
        $path = $this->cachePath();

        if (! file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);

        if (! is_array($data) || ! isset($data['hmac'])) {
            return null;
        }

        $storedHmac = $data['hmac'];
        $payload    = json_encode(array_diff_key($data, ['hmac' => true]));
        $expected   = hash_hmac('sha256', $payload, $this->licenseKey);

        if (! hash_equals($expected, $storedHmac)) {
            return null;
        }

        return $data;
    }

    public function isWithinGracePeriod(): bool
    {
        $cache = $this->readCache();

        if ($cache === null) {
            return false;
        }

        if ($cache['grace_until'] === null) {
            return true;
        }

        return new DateTime() <= new DateTime($cache['grace_until']);
    }

    /**
     * Returns true when 5–7 days offline (≤2 days remaining) — show UI notice.
     */
    public function graceWarning(): bool
    {
        $days = $this->daysUntilGraceExpiry();

        return $days !== null && $days >= 0 && $days <= 2;
    }

    /**
     * Returns true when 3–5 days offline (2–4 days remaining) — log only.
     */
    public function graceLogWarning(): bool
    {
        $days = $this->daysUntilGraceExpiry();

        return $days !== null && $days > 2 && $days <= 4;
    }

    private function daysUntilGraceExpiry(): ?int
    {
        $cache = $this->readCache();

        if ($cache === null || $cache['grace_until'] === null) {
            return null;
        }

        $graceUntil = new DateTime($cache['grace_until']);
        $now        = new DateTime();

        if ($now > $graceUntil) {
            return -1;
        }

        return (int) ceil(($graceUntil->getTimestamp() - $now->getTimestamp()) / 86400);
    }

    private function handleCloudFailure(string $key): array
    {
        $cached = $this->readCache();

        if ($cached === null) {
            throw new LicenseException('License validation failed and no cached license found.');
        }

        if ($cached['grace_until'] === null) {
            $cached['grace_until'] = (new DateTime())
                ->modify("+{$this->gracePeriodDays} days")
                ->format(DateTime::ATOM);

            $this->cache($cached);
        }

        if ($this->graceLogWarning()) {
            $this->logger->warning('Larafied: license cloud validation failed, operating in grace period.', [
                'grace_until' => $cached['grace_until'],
            ]);
        }

        return $cached;
    }

    private function cachePath(): string
    {
        return $this->storagePath.DIRECTORY_SEPARATOR.self::CACHE_FILE;
    }
}
