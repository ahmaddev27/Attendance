<?php

use App\Enums\AttendanceType;
use App\Enums\FraudCheckStatus;
use App\Enums\LeaveStatus;
use App\Enums\SmsStatus;

it('exposes attendance type values', function () {
    expect(AttendanceType::CheckIn->value)->toBe('check_in');
    expect(AttendanceType::CheckOut->value)->toBe('check_out');
});

it('exposes fraud check status values', function () {
    expect(FraudCheckStatus::Passed->value)->toBe('passed');
    expect(FraudCheckStatus::GpsFailed->value)->toBe('gps_failed');
    expect(FraudCheckStatus::IpFailed->value)->toBe('ip_failed');
    expect(FraudCheckStatus::Skipped->value)->toBe('skipped');
});

it('exposes leave status values', function () {
    expect(LeaveStatus::Pending->value)->toBe('pending');
    expect(LeaveStatus::Approved->value)->toBe('approved');
    expect(LeaveStatus::Rejected->value)->toBe('rejected');
});

it('exposes sms status values', function () {
    expect(SmsStatus::Sent->value)->toBe('sent');
    expect(SmsStatus::Failed->value)->toBe('failed');
});
