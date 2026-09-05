<?php

namespace App\Livewire\Admin;

use App\Enums\AttendanceType;
use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Overview extends Component
{
    #[Layout('layouts.app')]
    public function render()
    {
        $activeEmployees = Employee::where('is_active', true)->count();
        $presentToday = Attendance::whereDate('scanned_at', today())
            ->where('type', AttendanceType::CheckIn)
            ->distinct('employee_id')->count('employee_id');

        $pendingLeaves = LeaveRequest::where('status', LeaveStatus::Pending)->count();
        $approvedLeavesToday = LeaveRequest::where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->count();

        $absentToday = max(0, $activeEmployees - $presentToday - $approvedLeavesToday);

        $recentScans = Attendance::with('employee')->latest('scanned_at')->limit(5)->get();

        $last7Days = collect(range(0, 6))->map(function ($i) {
            $date = today()->subDays($i);

            return [
                'date' => $date->format('m-d'),
                'count' => Attendance::whereDate('scanned_at', $date)
                    ->where('type', AttendanceType::CheckIn)
                    ->distinct('employee_id')->count('employee_id'),
            ];
        })->reverse()->values();

        return view('livewire.admin.overview', [
            'activeEmployees' => $activeEmployees,
            'presentToday' => $presentToday,
            'absentToday' => $absentToday,
            'pendingLeaves' => $pendingLeaves,
            'approvedLeavesToday' => $approvedLeavesToday,
            'recentScans' => $recentScans,
            'last7Days' => $last7Days,
        ]);
    }
}
