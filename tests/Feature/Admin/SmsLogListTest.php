<?php

use App\Enums\SmsStatus;
use App\Livewire\Admin\SmsLogs\SmsLogList;
use App\Models\{SmsLog, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('filters logs by status', function () {
    SmsLog::factory()->create(['status' => SmsStatus::Sent]);
    SmsLog::factory()->create(['status' => SmsStatus::Failed]);

    Livewire::test(SmsLogList::class)
        ->set('status', 'failed')
        ->assertViewHas('logs', fn ($items) => $items->count() === 1);
});
