<?php

declare(strict_types=1);

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCompanyLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // 2 MiB cap: large enough for a decent raster logo, small
            // enough that an attacker (or an over-eager admin) can't fill
            // the disk by cycling uploads. MIME is pinned to the three
            // formats the frontend <img> renders without surprises.
            'logo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
