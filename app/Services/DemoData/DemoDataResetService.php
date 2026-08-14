<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;
use Throwable;

class DemoDataResetService
{
    /** @param null|callable(array{files_reset: int, accounts_zeroed: int, reset_at: string}): void $afterReset
     * @return array{files_reset: int, accounts_zeroed: int, reset_at: string}
     */
    public function reset(?callable $afterReset = null): array
    {
        $paths = $this->dataPaths();
        $snapshots = [];
        foreach ($paths as $path) $snapshots[$path] = is_file($path) ? file_get_contents($path) : null;

        try {
            $accounts = $this->decodeArray((string) ($snapshots[$paths['accounts_path']] ?? ''), 'Chart of Accounts');
            foreach ($accounts as &$account) $account['balance'] = 0;
            unset($account);
            $this->writeJson($paths['accounts_path'], $accounts);

            $preserved = ['accounts_path', 'users_path', 'settings_path', 'tax_codes_path'];
            $cleared = 0;
            foreach ($paths as $key => $path) {
                if (in_array($key, $preserved, true)) continue;
                $this->writeJson($path, []);
                $cleared++;
            }

            $result = [
                'files_reset' => $cleared + 1,
                'accounts_zeroed' => count($accounts),
                'reset_at' => now()->toIso8601String(),
            ];
            if ($afterReset !== null) $afterReset($result);
        } catch (Throwable $exception) {
            foreach ($snapshots as $path => $contents) {
                if ($contents === null) {
                    if (is_file($path)) @unlink($path);
                } else {
                    file_put_contents($path, $contents, LOCK_EX);
                }
            }
            throw new RuntimeException('Demo data reset failed; existing data was restored.', previous: $exception);
        }

        return $result;
    }

    /** @return array<string, string> */
    private function dataPaths(): array
    {
        $paths = [];
        foreach ((array) config('accounting', []) as $key => $path) {
            if (! str_ends_with((string) $key, '_path') || ! is_string($path) || $path === '') continue;
            $paths[$key] = $path;
        }
        foreach (['accounts_path', 'users_path', 'settings_path', 'tax_codes_path'] as $required) {
            if (! isset($paths[$required])) throw new RuntimeException("Demo data path [{$required}] is not configured.");
        }
        return $paths;
    }

    /** @return array<int, array<string, mixed>> */
    private function decodeArray(string $json, string $label): array
    {
        try { $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR); }
        catch (JsonException $exception) { throw new RuntimeException("{$label} contains invalid JSON.", previous: $exception); }
        if (! is_array($data) || ! array_is_list($data)) throw new RuntimeException("{$label} must contain a JSON list.");
        return $data;
    }

    private function writeJson(string $path, mixed $data): void
    {
        try { $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL; }
        catch (JsonException $exception) { throw new RuntimeException('Unable to encode demo reset data.', previous: $exception); }
        if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) throw new RuntimeException("Unable to write demo data file [{$path}].");
    }
}
