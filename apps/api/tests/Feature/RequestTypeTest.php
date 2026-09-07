<?php

use App\Models\Employee;
use App\Models\Request as RequestModel;
use App\Models\RequestType;
use App\Models\Workflow;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

test('an admin can create a request type with a valid form schema', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();

    $response = $this->postJson('/api/request-types', [
        'name' => 'طلب سلفة',
        'code' => 'advance',
        'workflow_id' => $workflow->id,
        'form_schema' => [
            ['key' => 'amount', 'label' => 'المبلغ', 'type' => 'number', 'required' => true, 'min' => 1],
            ['key' => 'reason', 'label' => 'السبب', 'type' => 'textarea', 'required' => true],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('data.code', 'advance');
    $this->assertDatabaseHas('request_types', ['code' => 'advance']);
});

test('an admin can list request types', function () {
    $this->actingAsSuperAdmin();
    RequestType::factory()->count(2)->create();

    $response = $this->getJson('/api/request-types');

    $response->assertOk()->assertJsonCount(2, 'data');
});

test('an admin can view a single request type', function () {
    $this->actingAsSuperAdmin();
    $requestType = RequestType::factory()->create();

    $response = $this->getJson("/api/request-types/{$requestType->id}");

    $response->assertOk()->assertJsonPath('data.id', $requestType->id);
});

test('an admin can update a request type', function () {
    $this->actingAsSuperAdmin();
    $requestType = RequestType::factory()->create(['is_active' => true]);

    $response = $this->putJson("/api/request-types/{$requestType->id}", ['is_active' => false]);

    $response->assertOk()->assertJsonPath('data.is_active', false);
});

test('an admin can delete a request type that has never been used', function () {
    $this->actingAsSuperAdmin();
    $requestType = RequestType::factory()->create();

    $response = $this->deleteJson("/api/request-types/{$requestType->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('request_types', ['id' => $requestType->id]);
});

test('deleting a request type that already has requests is rejected', function () {
    $this->actingAsSuperAdmin();
    $requestType = RequestType::factory()->create();
    RequestModel::factory()->create(['request_type_id' => $requestType->id, 'employee_id' => Employee::factory()]);

    $response = $this->deleteJson("/api/request-types/{$requestType->id}");

    $response->assertUnprocessable()->assertJsonValidationErrors(['request_type']);
});

test('a request type code must be unique', function () {
    $this->actingAsSuperAdmin();
    RequestType::factory()->create(['code' => 'duplicate']);
    $workflow = Workflow::factory()->create();

    $response = $this->postJson('/api/request-types', [
        'name' => 'Another',
        'code' => 'duplicate',
        'workflow_id' => $workflow->id,
        'form_schema' => [],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

test('a form schema field with an unknown type is rejected', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();

    $response = $this->postJson('/api/request-types', [
        'name' => 'Bad schema',
        'code' => 'bad-schema-type',
        'workflow_id' => $workflow->id,
        'form_schema' => [
            ['key' => 'foo', 'label' => 'Foo', 'type' => 'not-a-real-type'],
        ],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['form_schema.0.type']);
});

test('a select field without options is rejected', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();

    $response = $this->postJson('/api/request-types', [
        'name' => 'Bad select',
        'code' => 'bad-select',
        'workflow_id' => $workflow->id,
        'form_schema' => [
            ['key' => 'choice', 'label' => 'Choice', 'type' => 'select'],
        ],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['form_schema.0.options']);
});

test('a form schema with duplicate field keys is rejected', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();

    $response = $this->postJson('/api/request-types', [
        'name' => 'Duplicate keys',
        'code' => 'dup-keys',
        'workflow_id' => $workflow->id,
        'form_schema' => [
            ['key' => 'reason', 'label' => 'Reason', 'type' => 'text'],
            ['key' => 'reason', 'label' => 'Reason again', 'type' => 'text'],
        ],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['form_schema.1.key']);
});

test('a number field with min greater than max is rejected', function () {
    $this->actingAsSuperAdmin();
    $workflow = Workflow::factory()->create();

    $response = $this->postJson('/api/request-types', [
        'name' => 'Bad range',
        'code' => 'bad-range',
        'workflow_id' => $workflow->id,
        'form_schema' => [
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'min' => 10, 'max' => 5],
        ],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['form_schema.0.min']);
});
