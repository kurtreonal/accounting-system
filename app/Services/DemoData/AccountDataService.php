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

    /**
     * @param  array{name: string, type: string, sub_type: string, balance: float|int, status: string}  $attributes
     * @return array{code: string, name: string, type: string, sub_type: string, balance: float|int, status: string}
     */
    public function create(array $attributes): array
    {
        return $this->mutate(function (array &$accounts) use ($attributes): array {
            $usedCodes = array_column($accounts, 'code');
            $nextCode = 1;

            while (in_array((string) $nextCode, $usedCodes, true)) {
                $nextCode++;
            }

            $account = ['code' => (string) $nextCode, ...$attributes];
            $accounts[] = $account;

            return $account;
        });
    }

    /**
     * @param  array{name: string, type: string, sub_type: string, balance: float|int}  $attributes
     * @return array{code: string, name: string, type: string, sub_type: string, balance: float|int, status: string}
     */
    public function update(string $code, array $attributes): array
    {
        return $this->mutate(function (array &$accounts) use ($code, $attributes): array {
            $index = $this->accountIndex($accounts, $code);
            $accounts[$index] = [...$accounts[$index], ...$attributes];

            return $accounts[$index];
        });
    }

    /** @return array{code: string, name: string, type: string, sub_type: string, balance: float|int, status: string} */
    public function updateStatus(string $code, string $status): array
    {
        return $this->mutate(function (array &$accounts) use ($code, $status): array {
            $index = $this->accountIndex($accounts, $code);
            $accounts[$index]['status'] = $status;

            return $accounts[$index];
        });
    }

    public function delete(string $code): void
    {
        $this->mutate(function (array &$accounts) use ($code): null {
            $index = $this->accountIndex($accounts, $code);
            array_splice($accounts, $index, 1);

            return null;
        });
    }

    /** @return array<int, array{code: string, name: string, type: string, sub_type: string, balance: float|int, status: string}> */
    private function load(): array
    {
        $path = $this->path();

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

    /**
     * @template TResult
     *
     * @param  callable(array<int, array<string, mixed>>&): TResult  $callback
     * @return TResult
     */
    private function mutate(callable $callback): mixed
    {
        $path = $this->path();
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the demo accounts JSON file.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the demo accounts JSON file.');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            $accounts = $contents === false || trim($contents) === '' ? [] : $this->decode($contents);
            $result = $callback($accounts);
            $encoded = json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

            rewind($handle);
            if (! ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || ! fflush($handle)) {
                throw new RuntimeException('Unable to save the demo accounts JSON file.');
            }

            return $result;
        } catch (JsonException $exception) {
            throw new RuntimeException('The demo accounts JSON file is invalid.', previous: $exception);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function decode(string $contents): array
    {
        try {
            $accounts = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The demo accounts JSON file is invalid.', previous: $exception);
        }

        if (! is_array($accounts)) {
            throw new RuntimeException('The demo accounts JSON file must contain an array.');
        }

        return $accounts;
    }

    /** @param array<int, array<string, mixed>> $accounts */
    private function accountIndex(array $accounts, string $code): int
    {
        foreach ($accounts as $index => $account) {
            if ($account['code'] === $code) {
                return $index;
            }
        }

        throw new RuntimeException('The account could not be found.');
    }

    private function path(): string
    {
        return (string) config('accounting.accounts_path', storage_path('demo-data/accounts.json'));
    }
}
