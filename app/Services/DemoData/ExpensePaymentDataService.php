<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class ExpensePaymentDataService
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->load();
        usort($rows, static fn (array $a, array $b): int => [$b['payment_date'], $b['payment_number']] <=> [$a['payment_date'], $a['payment_number']]);
        return $rows;
    }

    /** @return array<string, mixed> */
    public function find(string $number): array
    {
        foreach ($this->all() as $row) if ($row['payment_number'] === $number) return $row;
        throw new RuntimeException('The expense payment could not be found.');
    }

    /** @return array<string, mixed> */
    public function forExpense(string $expenseNumber): ?array
    {
        foreach ($this->all() as $row) if ($row['expense_number'] === $expenseNumber && $row['status'] !== 'Reversed') return $row;
        return null;
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        return $this->mutate(function (array &$rows) use ($attributes): array {
            if (collect($rows)->contains('request_token', $attributes['request_token'])) throw new RuntimeException('This expense payment request already exists.');
            if (collect($rows)->contains(fn (array $row): bool => $row['expense_number'] === $attributes['expense_number'] && $row['status'] !== 'Reversed')) throw new RuntimeException('This expense already has an active payment record.');
            $year = substr($attributes['payment_date'], 0, 4);
            $sequence = collect($rows)->filter(fn (array $row): bool => str_starts_with($row['payment_number'], "EPY-{$year}-"))
                ->map(fn (array $row): int => (int) substr($row['payment_number'], -4))->max() + 1;
            $now = now()->toIso8601String();
            $payment = ['id' => (int) collect($rows)->max('id') + 1, 'payment_number' => sprintf('EPY-%s-%04d', $year, $sequence), ...$attributes,
                'status' => 'Draft', 'journal_entry_id' => null, 'reversal_journal_entry_id' => null,
                'submitted_by' => null, 'submitted_at' => null, 'posted_by' => null, 'posted_at' => null, 'reversed_by' => null, 'reversed_at' => null,
                'created_at' => $now, 'updated_at' => $now];
            $rows[] = $payment;
            return $payment;
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function update(string $number, array $attributes): array
    {
        return $this->mutate(function (array &$rows) use ($number, $attributes): array {
            $index = $this->index($rows, $number);
            if ($rows[$index]['status'] !== 'Draft') throw new RuntimeException('Only draft expense payments can be edited.');
            $rows[$index] = [...$rows[$index], ...$attributes, 'payment_number' => $number, 'status' => 'Draft', 'updated_at' => now()->toIso8601String()];
            return $rows[$index];
        });
    }

    public function delete(string $number): void
    {
        $this->mutate(function (array &$rows) use ($number): void {
            $index = $this->index($rows, $number);
            if ($rows[$index]['status'] !== 'Draft') throw new RuntimeException('Only draft expense payments can be deleted.');
            array_splice($rows, $index, 1);
        });
    }

    /** @return array<string, mixed> */
    public function submit(string $number, array $actor): array { return $this->transition($number, 'Draft', 'For Review', ['submitted_by' => $this->actor($actor), 'submitted_at' => now()->toIso8601String()]); }
    /** @return array<string, mixed> */
    public function returnToDraft(string $number): array { return $this->transition($number, 'For Review', 'Draft', ['submitted_by' => null, 'submitted_at' => null]); }
    /** @return array<string, mixed> */
    public function post(string $number, string $journal, array $actor, array $links = []): array { return $this->transition($number, 'For Review', 'Posted', ['journal_entry_id' => $journal, ...$links, 'posted_by' => $this->actor($actor), 'posted_at' => now()->toIso8601String()]); }
    /** @return array<string, mixed> */
    public function reverse(string $number, string $journal, array $actor): array { return $this->transition($number, 'Posted', 'Reversed', ['reversal_journal_entry_id' => $journal, 'reversed_by' => $this->actor($actor), 'reversed_at' => now()->toIso8601String()]); }

    /** @return array<string, mixed> */
    private function transition(string $number, string $from, string $to, array $attributes): array
    {
        return $this->mutate(function (array &$rows) use ($number, $from, $to, $attributes): array {
            $index = $this->index($rows, $number);
            if ($rows[$index]['status'] !== $from) throw new RuntimeException("Only {$from} expense payments can move to {$to}.");
            $rows[$index] = [...$rows[$index], ...$attributes, 'status' => $to, 'updated_at' => now()->toIso8601String()];
            return $rows[$index];
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function load(): array
    {
        $path = $this->path();
        if (! is_file($path)) return [];
        try { $rows = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('Expense payment demo data is invalid.', previous: $e); }
        return is_array($rows) ? $rows : [];
    }

    private function mutate(callable $callback): mixed
    {
        $handle = fopen($this->path(), 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) throw new RuntimeException('Unable to lock expense payment demo data.');
        try {
            rewind($handle); $contents = stream_get_contents($handle);
            $rows = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $result = $callback($rows); $encoded = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            rewind($handle); if (! ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || ! fflush($handle)) throw new RuntimeException('Unable to save expense payment demo data.');
            return $result;
        } catch (JsonException $e) { throw new RuntimeException('Expense payment demo data is invalid.', previous: $e); }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    private function index(array $rows, string $number): int { foreach ($rows as $i => $row) if ($row['payment_number'] === $number) return $i; throw new RuntimeException('The expense payment could not be found.'); }
    private function actor(array $actor): array { return ['id' => $actor['id'] ?? null, 'name' => $actor['name'] ?? 'Demo User']; }
    private function path(): string { return (string) config('accounting.expense_payments_path', storage_path('demo-data/expense_payments.json')); }
}
