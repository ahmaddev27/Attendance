<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Employment contract upload: PDFs are the dominant case (admin scans the
 * signed contract and attaches it); common image formats are also accepted
 * for photo-captured one-page contracts. 10 MB cap because contracts are
 * typically multi-page scans and 5 MB cuts off ~8-page 300 DPI PDFs.
 */
class UploadEmploymentContractRequest extends FormRequest
{
    private const MAX_FILE_SIZE_KB = 10240; // 10 MB

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
                'mimes:pdf,jpg,jpeg,png,webp',
            ],
        ];
    }
}
