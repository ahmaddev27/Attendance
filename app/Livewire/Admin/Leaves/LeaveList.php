<?php

namespace App\Livewire\Admin\Leaves;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveList extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'all'; // all | pending | approved | rejected

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public ?int $employeeId = null;

    public ?int $rejectingId = null;

    public string $rejectReason = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['status', 'from', 'to', 'employeeId'], true)) {
            $this->resetPage();
        }
    }

    public function approve(int $id, LeaveService $service): void
    {
        $leave = LeaveRequest::findOrFail($id);

        $service->approve($leave, auth()->user());

        session()->flash('success', __('تمت الموافقة على طلب الإجازة'));
    }

    public function startReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->rejectReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectReason = '';
        $this->resetErrorBag('rejectReason');
    }

    public function confirmReject(LeaveService $service): void
    {
        $this->validate([
            'rejectReason' => ['required', 'string', 'max:500'],
        ]);

        $leave = LeaveRequest::findOrFail($this->rejectingId);

        $service->reject($leave, auth()->user(), $this->rejectReason);

        $this->rejectingId = null;
        $this->rejectReason = '';

        session()->flash('success', __('تم رفض طلب الإجازة'));
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.leaves.leave-list', [
            'leaves' => $this->leavesQuery()->paginate(15),
            'employees' => Employee::orderBy('name')->get(),
        ]);
    }

    private function leavesQuery()
    {
        return LeaveRequest::query()
            ->with('employee')
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->from, fn ($query) => $query->whereDate('start_date', '>=', $this->from))
            ->when($this->to, fn ($query) => $query->whereDate('end_date', '<=', $this->to))
            ->when($this->employeeId, fn ($query) => $query->where('employee_id', $this->employeeId))
            ->latest();
    }
}
