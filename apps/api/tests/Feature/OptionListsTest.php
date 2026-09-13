<?php

use App\Models\Setting;
use App\Models\User;
use App\Shared\Enums\OptionList;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Concerns\CreatesSuperAdmin;

uses(CreatesSuperAdmin::class);

function actingAsPlainUser(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}

test('any signed-in user reads every picker list with code defaults', function () {
    actingAsPlainUser();

    $response = $this->getJson('/api/option-lists')->assertOk();

    foreach (OptionList::cases() as $list) {
        $response->assertJsonPath("data.{$list->value}", $list->defaults());
    }

    $response->assertJsonPath('data.currencies.0', ['value' => 'USD', 'label' => 'دولار أمريكي']);
});

test('picker lists require authentication', function () {
    $this->getJson('/api/option-lists')->assertUnauthorized();
});

test('an admin replaces a list and every reader sees the new items', function () {
    $this->actingAsSuperAdmin();

    $items = [
        ['value' => 'JOD', 'label' => 'دينار أردني'],
        ['value' => 'USD', 'label' => 'دولار'],
    ];

    $this->putJson('/api/admin/option-lists/currencies', ['items' => $items])
        ->assertOk()
        ->assertJsonPath('data.key', 'currencies')
        ->assertJsonPath('data.group', 'general')
        ->assertJsonPath('data.items', $items);

    $this->assertDatabaseHas('settings', ['key' => 'general.currencies', 'group' => 'general']);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'settings', 'description' => 'option_list_updated']);

    $this->getJson('/api/option-lists')->assertOk()->assertJsonPath('data.currencies', $items);
});

test('currency codes are trimmed and uppercased before validation', function () {
    $this->actingAsSuperAdmin();

    $this->putJson('/api/admin/option-lists/currencies', [
        'items' => [['value' => ' egp ', 'label' => ' جنيه مصري ']],
    ])
        ->assertOk()
        ->assertJsonPath('data.items', [['value' => 'EGP', 'label' => 'جنيه مصري']]);
});

test('a malformed code is rejected with the list specific hint', function () {
    $this->actingAsSuperAdmin();

    $this->putJson('/api/admin/option-lists/currencies', [
        'items' => [['value' => 'DOLLAR', 'label' => 'دولار']],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors', ['items.0.value' => [OptionList::Currencies->valueHint()]]);

    $this->putJson('/api/admin/option-lists/company_sizes', [
        'items' => [['value' => 'big', 'label' => 'كبيرة']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.value']);
});

test('duplicate codes and empty lists are rejected', function () {
    $this->actingAsSuperAdmin();

    $this->putJson('/api/admin/option-lists/industries', [
        'items' => [
            ['value' => 'technology', 'label' => 'تقنية'],
            ['value' => 'Technology', 'label' => 'تقنية مكررة'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.value', 'items.1.value']);

    $this->putJson('/api/admin/option-lists/industries', ['items' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

test('an unknown list key is a 404', function () {
    $this->actingAsSuperAdmin();

    $this->putJson('/api/admin/option-lists/colours', [
        'items' => [['value' => 'red', 'label' => 'أحمر']],
    ])->assertNotFound();
});

test('editing lists requires manage-settings', function () {
    actingAsPlainUser();

    $this->getJson('/api/admin/option-lists')->assertForbidden();
    $this->putJson('/api/admin/option-lists/currencies', [
        'items' => [['value' => 'USD', 'label' => 'دولار']],
    ])->assertForbidden();
    $this->deleteJson('/api/admin/option-lists/currencies')->assertForbidden();
});

test('the admin index describes every list for the editor', function () {
    $this->actingAsSuperAdmin();

    $this->getJson('/api/admin/option-lists')
        ->assertOk()
        ->assertJsonCount(count(OptionList::cases()), 'data')
        ->assertJsonPath('data.1.key', 'lead_sources')
        ->assertJsonPath('data.1.group', 'recruitment')
        ->assertJsonPath('data.1.label', OptionList::LeadSources->label())
        ->assertJsonPath('data.1.value_hint', OptionList::LeadSources->valueHint());
});

test('resetting a list drops the override and serves the defaults again', function () {
    $this->actingAsSuperAdmin();

    $this->putJson('/api/admin/option-lists/education_levels', [
        'items' => [['value' => 'bachelor', 'label' => 'جامعي']],
    ])->assertOk();

    $this->deleteJson('/api/admin/option-lists/education_levels')
        ->assertOk()
        ->assertJsonPath('data.items', OptionList::EducationLevels->defaults());

    $this->assertDatabaseMissing('settings', ['key' => 'recruitment.education_levels']);
    $this->getJson('/api/option-lists')
        ->assertJsonPath('data.education_levels', OptionList::EducationLevels->defaults());
});

test('legacy rows that stored bare codes are served with their default labels', function () {
    actingAsPlainUser();

    Setting::create([
        'key' => 'recruitment.industries',
        'value' => json_encode(['technology', 'agritech']),
        'encrypted' => false,
        'group' => 'recruitment',
    ]);

    $this->getJson('/api/option-lists')
        ->assertOk()
        ->assertJsonPath('data.industries', [
            ['value' => 'technology', 'label' => 'تقنية المعلومات'],
            ['value' => 'agritech', 'label' => 'agritech'],
        ]);
});

test('a corrupt row falls back to the defaults instead of an empty picker', function () {
    actingAsPlainUser();

    Setting::create([
        'key' => 'general.currencies',
        'value' => '{not json',
        'encrypted' => false,
        'group' => 'general',
    ]);

    $this->getJson('/api/option-lists')
        ->assertOk()
        ->assertJsonPath('data.currencies', OptionList::Currencies->defaults());
});
