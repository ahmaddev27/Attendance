<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Services;

use App\Models\RequestType;
use App\Modules\Workflow\Repositories\RequestTypeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class RequestTypeService
{
    public function __construct(
        private readonly RequestTypeRepository $requestTypes,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->requestTypes->paginate($filters, $perPage);
    }

    public function find(int $id): RequestType
    {
        return $this->requestTypes->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): RequestType
    {
        $this->validateSchema($data['form_schema'] ?? []);

        return $this->requestTypes->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(RequestType $requestType, array $data): RequestType
    {
        if (array_key_exists('form_schema', $data)) {
            $this->validateSchema($data['form_schema']);
        }

        return $this->requestTypes->update($requestType, $data);
    }

    /**
     * Soft-deletes the type, unless it has ever been used — deleting one
     * with existing requests would orphan historical data (or hit the
     * requests.request_type_id restrictOnDelete constraint). Deactivating
     * via is_active is the correct way to retire a used type.
     */
    public function delete(RequestType $requestType): void
    {
        if ($requestType->requests()->exists()) {
            throw ValidationException::withMessages([
                'request_type' => 'This request type cannot be deleted because it already has requests. Deactivate it instead.',
            ]);
        }

        $this->requestTypes->delete($requestType);
    }

    /**
     * Validates the *shape* of a form_schema definition — the structural
     * rules a request type's designer must follow — as opposed to
     * App\Modules\Requests\Services\FormSchemaValidator, which validates a
     * specific submission's form_data against an already-valid schema.
     *
     * @param  array<int, mixed>  $schema
     */
    public function validateSchema(array $schema): void
    {
        $errors = [];
        $seenKeys = [];

        foreach ($schema as $index => $field) {
            if (! is_array($field)) {
                $errors["form_schema.$index"] = ['Each form schema field must be an object.'];

                continue;
            }

            $key = $field['key'] ?? null;

            if (! is_string($key) || $key === '') {
                $errors["form_schema.$index.key"] = ['Each field requires a non-empty string key.'];
            } elseif (isset($seenKeys[$key])) {
                $errors["form_schema.$index.key"] = ["Duplicate field key \"{$key}\" in form schema."];
            } else {
                $seenKeys[$key] = true;
            }

            if (! is_string($field['label'] ?? null) || $field['label'] === '') {
                $errors["form_schema.$index.label"] = ['Each field requires a non-empty string label.'];
            }

            $type = $field['type'] ?? null;

            if (! in_array($type, RequestType::FIELD_TYPES, true)) {
                $errors["form_schema.$index.type"] = ['Field type must be one of: '.implode(', ', RequestType::FIELD_TYPES).'.'];

                continue;
            }

            if (isset($field['required']) && ! is_bool($field['required'])) {
                $errors["form_schema.$index.required"] = ['The required flag must be a boolean.'];
            }

            if ($type === 'select') {
                $options = $field['options'] ?? null;

                if (! is_array($options) || $options === []) {
                    $errors["form_schema.$index.options"] = ['Select fields require a non-empty options array.'];
                }
            }

            if ($type === 'number') {
                foreach (['min', 'max'] as $bound) {
                    if (isset($field[$bound]) && ! is_numeric($field[$bound])) {
                        $errors["form_schema.$index.$bound"] = ["The {$bound} value must be numeric."];
                    }
                }

                if (isset($field['min'], $field['max']) && is_numeric($field['min']) && is_numeric($field['max']) && $field['min'] > $field['max']) {
                    $errors["form_schema.$index.min"] = ['min cannot be greater than max.'];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
