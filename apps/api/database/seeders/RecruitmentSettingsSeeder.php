<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the picker lists that the Recruitment UI reads from
 * `settings.recruitment.*`. Keeping them here rather than in code
 * enums lets an admin edit the effective values from the admin UI
 * without a code deploy.
 *
 * Idempotent: firstOrCreate on `key`, so an admin's edits made after
 * the first seed run are never clobbered by a rerun. To reset a list
 * to defaults, an admin deletes the row and reruns this seeder — the
 * fixture then reappears.
 */
class RecruitmentSettingsSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const DEFAULTS = [
        'recruitment.lead_sources' => [
            'linkedin', 'referral', 'website', 'existing_client', 'partner',
            'email', 'direct_outreach', 'event', 'other',
        ],
        'recruitment.lead_statuses' => [
            'new', 'contacted', 'meeting_scheduled', 'meeting_completed',
            'qualified', 'proposal_sent', 'negotiation', 'converted', 'lost', 'on_hold',
        ],
        'recruitment.industries' => [
            'technology', 'finance', 'healthcare', 'education', 'retail',
            'manufacturing', 'consulting', 'hospitality', 'construction',
            'telecom', 'nonprofit', 'other',
        ],
        'recruitment.company_sizes' => [
            '1-10', '11-50', '51-200', '201-500', '501-1000', '1000+',
        ],
        'recruitment.employment_types' => [
            'full_time', 'part_time', 'contract', 'intern', 'temporary',
        ],
        'recruitment.work_modes' => [
            'remote', 'onsite', 'hybrid',
        ],
        'recruitment.education_levels' => [
            'high_school', 'diploma', 'bachelor', 'master', 'phd', 'other',
        ],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                [
                    'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'encrypted' => false,
                    'group' => 'recruitment',
                ]
            );
        }
    }
}
