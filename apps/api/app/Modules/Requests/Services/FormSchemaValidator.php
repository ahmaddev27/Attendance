<?php

declare(strict_types=1);

namespace App\Modules\Requests\Services;

use App\Models\Employee;
use App\Models\RequestType;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Validates a specific submission's form_data against its request type's
 * (already structurally valid — see
 * App\Modules\Workflow\Services\RequestTypeService::validateSchema())
 * form_schema. Field-level errors are reported under `form_data.<key>` so
 * they read naturally alongside normal FormRequest validation errors.
 */
class FormSchemaValidator
{
    /**
     * @param  array<string, mixed>  $formData
     */
    public function validate(RequestType $requestType, array $formData): void
    {
        $errors = [];

        /** @var array<int, array<string, mixed>> $schema */
        $schema = $requestType->form_schema;

        foreach ($schema as $field) {
            $key = $field['key'];
            $value = $formData[$key] ?? null;
            $required = (bool) ($field['required'] ?? false);
            $isBlank = $value === null || $value === '';

            if ($required && $isBlank) {
                $errors["form_data.$key"] = ["The {$field['label']} field is required."];

                continue;
            }

            if ($isBlank) {
                continue;
            }

            $error = $this->validateFieldValue($field, $value);

            if ($error !== null) {
                $errors["form_data.$key"] = [$error];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateFieldValue(array $field, mixed $value): ?string
    {
        return match ($field['type']) {
            'text', 'textarea', 'file' => is_string($value) ? null : "The {$field['label']} field must be text.",
            'number' => $this->validateNumber($field, $value),
            'date' => $this->validateDate($field, $value),
            'select' => $this->validateSelect($field, $value),
            'checkbox' => $this->validateCheckbox($field, $value),
            'employee' => $this->validateEmployee($field, $value),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateNumber(array $field, mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return "The {$field['label']} field must be a number.";
        }

        $number = (float) $value;

        if (isset($field['min']) && $number < (float) $field['min']) {
            return "The {$field['label']} field must be at least {$field['min']}.";
        }

        if (isset($field['max']) && $number > (float) $field['max']) {
            return "The {$field['label']} field must not exceed {$field['max']}.";
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateDate(array $field, mixed $value): ?string
    {
        if (! is_string($value)) {
            return "The {$field['label']} field must be a valid date.";
        }

        try {
            Carbon::parse($value);
        } catch (\Throwable) {
            return "The {$field['label']} field must be a valid date.";
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateSelect(array $field, mixed $value): ?string
    {
        $options = $field['options'] ?? [];

        // Strict, string-normalized comparison. Loose in_array() treated
        // e.g. "yes" == 0 as true — an admin who defined numeric options
        // would accept arbitrary text as a valid selection.
        $normalizedOptions = array_map(fn ($option) => (string) $option, $options);

        if (! in_array((string) $value, $normalizedOptions, true)) {
            return "The {$field['label']} field must be one of: ".implode(', ', $options).'.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateCheckbox(array $field, mixed $value): ?string
    {
        $isBooleanish = is_bool($value) || in_array($value, [0, 1, '0', '1'], true);

        return $isBooleanish ? null : "The {$field['label']} field must be true or false.";
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateEmployee(array $field, mixed $value): ?string
    {
        if (! is_numeric($value) || ! Employee::query()->whereKey((int) $value)->exists()) {
            return "The {$field['label']} field must reference a valid employee.";
        }

        return null;
    }
}
