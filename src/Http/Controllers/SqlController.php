<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Larafied\Services\FeatureFlags;

final class SqlController extends Controller
{
    public function __construct(private readonly FeatureFlags $featureFlags) {}

    public function execute(Request $request): JsonResponse
    {
        if (! $this->featureFlags->isEnabled('sql_console')) {
            return response()->json([
                'error'   => 'SQL Console requires a Pro license.',
                'upgrade' => true,
            ], 403);
        }

        $validated = $request->validate([
            'sql'        => ['required', 'string', 'max:10000'],
            'connection' => ['nullable', 'string', 'max:100'],
        ]);

        $sql = trim($validated['sql']);

        if (! preg_match('/^\s*SELECT\b/i', $sql)) {
            return response()->json(['error' => 'Only SELECT statements are allowed.'], 422);
        }

        try {
            $conn  = $validated['connection'] ?? null;
            $db    = $conn ? DB::connection($conn) : DB::connection();
            $start = microtime(true);
            $rows  = $db->select($sql);
            $ms    = round((microtime(true) - $start) * 1000, 2);

            return response()->json([
                'rows'        => $rows,
                'count'       => count($rows),
                'duration_ms' => $ms,
                'connection'  => $db->getName(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
