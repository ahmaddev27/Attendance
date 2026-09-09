<?php

declare(strict_types=1);

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Anyone may attempt to authenticate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // `identifier` is the new canonical field — accepts either the
            // numeric employee_number or an email address. Kept
            // `employee_number` as a legacy alias so existing clients
            // (the mobile app, the old web bundle) keep working while
            // they update.
            'identifier' => ['required_without:employee_number', 'string'],
            'employee_number' => ['required_without:identifier'],
            'password' => ['required', 'string'],
            // Optional client tag — used as the Sanctum token name so
            // relogins from one client only rotate that client's own
            // tokens (a mobile relogin never kills a web session).
            'device_name' => ['sometimes', 'string', 'max:64'],
        ];
    }

    /**
     * Normalize legacy `employee_number` payloads into the new `identifier`
     * shape so downstream code has one field to read.
     */
    public function identifier(): string
    {
        return (string) ($this->input('identifier') ?? $this->input('employee_number'));
    }

    /**
     * Sanctum token name for this login attempt. Defaults to `web` so
     * legacy clients (which never sent a device_name) keep grouping
     * under the same bucket the historical `createToken('web')` used.
     */
    public function deviceName(): string
    {
        $name = trim((string) $this->input('device_name', 'web'));

        return $name === '' ? 'web' : $name;
    }
}
