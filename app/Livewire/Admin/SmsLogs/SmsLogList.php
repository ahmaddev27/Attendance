<?php

namespace App\Livewire\Admin\SmsLogs;

use App\Models\SmsLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SmsLogList extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'all'; // all | sent | failed

    #[Url]
    public string $phone = '';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public function updating(string $property): void
    {
        if (in_array($property, ['status', 'phone', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.sms-logs.sms-log-list', [
            'logs' => $this->logsQuery()->paginate(20),
        ]);
    }

    private function logsQuery()
    {
        return SmsLog::query()
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->phone !== '', fn ($query) => $query->where('phone', 'like', "%{$this->phone}%"))
            ->when($this->from, fn ($query) => $query->whereDate('sent_at', '>=', $this->from))
            ->when($this->to, fn ($query) => $query->whereDate('sent_at', '<=', $this->to))
            ->latest('sent_at');
    }
}
