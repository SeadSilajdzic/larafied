<?php

declare(strict_types=1);

namespace Larafied\Services;

use Larafied\Exceptions\SsrfException;

final class SsrfGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const BLOCKED_HOSTS = [
        '169.254.169.254',          // AWS / Azure instance metadata
        'metadata.google.internal', // GCP metadata
        '100.100.100.200',          // Alibaba Cloud metadata
    ];

    public function __construct(
        private readonly bool $allowPrivateHosts = false,
    ) {}

    public function validate(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['host'])) {
            throw new SsrfException("Invalid URL: {$url}");
        }

        $scheme = strtolower($parsed['scheme'] ?? '');

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new SsrfException("Scheme not allowed: '{$scheme}'. Only http and https are permitted.");
        }

        $host = strtolower($parsed['host']);

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new SsrfException("Host is blocked: {$host}");
        }

        if ($this->allowPrivateHosts) {
            return;
        }

        $ip = $this->resolveToIp($host);

        if ($ip !== null && $this->isPrivateOrReservedIp($ip)) {
            throw new SsrfException("Requests to private or reserved IP ranges are not allowed.");
        }
    }

    private function resolveToIp(string $host): ?string
    {
        // parse_url keeps brackets on IPv6 addresses — strip them
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        // Already an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        // Resolve hostname
        $resolved = gethostbyname($host);

        // gethostbyname returns the original host if resolution fails
        return $resolved !== $host ? $resolved : null;
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->isPrivateOrReservedIpv6($ip);
        }

        return true;
    }

    private function isPrivateOrReservedIpv6(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return true;
        }

        /** @var array<int, int> $bytes */
        $bytes = array_values(unpack('C16', $packed));

        // ::1 (loopback) and :: (unspecified)
        $firstFifteen = array_sum(array_slice($bytes, 0, 15));
        if ($firstFifteen === 0 && in_array($bytes[15], [0, 1], true)) {
            return true;
        }

        // fc00::/7 — Unique Local (private), covers fc00:: and fd00::
        if (($bytes[0] & 0xFE) === 0xFC) {
            return true;
        }

        // fe80::/10 — Link-local
        if ($bytes[0] === 0xFE && ($bytes[1] & 0xC0) === 0x80) {
            return true;
        }

        // ::ffff:0:0/96 — IPv4-mapped: validate the embedded IPv4 address
        $isIpv4Mapped = $bytes[0] === 0 && $bytes[1] === 0 && $bytes[2] === 0  && $bytes[3] === 0  &&
                        $bytes[4] === 0 && $bytes[5] === 0 && $bytes[6] === 0  && $bytes[7] === 0  &&
                        $bytes[8] === 0 && $bytes[9] === 0 && $bytes[10] === 0xFF && $bytes[11] === 0xFF;

        if ($isIpv4Mapped) {
            $embeddedIpv4 = implode('.', array_slice($bytes, 12, 4));
            return $this->isPrivateOrReservedIp($embeddedIpv4);
        }

        // 2001:db8::/32 — Documentation range
        if ($bytes[0] === 0x20 && $bytes[1] === 0x01 && $bytes[2] === 0x0D && $bytes[3] === 0xB8) {
            return true;
        }

        return false;
    }
}
