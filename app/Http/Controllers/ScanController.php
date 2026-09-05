<?php

namespace App\Http\Controllers;

use App\DataObjects\FraudCheckContext;
use App\Enums\FraudCheckStatus;
use App\Http\Requests\Public\RecordAttendanceRequest;
use App\Http\Requests\Public\SubmitLeaveRequestRequest;
use App\Services\AttendanceService;
use App\Services\LeaveService;

class ScanController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly LeaveService $leave,
    ) {}

    public function index()
    {
        return view('scan.index');
    }

    public function attendanceForm()
    {
        return view('scan.attendance');
    }

    public function attendancePreview(RecordAttendanceRequest $request)
    {
        $employee = $request->employee();
        $ctx = new FraudCheckContext(
            $request->ip(),
            $request->float('latitude'),
            $request->float('longitude'),
        );

        $fraud = $this->attendance->checkFraud($ctx);
        if (! $fraud->passed) {
            $message = $fraud->status === FraudCheckStatus::GpsFailed
                ? __('messages.scan_fraud_gps_failed')
                : __('messages.scan_fraud_ip_failed');

            return back()->withInput()->withErrors(['employee_number' => $message]);
        }

        $nextType = $this->attendance->previewNextType($employee);

        return view('scan.attendance-confirm', [
            'employee' => $employee,
            'nextType' => $nextType,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ]);
    }

    public function attendanceConfirm(RecordAttendanceRequest $request)
    {
        $employee = $request->employee();
        $ctx = new FraudCheckContext(
            $request->ip(),
            $request->float('latitude'),
            $request->float('longitude'),
        );

        $attendance = $this->attendance->record($employee, $ctx);

        return redirect()->route('scan.index')
            ->with('success', $attendance);
    }

    public function leaveSubmit(SubmitLeaveRequestRequest $request)
    {
        $employee = $request->employee();
        $this->leave->submit($employee, $request->validated());

        return redirect()->route('scan.index')->with('leave_success', true);
    }
}
