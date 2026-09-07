<?php

use App\Models\Employee;
use App\Models\RequestType;
use App\Modules\Requests\Services\FormSchemaValidator;
use Illuminate\Validation\ValidationException;

/**
 * Exercises FormSchemaValidator directly (no HTTP/routing involved) — a
 * plain unit test of a single service class. It lives under tests/Feature
 * rather than tests/Unit purely so it gets RefreshDatabase for the one
 * check (type=employee) that needs a real employees row to validate
 * against; every other case here touches no database at all.
 */
function schemaOf(array $field): RequestType
{
    return new RequestType(['form_schema' => [$field]]);
}

test('a required field that is missing from form_data fails validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'reason', 'label' => 'Reason', 'type' => 'text', 'required' => true]);

    expect(fn () => $validator->validate($requestType, []))
        ->toThrow(ValidationException::class);
});

test('an optional field that is missing from form_data passes validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'reason', 'label' => 'Reason', 'type' => 'text', 'required' => false]);

    $validator->validate($requestType, []);
})->throwsNoExceptions();

test('a text field receiving a non-string value fails validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]);

    expect(fn () => $validator->validate($requestType, ['name' => ['not', 'a', 'string']]))
        ->toThrow(ValidationException::class);
});

test('a number field below its min fails validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'min' => 10]);

    expect(fn () => $validator->validate($requestType, ['amount' => 5]))
        ->toThrow(ValidationException::class);
});

test('a number field above its max fails validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'max' => 10]);

    expect(fn () => $validator->validate($requestType, ['amount' => 20]))
        ->toThrow(ValidationException::class);
});

test('a number field within its bounds passes validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 12]);

    $validator->validate($requestType, ['amount' => 6]);
})->throwsNoExceptions();

test('a non-numeric value for a number field fails validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true]);

    expect(fn () => $validator->validate($requestType, ['amount' => 'abc']))
        ->toThrow(ValidationException::class);
});

test('an invalid date string fails validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'required' => true]);

    expect(fn () => $validator->validate($requestType, ['start_date' => 'not-a-date']))
        ->toThrow(ValidationException::class);
});

test('a valid date string passes validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'required' => true]);

    $validator->validate($requestType, ['start_date' => '2026-10-01']);
})->throwsNoExceptions();

test('a select value outside the declared options fails validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'choice', 'label' => 'Choice', 'type' => 'select', 'required' => true, 'options' => ['A', 'B']]);

    expect(fn () => $validator->validate($requestType, ['choice' => 'C']))
        ->toThrow(ValidationException::class);
});

test('a select value inside the declared options passes validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'choice', 'label' => 'Choice', 'type' => 'select', 'required' => true, 'options' => ['A', 'B']]);

    $validator->validate($requestType, ['choice' => 'B']);
})->throwsNoExceptions();

test('a non-boolean-ish checkbox value fails validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'agree', 'label' => 'Agree', 'type' => 'checkbox', 'required' => true]);

    expect(fn () => $validator->validate($requestType, ['agree' => 'maybe']))
        ->toThrow(ValidationException::class);
});

test('a boolean checkbox value passes validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'agree', 'label' => 'Agree', 'type' => 'checkbox', 'required' => true]);

    $validator->validate($requestType, ['agree' => true]);
})->throwsNoExceptions();

test('an employee field referencing a non-existent employee fails validation', function () {
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'reviewer_id', 'label' => 'Reviewer', 'type' => 'employee', 'required' => true]);

    expect(fn () => $validator->validate($requestType, ['reviewer_id' => 999999]))
        ->toThrow(ValidationException::class);
});

test('an employee field referencing a real employee passes validation', function () {
    $employee = Employee::factory()->create();
    $validator = new FormSchemaValidator;
    $requestType = schemaOf(['key' => 'reviewer_id', 'label' => 'Reviewer', 'type' => 'employee', 'required' => true]);

    $validator->validate($requestType, ['reviewer_id' => $employee->id]);
})->throwsNoExceptions();
