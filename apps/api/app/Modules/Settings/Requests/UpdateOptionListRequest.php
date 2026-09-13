<?php

declare(strict_types=1);

namespace App\Modules\Settings\Requests;

use App\Shared\Enums\OptionList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Full replacement of one picker list. Authorisation is the route's
 * `manage-settings` middleware; this class only guards the shape. The
 * code pattern depends on which list is being edited, so it is read
 * from the bound `{list}` route parameter.
 */
class UpdateOptionListRequest extends FormRequest
{
    private const MAX_ITEMS = 100;

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
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['required', 'array:value,label'],
            'items.*.value' => ['required', 'string', 'max:50', 'regex:'.$this->optionList()->valuePattern(), 'distinct:ignore_case'],
            'items.*.label' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'القائمة يجب أن تحتوي على عنصر واحد على الأقل.',
            'items.min' => 'القائمة يجب أن تحتوي على عنصر واحد على الأقل.',
            'items.max' => 'الحد الأقصى '.self::MAX_ITEMS.' عنصر في القائمة.',
            'items.*.value.required' => 'الرمز مطلوب لكل عنصر.',
            'items.*.value.max' => 'الرمز يجب ألا يتجاوز 50 حرفاً.',
            'items.*.value.regex' => $this->optionList()->valueHint(),
            'items.*.value.distinct' => 'الرمز مكرر في القائمة.',
            'items.*.label.required' => 'الاسم الظاهر مطلوب لكل عنصر.',
            'items.*.label.max' => 'الاسم الظاهر يجب ألا يتجاوز 100 حرف.',
        ];
    }

    /**
     * Trim both fields and uppercase currency codes before the pattern
     * check, so "usd " is accepted as USD instead of bouncing the form.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $uppercase = $this->optionList() === OptionList::Currencies;

        $this->merge([
            'items' => array_map(function (mixed $item) use ($uppercase): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                $value = $item['value'] ?? null;
                $label = $item['label'] ?? null;

                if (is_string($value)) {
                    $value = trim($value);
                    $value = $uppercase ? strtoupper($value) : $value;
                }

                return [
                    'value' => $value,
                    'label' => is_string($label) ? trim($label) : $label,
                ];
            }, $items),
        ]);
    }

    private function optionList(): OptionList
    {
        $list = $this->route('list');

        return $list instanceof OptionList ? $list : OptionList::from((string) $list);
    }
}
