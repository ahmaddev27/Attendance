<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Admin-editable picker lists. Each case ships a default list in code;
 * a row in `settings` (key = settingKey()) overrides it once an admin
 * edits the list from /settings. Fresh installs and environments that
 * never ran a seeder therefore still render sensible pickers.
 *
 * Only descriptive lists belong here. Lead statuses, employment types
 * and work modes are deliberately absent: kanban columns, the convert
 * flow and request validation branch on their exact values, so letting
 * an admin rename or remove one would break behaviour, not just labels.
 */
enum OptionList: string
{
    case Currencies = 'currencies';
    case LeadSources = 'lead_sources';
    case Industries = 'industries';
    case CompanySizes = 'company_sizes';
    case EducationLevels = 'education_levels';

    public function settingKey(): string
    {
        return $this->group().'.'.$this->value;
    }

    /**
     * Currencies are platform-wide (payroll and contracts will need them
     * too); the rest only exist for Recruitment.
     */
    public function group(): string
    {
        return $this === self::Currencies ? 'general' : 'recruitment';
    }

    public function label(): string
    {
        return match ($this) {
            self::Currencies => 'العملات',
            self::LeadSources => 'مصادر العملاء المحتملين',
            self::Industries => 'القطاعات',
            self::CompanySizes => 'أحجام الشركات',
            self::EducationLevels => 'المستويات التعليمية',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Currencies => 'تظهر في حقل عملة الراتب عند إنشاء الوظائف.',
            self::LeadSources => 'من أين وصل العميل المحتمل — تظهر في النموذج والفلاتر.',
            self::Industries => 'قطاع الشركة للعملاء المحتملين والعملاء.',
            self::CompanySizes => 'عدد موظفي الشركة للعملاء المحتملين والعملاء.',
            self::EducationLevels => 'الحد الأدنى من التعليم المطلوب للوظيفة.',
        };
    }

    /**
     * Stored codes must stay stable and URL/CSV safe; the Arabic label is
     * what users read. Every pattern fits the 50-char columns they land in.
     */
    public function valuePattern(): string
    {
        return match ($this) {
            self::Currencies => '/^[A-Z]{3}$/',
            self::CompanySizes => '/^[0-9]{1,6}(-[0-9]{1,6}|\+)$/',
            default => '/^[a-z][a-z0-9_]{0,49}$/',
        };
    }

    public function valueHint(): string
    {
        return match ($this) {
            self::Currencies => 'الرمز يجب أن يكون رمز ISO من 3 أحرف لاتينية كبيرة، مثل USD.',
            self::CompanySizes => 'الرمز يجب أن يكون نطاقاً مثل 51-200 أو حداً أدنى مثل 1000+.',
            default => 'الرمز يجب أن يبدأ بحرف لاتيني صغير ويحتوي أحرفاً صغيرة وأرقاماً و _ فقط.',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function defaults(): array
    {
        return match ($this) {
            self::Currencies => self::pairs([
                'USD' => 'دولار أمريكي',
                'JOD' => 'دينار أردني',
                'ILS' => 'شيكل',
                'EUR' => 'يورو',
                'SAR' => 'ريال سعودي',
                'AED' => 'درهم إماراتي',
            ]),
            self::LeadSources => array_map(
                fn (LeadSource $source) => ['value' => $source->value, 'label' => $source->label()],
                LeadSource::cases(),
            ),
            self::Industries => self::pairs([
                'technology' => 'تقنية المعلومات',
                'finance' => 'المال والمصارف',
                'healthcare' => 'الرعاية الصحية',
                'education' => 'التعليم',
                'retail' => 'التجزئة',
                'manufacturing' => 'الصناعة',
                'consulting' => 'الاستشارات',
                'hospitality' => 'الضيافة',
                'construction' => 'الإنشاءات',
                'telecom' => 'الاتصالات',
                'nonprofit' => 'القطاع غير الربحي',
                'other' => 'أخرى',
            ]),
            self::CompanySizes => self::pairs([
                '1-10' => '1-10',
                '11-50' => '11-50',
                '51-200' => '51-200',
                '201-500' => '201-500',
                '501-1000' => '501-1000',
                '1000+' => '1000+',
            ]),
            self::EducationLevels => self::pairs([
                'high_school' => 'ثانوية عامة',
                'diploma' => 'دبلوم',
                'bachelor' => 'بكالوريوس',
                'master' => 'ماجستير',
                'phd' => 'دكتوراه',
                'other' => 'أخرى',
            ]),
        };
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    private static function pairs(array $labels): array
    {
        $pairs = [];

        foreach ($labels as $value => $label) {
            $pairs[] = ['value' => (string) $value, 'label' => $label];
        }

        return $pairs;
    }
}
