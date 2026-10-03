<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * National ID scan upload: images only, 5 MB cap. PDFs are allowed too
 * because photocopy shops often email scans as PDFs — rejecting them
 * would push admins to open a photo editor just to re-save as PNG.
 */
class UploadNationalIdImageRequest extends FormRequest
{
    private const MAX_FILE_SIZE_KB = 5120; // 5 MB

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
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_FILE_SIZE_KB,
                'mimes:jpg,jpeg,png,webp,pdf',
            ],
        ];
    }
}
