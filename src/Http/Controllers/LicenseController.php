<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Larafied\Exceptions\LicenseException;
use Larafied\Http\Requests\ActivateLicenseRequest;
use Larafied\Services\FeatureFlags;
use Larafied\Services\LicenseValidator;

final class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseValidator $licenseValidator,
        private readonly FeatureFlags $featureFlags,
    ) {}

    public function show(): JsonResponse
    {
        $cache = $this->licenseValidator->readCache();

        return response()->json([
            'tier'          => $this->featureFlags->tier(),
            'features'      => $this->featureFlags->features(),
            'validated_at'  => $cache['validated_at'] ?? null,
            'grace_until'   => $cache['grace_until'] ?? null,
            'grace_warning' => $this->licenseValidator->graceWarning(),
        ]);
    }

    public function activate(ActivateLicenseRequest $request): JsonResponse
    {
        $domain = $request->validated('domain') ?? $request->getHost();

        try {
            $result = $this->licenseValidator->validate(
                $request->validated('key'),
                $domain,
            );

            return response()->json([
                'tier'         => $result['tier'],
                'features'     => $result['features'],
                'validated_at' => $result['validated_at'],
                'grace_until'  => $result['grace_until'],
            ]);
        } catch (LicenseException $e) {
            return response()->json([
                'message' => 'License activation failed.',
                'reason'  => $e->getMessage(),
            ], 422);
        }
    }
}
