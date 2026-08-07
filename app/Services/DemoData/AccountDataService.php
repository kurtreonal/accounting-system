<?php

namespace App\Services\DemoData;

use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;

class AccountDataService
{
    /** @return array<int, array{code: string, name: string, type: string, sub_type: string, balance: float|int, status: string}> */
    public function all(array $filters = []): array
    {
        $accounts = $this->load();
        $search = mb_strtolower(trim((string) Arr::get($filters, 'search', '')));
        $type = trim((string) Arr::get($filters, 'type', ''));
        $status = trim((string) Arr::get($filters, 'status', ''));

        return array_values(array_filter($accounts, static function (array $account) use ($search, $type, $status): bool {
            if ($search !== '' && ! str_contains(mb_strtolower($account['code'].' '.$account['name']), $search)) {
                return false;
            }

            if ($type !== '' && $account['type'] !== $type) {
                return false;
            }

            return $status === '' || $account['status'] === $status;
        }));
    }

    /** @return array<int, array{code: string, name: string, type: string, sub_type: string, balance: float|int, status: string}> */
    private function load(): array
    {
        $path = storage_path('demo-data/accounts.json');

        if (! is_file($path)) {
            throw new RuntimeException('The demo accounts JSON file is missing.');
        }

        try {
            $accounts = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The demo accounts JSON file is invalid.', previous: $exception);
        }

        if (! is_array($accounts)) {
            throw new RuntimeException('The demo accounts JSON file must contain an array.');
        }

        foreach ($accounts as $account) {
            foreach (['code', 'name', 'type', 'sub_type', 'balance', 'status'] as $field) {
                if (! array_key_exists($field, $account)) {
                    throw new RuntimeException("The demo account data is missing the {$field} field.");
                }
            }
        }

        return $accounts;
    }
}
