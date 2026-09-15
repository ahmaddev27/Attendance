<?php

declare(strict_types=1);

use App\Shared\Support\PhoneNumber;

/**
 * MTCSMS (Palestine) only delivers to international numbers, while admins
 * type phones the local way (0599 123 456). These cases pin the conversion.
 */
test('local numbers get the default country code instead of the trunk zero', function (string $typed, string $expected) {
    expect(PhoneNumber::toInternational($typed, '970'))->toBe($expected);
})->with([
    'jawwal' => ['0599123456', '970599123456'],
    'ooredoo with spaces' => ['056 912 3456', '970569123456'],
    'dashes and brackets' => ['(059) 912-3456', '970599123456'],
    'without the trunk zero' => ['599123456', '970599123456'],
]);

test('numbers already in international form keep their own country code', function (string $typed, string $expected) {
    expect(PhoneNumber::toInternational($typed, '970'))->toBe($expected);
})->with([
    'plus prefix' => ['+970 599 123 456', '970599123456'],
    'double zero prefix' => ['00970599123456', '970599123456'],
    'digits only' => ['970599123456', '970599123456'],
    'another country' => ['+962791234567', '962791234567'],
]);

test('the country code setting may carry a plus sign', function () {
    expect(PhoneNumber::toInternational('0599123456', '+972'))->toBe('972599123456');
});

test('without a country code the digits are sent as typed', function () {
    expect(PhoneNumber::toInternational('059-912-3456', ''))->toBe('0599123456')
        ->and(PhoneNumber::toInternational('059-912-3456', null))->toBe('0599123456');
});

test('anything that is not a phone number is rejected', function (string $typed) {
    expect(PhoneNumber::toInternational($typed, '970'))->toBeNull();
})->with([
    'empty' => [''],
    'blank' => ['   '],
    'letters' => ['call me'],
    'too short' => ['12345'],
    'too long' => ['+9705991234567890123'],
]);
