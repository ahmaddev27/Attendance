<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Dual-mode login endpoint.
     *
     * - Web SPA: request arrives on the stateful path (Sanctum's
     *   `EnsureFrontendRequestsAreStateful` middleware started a
     *   session for the request). We authenticate against the session
     *   guard and return ONLY the user — no bearer token in the body,
     *   nothing readable by JS. The session id is stored in an
     *   httpOnly cookie the browser will send automatically on every
     *   subsequent request. XSS can no longer exfiltrate credentials.
     *
     * - Mobile: request arrives without a session. We mint a Sanctum
     *   personal access token exactly like before and return it. RN's
     *   Expo secure-store keeps that out of the browser attack surface.
     *
     * A stateful request is detected by `$request->hasSession()` — the
     * middleware sets up the session store only when the Origin matches
     * SANCTUM_STATEFUL_DOMAINS.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if ($this->isStatefulRequest($request)) {
            $user = $this->authService->loginStateful(
                identifier: $request->identifier(),
                password: (string) $request->validated('password'),
            );

            // Rotate the session id to close any pre-login fixation window.
            $request->session()->regenerate();

            return response()->json([
                'user' => new UserResource($user),
            ]);
        }

        $result = $this->authService->login(
            identifier: $request->identifier(),
            password: (string) $request->validated('password'),
            tokenName: $request->deviceName(),
        );

        return response()->json([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    /**
     * Tears down whichever credential authenticated the request.
     *
     * - Session-authed (web): logout the guard, invalidate the session
     *   record (row in `sessions`), rotate the CSRF token so the
     *   post-logout page can't replay any prior state.
     *
     * - Token-authed (mobile): revoke just the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->hasSession() && Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } elseif ($user) {
            $this->authService->logout($user);
        }

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Return the currently authenticated employee.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    /**
     * A request is "stateful" once Sanctum's middleware set up a
     * session store for it — meaning the Origin matched
     * SANCTUM_STATEFUL_DOMAINS. Mobile requests never trigger that.
     */
    private function isStatefulRequest(Request $request): bool
    {
        return $request->hasSession();
    }
}
