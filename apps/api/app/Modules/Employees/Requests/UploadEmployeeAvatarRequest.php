<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Avatar upload: small raster images only. SVG is excluded on purpose —
 * the file is served from the public disk, and SVG can carry script.
 */
class UploadEmployeeAvatarRequest extends FormRequest
{
    private const MAX_FILE_SIZE_KB = 2048; // 2 MB

    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'max:'.self::MAX_FILE_SIZE_KB,
            ],
        ];
    }
}
