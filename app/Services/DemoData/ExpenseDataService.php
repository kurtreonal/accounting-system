<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class ExpenseDataService
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
            throw new RuntimeException('Expense demo data is invalid.', previous: $exception);
        }

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed> */
    public function find(string $expenseNumber): array
    {
        $expense = collect($this->all())->firstWhere('expense_number', $expenseNumber);
        if (! $expense) {
            throw new RuntimeException('The expense could not be found.');
        }

        return $expense;
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        return $this->mutate(function (array &$rows) use ($attributes): array {
            if (collect($rows)->contains('request_token', $attributes['request_token'])) {
                throw new RuntimeException('This expense request was already saved.');
            }
            $year = substr((string) $attributes['date'], 0, 4);
            $sequence = (int) collect($rows)
                ->filter(fn (array $row): bool => str_starts_with((string) ($row['expense_number'] ?? ''), "EXP-{$year}-"))
                ->map(fn (array $row): int => (int) substr((string) $row['expense_number'], -4))
                ->max() + 1;
            $expense = [
                'id' => (int) collect($rows)->max('id') + 1,
                'expense_number' => sprintf('EXP-%s-%04d', $year, $sequence),
                'journal_entry_id' => null,
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                ...$attributes,
            ];
            $rows[] = $expense;

            return $expense;
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function update(string $expenseNumber, array $attributes): array
    {
        return $this->mutate(function (array &$rows) use ($expenseNumber, $attributes): array {
            $index = $this->index($rows, $expenseNumber);
            if (($rows[$index]['status'] ?? '') !== 'Draft') {
                throw new RuntimeException('Only draft expenses can be edited.');
            }
            $rows[$index] = [...$rows[$index], ...$attributes, 'updated_at' => now()->toIso8601String()];

            return $rows[$index];
        });
    }

    /** @return array<string, mixed> */
    public function submitForReview(string $expenseNumber): array
    {
        return $this->mutate(function (array &$rows) use ($expenseNumber): array {
            $index = $this->index($rows, $expenseNumber);
            if (($rows[$index]['status'] ?? '') !== 'Draft') {
                throw new RuntimeException('Only draft expenses can be submitted for review.');
            }
            $rows[$index]['status'] = 'For Review';
            $rows[$index]['updated_at'] = now()->toIso8601String();

            return $rows[$index];
        });
    }

    /** @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    public function approve(string $expenseNumber, string $journalNumber, array $actor): array
    {
        return $this->mutate(function (array &$rows) use ($expenseNumber, $journalNumber, $actor): array {
            $index = $this->index($rows, $expenseNumber);
            if (($rows[$index]['status'] ?? '') !== 'For Review') {
                throw new RuntimeException('Only expenses for review can be approved.');
            }
            $rows[$index] = [
                ...$rows[$index],
                'status' => 'Approved',
                'journal_entry_id' => $journalNumber,
                'approved_by' => ['id' => $actor['id'] ?? null, 'name' => $actor['name'] ?? 'Demo User'],
                'approved_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            return $rows[$index];
        });
    }

    public function delete(string $expenseNumber): void
    {
        $this->mutate(function (array &$rows) use ($expenseNumber): null {
            $index = $this->index($rows, $expenseNumber);
            if (($rows[$index]['status'] ?? '') !== 'Draft') {
                throw new RuntimeException('Only draft expenses can be deleted.');
            }
            array_splice($rows, $index, 1);

            return null;
        });
    }

    private function mutate(callable $callback): mixed
    {
        $handle = fopen($this->path(), 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new RuntimeException('Unable to lock expense demo data.');
        }
        try {
            rewind($handle);
            $contents = stream_get_contents($handle);
            $rows = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($rows)) {
                throw new RuntimeException('Expense demo data must contain an array.');
            }
            $result = $callback($rows);
            $encoded = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            rewind($handle);
            if (! ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || ! fflush($handle)) {
                throw new RuntimeException('Unable to save expense demo data.');
            }

            return $result;
        } catch (JsonException $exception) {
            throw new RuntimeException('Expense demo data is invalid.', previous: $exception);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function index(array $rows, string $expenseNumber): int
    {
        foreach ($rows as $index => $row) {
            if (($row['expense_number'] ?? '') === $expenseNumber) {
                return $index;
            }
        }
        throw new RuntimeException('The expense could not be found.');
    }

    private function path(): string
    {
        return (string) config('accounting.expenses_path', storage_path('demo-data/expenses.json'));
    }
}
