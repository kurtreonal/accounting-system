<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class TaxCodeDataService
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return [];
        }

        try {
            $rows = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Tax configuration demo data is invalid.', previous: $exception);
        }

        if (! is_array($rows)) {
            throw new RuntimeException('Tax configuration demo data must contain an array.');
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeVatRates(): array
    {
        return collect($this->all())->where('status', 'Active')->where('type', 'VAT')
            ->sortByDesc('is_default')->sortBy('rate')->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function defaultVat(): ?array
    {
        return collect($this->activeVatRates())->firstWhere('is_default', true)
            ?? collect($this->activeVatRates())->first();
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        return $this->mutate(function (array &$rows) use ($attributes): array {
            $this->requireUniqueCode($rows, $attributes['code']);
            $id = (int) collect($rows)->max('id') + 1;
            $taxCode = [
                'id' => $id,
                ...$attributes,
                'status' => 'Active',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];
            if ($taxCode['is_default']) {
                $this->clearDefault($rows, $taxCode['type']);
            }
            $rows[] = $taxCode;

            return $taxCode;
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function update(int $id, array $attributes): array
    {
        return $this->mutate(function (array &$rows) use ($id, $attributes): array {
            $index = $this->index($rows, $id);
            $this->requireUniqueCode($rows, $attributes['code'], $id);
            if (($rows[$index]['is_default'] ?? false)
                && (! $attributes['is_default'] || $attributes['type'] !== $rows[$index]['type'])) {
                throw new RuntimeException('Assign another default code before removing or changing this default.');
            }
            if ($attributes['is_default']) {
                $this->clearDefault($rows, $attributes['type']);
            }
            $rows[$index] = [...$rows[$index], ...$attributes, 'updated_at' => now()->toIso8601String()];

            return $rows[$index];
        });
    }

    /** @return array<string, mixed> */
    public function updateStatus(int $id, string $status): array
    {
        return $this->mutate(function (array &$rows) use ($id, $status): array {
            $index = $this->index($rows, $id);
            if ($status === 'Inactive' && ($rows[$index]['is_default'] ?? false)) {
                throw new RuntimeException('Assign another default rate before disabling this tax code.');
            }
            $rows[$index]['status'] = $status;
            $rows[$index]['updated_at'] = now()->toIso8601String();

            return $rows[$index];
        });
    }

    /** @return array<string, mixed> */
    public function setDefault(int $id): array
    {
        return $this->mutate(function (array &$rows) use ($id): array {
            $index = $this->index($rows, $id);
            if ($rows[$index]['status'] !== 'Active') {
                throw new RuntimeException('Only an active tax code can be the default.');
            }
            $this->clearDefault($rows, $rows[$index]['type']);
            $rows[$index]['is_default'] = true;
            $rows[$index]['updated_at'] = now()->toIso8601String();

            return $rows[$index];
        });
    }

    private function mutate(callable $callback): mixed
    {
        $handle = fopen($this->path(), 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock tax configuration demo data.');
        }

        try {
            rewind($handle);
            $contents = stream_get_contents($handle);
            $rows = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($rows)) {
                throw new RuntimeException('Tax configuration demo data must contain an array.');
            }
            $result = $callback($rows);
            $encoded = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            rewind($handle);
            if (! ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || ! fflush($handle)) {
                throw new RuntimeException('Unable to save tax configuration demo data.');
            }

            return $result;
        } catch (JsonException $exception) {
            throw new RuntimeException('Tax configuration demo data is invalid.', previous: $exception);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function clearDefault(array &$rows, string $type): void
    {
        foreach ($rows as &$row) {
            if ($row['type'] === $type) {
                $row['is_default'] = false;
            }
        }
        unset($row);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function requireUniqueCode(array $rows, string $code, ?int $exceptId = null): void
    {
        if (collect($rows)->contains(fn (array $row): bool => mb_strtolower($row['code']) === mb_strtolower($code) && (int) $row['id'] !== $exceptId)) {
            throw new RuntimeException('Tax code must be unique.');
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function index(array $rows, int $id): int
    {
        foreach ($rows as $index => $row) {
            if ((int) $row['id'] === $id) {
                return $index;
            }
        }
        throw new RuntimeException('The tax code could not be found.');
    }

    private function path(): string
    {
        return (string) config('accounting.tax_codes_path', storage_path('demo-data/tax_codes.json'));
    }
}
