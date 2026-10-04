<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts the CSV / XLSX upload that the queued worker will process.
 * Mime + size limits mirror plan §9.1 — anything larger is a UI mistake
 * (the client splits big files), anything else is a wrong-format upload.
 */
class StoreCandidateImportRequest extends FormRequest
{
    private const MAX_UPLOAD_KILOBYTES = 5120; // 5 MB per plan §9.1.

    public function authorize(): bool
    {
        return $this->user()?->can('manage-candidates') === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'mimes:csv,txt,xlsx',
                'max:'.self::MAX_UPLOAD_KILOBYTES,
            ],
        ];
    }
}
