<?php

declare(strict_types=1);

namespace Larafied\Http\Controllers;

use Larafied\Services\RouteScanner;
use Illuminate\Http\JsonResponse;

final class RouteController extends Controller
{
    public function __construct(private readonly RouteScanner $scanner) {}

    public function index(): JsonResponse
    {
        return response()->json($this->scanner->scan());
    }
}
