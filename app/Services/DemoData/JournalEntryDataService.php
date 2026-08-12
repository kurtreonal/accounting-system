<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class JournalEntryDataService
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $entries = $this->load();

        usort($entries, static fn (array $left, array $right): int => [$right['date'], $right['journal_number']] <=> [$left['date'], $left['journal_number']]
        );

        return $entries;
    }

    /** @return array<string, mixed> */
    public function find(string $journalNumber): array
    {
        foreach ($this->load() as $entry) {
            if ($entry['journal_number'] === $journalNumber) {
                return $entry;
            }
        }

        throw new RuntimeException('The journal entry could not be found.');
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        return $this->mutate(function (array &$entries) use ($attributes): array {
            $now = now()->toIso8601String();
            $entry = [
                'journal_number' => $this->nextNumber($entries, $attributes['date']),
                ...$attributes,
                'status' => 'Draft',
                'reviewed_by' => null,
                'posted_by' => null,
                'reversed_by' => null,
                'reversal_of' => null,
                'reversal_entry_number' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'reviewed_at' => null,
                'posted_at' => null,
                'reversed_at' => null,
            ];
            $entries[] = $entry;

            return $entry;
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function update(string $journalNumber, array $attributes): array
    {
        return $this->mutate(function (array &$entries) use ($journalNumber, $attributes): array {
            $index = $this->entryIndex($entries, $journalNumber);
            $this->requireStatus($entries[$index], 'Draft', 'Only draft journal entries can be edited.');
            $entries[$index] = [
                ...$entries[$index],
                ...$attributes,
                'journal_number' => $journalNumber,
                'status' => 'Draft',
                'updated_at' => now()->toIso8601String(),
            ];

            return $entries[$index];
        });
    }

    public function delete(string $journalNumber): void
    {
        $this->mutate(function (array &$entries) use ($journalNumber): null {
            $index = $this->entryIndex($entries, $journalNumber);
            $this->requireStatus($entries[$index], 'Draft', 'Only draft journal entries can be deleted.');
            array_splice($entries, $index, 1);

            return null;
        });
    }

    /** @return array<string, mixed> */
    public function submitForReview(string $journalNumber): array
    {
        return $this->changeStatus($journalNumber, 'Draft', 'For Review', [
            'reviewed_by' => null,
            'reviewed_at' => null,
        ], 'Only draft journal entries can be submitted for review.');
    }

    /** @return array<string, mixed> */
    public function returnToDraft(string $journalNumber): array
    {
        return $this->changeStatus($journalNumber, 'For Review', 'Draft', [
            'reviewed_by' => null,
            'reviewed_at' => null,
        ], 'Only journal entries under review can be returned to draft.');
    }

    /** @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    public function post(string $journalNumber, array $actor, array $postingMetadata = []): array
    {
        return $this->changeStatus($journalNumber, 'For Review', 'Posted', [
            ...$postingMetadata,
            'reviewed_by' => $this->actorSnapshot($actor),
            'reviewed_at' => now()->toIso8601String(),
            'posted_by' => $this->actorSnapshot($actor),
            'posted_at' => now()->toIso8601String(),
        ], 'Only journal entries under review can be posted.');
    }

    /** @param array<string, mixed> $actor
     * @return array{entry: array<string, mixed>, reversal: array<string, mixed>}
     */
    public function reverse(string $journalNumber, array $actor): array
    {
        return $this->mutate(function (array &$entries) use ($journalNumber, $actor): array {
            $index = $this->entryIndex($entries, $journalNumber);
            $original = $entries[$index];
            $this->requireStatus($original, 'Posted', 'Only posted journal entries can be reversed.');

            $now = now()->toIso8601String();
            $reversalNumber = $this->nextNumber($entries, now()->toDateString());
            $reversalLines = array_map(static fn (array $line): array => [
                ...$line,
                'debit' => (float) $line['credit'],
                'credit' => (float) $line['debit'],
            ], $original['lines']);

            $reversal = [
                ...$original,
                'journal_number' => $reversalNumber,
                'date' => now()->toDateString(),
                'reference' => $original['journal_number'],
                'description' => 'Reversal of '.$original['journal_number'].': '.$original['description'],
                'source_type' => 'Reversal',
                'status' => 'Posted',
                'created_by' => $this->actorSnapshot($actor),
                'reviewed_by' => $this->actorSnapshot($actor),
                'posted_by' => $this->actorSnapshot($actor),
                'reversed_by' => null,
                'reversal_of' => $original['journal_number'],
                'source_key' => 'reversal:'.$original['journal_number'],
                'posting_engine' => $original['posting_engine'] ?? 'accounting-v1',
                'reversal_entry_number' => null,
                'lines' => $reversalLines,
                'created_at' => $now,
                'updated_at' => $now,
                'reviewed_at' => $now,
                'posted_at' => $now,
                'reversed_at' => null,
            ];

            $entries[$index] = [
                ...$original,
                'status' => 'Reversed',
                'reversed_by' => $this->actorSnapshot($actor),
                'reversal_entry_number' => $reversalNumber,
                'updated_at' => $now,
                'reversed_at' => $now,
            ];
            $entries[] = $reversal;

            return ['entry' => $entries[$index], 'reversal' => $reversal];
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function changeStatus(
        string $journalNumber,
        string $from,
        string $to,
        array $attributes,
        string $message,
    ): array {
        return $this->mutate(function (array &$entries) use ($journalNumber, $from, $to, $attributes, $message): array {
            $index = $this->entryIndex($entries, $journalNumber);
            $this->requireStatus($entries[$index], $from, $message);
            $entries[$index] = [
                ...$entries[$index],
                ...$attributes,
                'status' => $to,
                'updated_at' => now()->toIso8601String(),
            ];

            return $entries[$index];
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function load(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            throw new RuntimeException('The demo journal entries JSON file is missing.');
        }

        try {
            $entries = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The demo journal entries JSON file is invalid.', previous: $exception);
        }

        if (! is_array($entries)) {
            throw new RuntimeException('The demo journal entries JSON file must contain an array.');
        }

        foreach ($entries as $entry) {
            foreach (['journal_number', 'date', 'description', 'source_type', 'status', 'lines', 'total_debit', 'total_credit'] as $field) {
                if (! array_key_exists($field, $entry)) {
                    throw new RuntimeException("The demo journal entry data is missing the {$field} field.");
                }
            }
        }

        return $entries;
    }

    /** @template TResult
     * @param  callable(array<int, array<string, mixed>>&): TResult  $callback
     * @return TResult
     */
    private function mutate(callable $callback): mixed
    {
        $path = $this->path();
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the demo journal entries JSON file.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the demo journal entries JSON file.');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            $entries = $contents === false || trim($contents) === '' ? [] : $this->decode($contents);
            $result = $callback($entries);
            $encoded = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

            rewind($handle);
            if (! ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || ! fflush($handle)) {
                throw new RuntimeException('Unable to save the demo journal entries JSON file.');
            }

            return $result;
        } catch (JsonException $exception) {
            throw new RuntimeException('The demo journal entries JSON file is invalid.', previous: $exception);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function decode(string $contents): array
    {
        try {
            $entries = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The demo journal entries JSON file is invalid.', previous: $exception);
        }

        if (! is_array($entries)) {
            throw new RuntimeException('The demo journal entries JSON file must contain an array.');
        }

        return $entries;
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function entryIndex(array $entries, string $journalNumber): int
    {
        foreach ($entries as $index => $entry) {
            if ($entry['journal_number'] === $journalNumber) {
                return $index;
            }
        }

        throw new RuntimeException('The journal entry could not be found.');
    }

    /** @param array<string, mixed> $entry */
    private function requireStatus(array $entry, string $status, string $message): void
    {
        if ($entry['status'] !== $status) {
            throw new RuntimeException($message);
        }
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function nextNumber(array $entries, string $date): string
    {
        $year = substr($date, 0, 4);
        $highest = 0;

        foreach ($entries as $entry) {
            if (preg_match('/^JE-'.preg_quote($year, '/').'-(\d+)$/', $entry['journal_number'], $matches) === 1) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return sprintf('JE-%s-%04d', $year, $highest + 1);
    }

    /** @param array<string, mixed> $actor
     * @return array{id: int|string|null, name: string}
     */
    private function actorSnapshot(array $actor): array
    {
        return ['id' => $actor['id'] ?? null, 'name' => (string) ($actor['name'] ?? 'Demo User')];
    }

    private function path(): string
    {
        return (string) config('accounting.journal_entries_path');
    }
}
