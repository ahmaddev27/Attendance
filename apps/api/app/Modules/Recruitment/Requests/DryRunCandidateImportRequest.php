<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Same shape as StoreCandidateImportRequest — a separate class so the
 * controller signature spells out the two intents and so future tweaks
 * (e.g. a larger size cap on dry-run) can diverge without touching the
 * commit path.
 */
class DryRunCandidateImportRequest extends FormRequest
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
