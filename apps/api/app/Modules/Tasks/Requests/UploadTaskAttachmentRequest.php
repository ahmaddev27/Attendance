<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadTaskAttachmentRequest extends FormRequest
{
    /**
     * Kept in sync with config/media-library.php's own max_file_size
     * (10MB) — this just lets an oversized upload fail fast with a clean
     * 422 instead of a MediaLibrary exception.
     */
    private const MAX_FILE_SIZE_KB = 10240;

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
                'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
            ],
        ];
    }
}
