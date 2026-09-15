<?php

declare(strict_types=1);

namespace App\Modules\Sms\Requests;

use App\Modules\Sms\Services\SmsLogService;
use Illuminate\Foundation\Http\FormRequest;

class ListSmsLogsRequest extends FormRequest
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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.SmsLogService::MAX_LIMIT],
        ];
    }
}
