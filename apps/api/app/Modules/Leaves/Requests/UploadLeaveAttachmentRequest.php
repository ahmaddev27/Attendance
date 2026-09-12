<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for POST /me/leaves/attachment. Kept intentionally narrow
 * (pdf + common image formats, 5MB cap) — a leave attachment is a
 * supporting document (sick note, official request), not a general file
 * bucket like task attachments. Rejecting oversized/wrong-type files here
 * lets the client show a clean 422 field error instead of a raw storage
 * exception surfacing.
 */
class UploadLeaveAttachmentRequest extends FormRequest
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
                'mimes:pdf,jpg,jpeg,png',
            ],
        ];
    }
}
