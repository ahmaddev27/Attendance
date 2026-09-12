<?php

declare(strict_types=1);

namespace App\Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Employees\Requests\UpdateMyPasswordRequest;
use App\Modules\Employees\Requests\UpdateMyProfileRequest;
use App\Modules\Employees\Resources\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Employee self-service profile endpoints — the caller can only ever act
 * on THEIR OWN row. Every method resolves the employee via
 * $request->user()->employee and never accepts an id from the client, so
 * horizontal-scope elevation (edit somebody else's row by guessing an id)
 * is impossible.
 *
 * See {@see EmployeeController} for the admin-facing equivalents; those
 * remain gated on the manage-users permission.
 */
class MyProfileController extends Controller
{
    /**
     * `GET /me/profile`
     *
     * Returns the caller's User (with roles + permissions) plus the linked
     * Employee row so the /profile page can render every read-only field
     * without a second round-trip.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user?->employee;

        if ($employee !== null) {
            $employee->load(['position', 'department', 'team', 'directManager', 'workSchedule']);
        }

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'employee' => $employee ? new EmployeeResource($employee) : null,
            ],
        ]);
    }

    /**
     * `PATCH /me/profile`
     *
     * The FormRequest whitelist already drops any HR-owned field before
     * validated() returns — but as a belt-and-braces guard we pluck ONLY
     * the fields we intend to write, so a future ruleset change can't
     * accidentally widen the surface without an explicit code edit here.
     */
    public function update(UpdateMyProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user?->employee;

        if ($employee === null) {
            // A user without a linked Employee row (bootstrap super-admin)
            // has no phone column to update. Fail loudly rather than 500
            // on the null dereference below.
            throw ValidationException::withMessages([
                'phone' => __('لا يوجد ملف موظف مرتبط بهذا الحساب.'),
            ]);
        }

        $validated = $request->validated();

        // Explicit whitelist — see class docblock. Only fields listed here
        // reach the update() call, so any accidental widening of
        // UpdateMyProfileRequest::rules() is not enough on its own to leak
        // a protected column.
        $payload = [];
        if (array_key_exists('phone', $validated)) {
            $payload['phone'] = $validated['phone'];
        }

        if (! empty($payload)) {
            $employee->update($payload);
            $employee->refresh();
        }

        $employee->load(['position', 'department', 'team', 'directManager', 'workSchedule']);

        return response()->json([
            'data' => [
                'user' => new UserResource($user->fresh()),
                'employee' => new EmployeeResource($employee),
            ],
        ]);
    }

    /**
     * `POST /me/password`
     *
     * Rotates the caller's password AND revokes every other Sanctum token
     * the account holds. The current token (if any) is preserved so the
     * request that succeeded stays authenticated for the rest of the
     * session — every OTHER device is logged out. For session-authed
     * (web SPA) callers `currentAccessToken()` returns a TransientToken
     * with no id, and the token table is filtered to bearer tokens only,
     * so the same "keep me, kill everyone else" semantic still holds.
     */
    public function updatePassword(UpdateMyPasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->forceFill([
            'password' => Hash::make((string) $validated['password']),
        ])->save();

        $currentToken = $user->currentAccessToken();
        // TransientToken (session-authed request) has no id — treat as null
        // so the delete below wipes EVERY bearer token the user still holds
        // (mobile handset stays signed in only when this same call was made
        // over a bearer token).
        $currentTokenId = ($currentToken && method_exists($currentToken, 'getKey'))
            ? $currentToken->getKey()
            : null;

        $revokeQuery = $user->tokens();
        if ($currentTokenId !== null) {
            $revokeQuery->where('id', '!=', $currentTokenId);
        }
        $revokeQuery->delete();

        return response()->json([
            'data' => ['ok' => true],
            'message' => __('تم تحديث كلمة السر.'),
        ]);
    }
}
