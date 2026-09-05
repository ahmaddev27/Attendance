<?php

namespace App\Livewire\Admin\Attendance;

use App\Models\Attendance;
use App\Models\Employee;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceList extends Component
{
    use WithPagination;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public ?int $employeeId = null;

    #[Url]
    public string $type = 'all'; // all | check_in | check_out

    #[Url]
    public string $fraudStatus = 'all'; // all | passed | skipped | gps_failed | ip_failed

    public function updating(string $property): void
    {
        if (in_array($property, ['from', 'to', 'employeeId', 'type', 'fraudStatus'], true)) {
            $this->resetPage();
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.attendance.attendance-list', [
            'records' => $this->attendanceQuery()->paginate(20),
            'employees' => Employee::orderBy('name')->get(),
        ]);
    }

    private function attendanceQuery()
    {
        return Attendance::query()
            ->with('employee')
            ->when($this->from, fn ($query) => $query->whereDate('scanned_at', '>=', $this->from))
            ->when($this->to, fn ($query) => $query->whereDate('scanned_at', '<=', $this->to))
            ->when($this->employeeId, fn ($query) => $query->where('employee_id', $this->employeeId))
            ->when($this->type !== 'all', fn ($query) => $query->where('type', $this->type))
            ->when($this->fraudStatus !== 'all', fn ($query) => $query->where('fraud_check_status', $this->fraudStatus))
            ->latest('scanned_at');
    }
}
