<?php

namespace App\Livewire\Admin\Settings;

use App\Services\SettingsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

class SettingsForm extends Component
{
    public bool $gpsEnabled = false;

    public ?float $officeLat = null;

    public ?float $officeLng = null;

    public int $geofenceRadius = 100;

    public bool $ipEnabled = false;

    public string $ipWhitelistText = '';

    public string $smsUsername = '';

    public string $smsPassword = '';

    public string $smsSender = '';

    public int $employeeNumberStart = 1001;

    public function mount(SettingsService $settings): void
    {
        $this->gpsEnabled = (bool) $settings->get('gps_enabled', false);
        $this->officeLat = $settings->get('office_lat');
        $this->officeLng = $settings->get('office_lng');
        $this->geofenceRadius = (int) $settings->get('geofence_radius_meters', 100);

        $this->ipEnabled = (bool) $settings->get('ip_enabled', false);
        $this->ipWhitelistText = implode("\n", (array) $settings->get('ip_whitelist', []));

        $this->smsUsername = (string) $settings->get('sms_username', '');
        $this->smsPassword = (string) $settings->get('sms_password', '');
        $this->smsSender = (string) $settings->get('sms_sender', '');

        $this->employeeNumberStart = (int) $settings->get('employee_number_start', 1001);
    }

    public function rules(): array
    {
        return [
            'gpsEnabled' => ['boolean'],
            'officeLat' => ['nullable', 'numeric', 'between:-90,90'],
            'officeLng' => ['nullable', 'numeric', 'between:-180,180'],
            'geofenceRadius' => ['integer', 'min:1', 'max:10000'],
            'ipEnabled' => ['boolean'],
            'ipWhitelistText' => ['nullable', 'string'],
            'smsUsername' => ['nullable', 'string', 'max:150'],
            'smsPassword' => ['nullable', 'string', 'max:150'],
            'smsSender' => ['nullable', 'string', 'max:150'],
            'employeeNumberStart' => ['integer', 'min:1'],
        ];
    }

    public function save(SettingsService $settings): void
    {
        $this->validate();

        $settings->set('gps_enabled', $this->gpsEnabled, 'boolean');
        $settings->set('office_lat', $this->officeLat, 'number');
        $settings->set('office_lng', $this->officeLng, 'number');
        $settings->set('geofence_radius_meters', $this->geofenceRadius, 'number');

        $settings->set('ip_enabled', $this->ipEnabled, 'boolean');
        $settings->set('ip_whitelist', $this->parseIpWhitelist(), 'json');

        $settings->set('sms_username', $this->smsUsername, 'string');
        $settings->set('sms_password', $this->smsPassword, 'string');
        $settings->set('sms_sender', $this->smsSender, 'string');

        $settings->set('employee_number_start', $this->employeeNumberStart, 'number');

        session()->flash('success', __('تم حفظ الإعدادات بنجاح'));
    }

    /**
     * Splits the textarea input into a clean list of IP addresses,
     * dropping blank lines produced by trailing newlines or extra spacing.
     *
     * @return array<int, string>
     */
    private function parseIpWhitelist(): array
    {
        $lines = preg_split('/\r?\n/', $this->ipWhitelistText) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.settings.settings-form');
    }
}
