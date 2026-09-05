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

        $last7DaysStart = today()->subDays(6);

        $attendanceByDay = Attendance::query()
            ->where('type', AttendanceType::CheckIn)
            ->whereDate('scanned_at', '>=', $last7DaysStart)
            ->selectRaw('DATE(scanned_at) as day, COUNT(DISTINCT employee_id) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        $last7Days = collect(range(0, 6))->map(function ($i) use ($attendanceByDay) {
            $date = today()->subDays($i);

            return [
                'date' => $date->format('m-d'),
                'count' => (int) ($attendanceByDay[$date->format('Y-m-d')] ?? 0),
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
