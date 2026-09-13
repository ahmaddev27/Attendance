<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Models\User;
use App\Shared\Enums\OptionList;
use Illuminate\Support\Facades\Log;

/**
 * Reads and writes the admin-editable picker lists described by
 * OptionList. Storage is a JSON array in the `settings` table, read
 * through SettingsService so lookups share its cache and every write
 * invalidates it.
 *
 * A missing, empty or corrupt row resolves to the list's code defaults —
 * a picker must never render empty because of a configuration mistake.
 */
final class OptionListService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return list<array{value: string, label: string}>
     */
    public function items(OptionList $list): array
    {
        $raw = $this->settings->get($list->settingKey());

        if ($raw === null || $raw === '') {
            return $list->defaults();
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            Log::warning('option list setting is not a JSON array; serving defaults', [
                'key' => $list->settingKey(),
            ]);

            return $list->defaults();
        }

        $items = $this->normalize($list, $decoded);

        return $items === [] ? $list->defaults() : $items;
    }

    /**
     * Every list keyed by its short name, e.g. `currencies`.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public function all(): array
    {
        $lists = [];

        foreach (OptionList::cases() as $list) {
            $lists[$list->value] = $this->items($list);
        }

        return $lists;
    }

    /**
     * @return list<string>
     */
    public function values(OptionList $list): array
    {
        return array_column($this->items($list), 'value');
    }

    /**
     * @param  list<array{value: string, label: string}>  $items  already validated
     * @return list<array{value: string, label: string}>
     */
    public function replace(OptionList $list, array $items, User $actor): array
    {
        $this->settings->set(
            key: $list->settingKey(),
            value: json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            group: $list->group(),
        );

        activity('settings')
            ->causedBy($actor)
            ->withProperties([
                'key' => $list->settingKey(),
                'group' => $list->group(),
                'items_count' => count($items),
            ])
            ->log('option_list_updated');

        return $this->items($list);
    }

    /**
     * Drops the admin override so the list falls back to its code defaults.
     *
     * @return list<array{value: string, label: string}>
     */
    public function reset(OptionList $list, User $actor): array
    {
        $this->settings->forget($list->settingKey());

        activity('settings')
            ->causedBy($actor)
            ->withProperties([
                'key' => $list->settingKey(),
                'group' => $list->group(),
            ])
            ->log('option_list_reset');

        return $list->defaults();
    }

    /**
     * Rows seeded before lists carried labels stored bare codes
     * (`["linkedin", "referral"]`); those borrow the default label for the
     * code when one exists so existing environments read in Arabic.
     *
     * @param  array<mixed>  $decoded
     * @return list<array{value: string, label: string}>
     */
    private function normalize(OptionList $list, array $decoded): array
    {
        $defaultLabels = array_column($list->defaults(), 'label', 'value');
        $items = [];

        foreach ($decoded as $entry) {
            if (is_string($entry) && $entry !== '') {
                $items[] = ['value' => $entry, 'label' => $defaultLabels[$entry] ?? $entry];

                continue;
            }

            if (! is_array($entry) || ! is_string($entry['value'] ?? null) || $entry['value'] === '') {
                continue;
            }

            $label = $entry['label'] ?? null;

            $items[] = [
                'value' => $entry['value'],
                'label' => is_string($label) && $label !== '' ? $label : $entry['value'],
            ];
        }

        return $items;
    }
}
