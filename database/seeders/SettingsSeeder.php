<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'gps_enabled', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'office_lat', 'value' => null, 'type' => 'number'],
            ['key' => 'office_lng', 'value' => null, 'type' => 'number'],
            ['key' => 'geofence_radius_meters', 'value' => '100', 'type' => 'number'],
            ['key' => 'ip_enabled', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'ip_whitelist', 'value' => json_encode([]), 'type' => 'json'],
            ['key' => 'sms_username', 'value' => '', 'type' => 'string'],
            ['key' => 'sms_password', 'value' => '', 'type' => 'string'],
            ['key' => 'sms_sender', 'value' => '', 'type' => 'string'],
            ['key' => 'employee_number_start', 'value' => '1001', 'type' => 'number'],
        ];

        foreach ($defaults as $row) {
            Setting::updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
