<?php

declare(strict_types=1);

namespace App\Modules\AI\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Services\MotivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotivationController extends Controller
{
    public function __construct(
        private readonly MotivationService $motivation,
    ) {}

    /**
     * GET /api/me/motivation
     *
     * Returns today's motivational line for the signed-in user.
     * Wrapped in `data` to keep envelope-shape parity with every
     * other endpoint in the API.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->motivation->for($request->user()),
        ]);
    }
}
