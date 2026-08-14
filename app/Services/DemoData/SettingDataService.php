<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class SettingDataService
{
    /** @return array<string, mixed> */
    public function all(): array
    {
        if (! is_file($this->path())) return $this->defaults();
        try { $settings = json_decode((string) file_get_contents($this->path()), true, flags: JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('Demo settings JSON is invalid.', previous: $e); }
        return [...$this->defaults(), ...(is_array($settings) ? $settings : [])];
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function update(string $section, array $values): array
    {
        $settings = $this->all();
        $settings[$section] = [...($settings[$section] ?? []), ...$values];
        $settings['updated_at'] = now()->toIso8601String();
        $encoded = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        if (file_put_contents($this->path(), $encoded, LOCK_EX) !== strlen($encoded)) throw new RuntimeException('Unable to save demo settings.');
        return $settings;
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'company' => ['name' => 'APM Customs', 'legal_name' => 'APM Customs Demo Company', 'tax_id' => '000-000-000-000', 'email' => 'accounting@apm.example', 'phone' => '+63 2 8000 0000', 'address' => 'Metro Manila, Philippines'],
            'system' => ['fiscal_year_start' => 1, 'currency' => 'PHP', 'date_format' => 'Y-m-d', 'journal_prefix' => 'JE', 'invoice_prefix' => 'INV', 'bill_prefix' => 'BILL', 'timezone' => 'Asia/Manila'],
            'updated_at' => null,
        ];
    }

    private function path(): string
    {
        return (string) config('accounting.settings_path', storage_path('demo-data/settings.json'));
    }
}
