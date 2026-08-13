<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class AuditLogDataService
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $path = (string) config('accounting.audit_logs_path');
        if (! is_file($path)) {
            return [];
        }
        try {
            $logs = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The demo audit log JSON file is invalid.', previous: $exception);
        }
        if (! is_array($logs)) {
            throw new RuntimeException('The demo audit log JSON file must contain an array.');
        }
        usort($logs, static fn (array $left, array $right): int => [$right['created_at'], $right['id']] <=> [$left['created_at'], $left['id']]);

        return $logs;
    }

    /** @param array<string, mixed> $actor */
    public function record(
        array $actor,
        string $action,
        string $resourceId,
        array $details = [],
        string $resource = 'journal_entry',
    ): void {
        $path = (string) config('accounting.audit_logs_path');
        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the demo audit log JSON file.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the demo audit log JSON file.');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            $logs = $contents === false || trim($contents) === ''
                ? []
                : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($logs)) {
                throw new RuntimeException('The demo audit log JSON file must contain an array.');
            }

            $logs[] = [
                'id' => count($logs) + 1,
                'actor_user_id' => $actor['id'] ?? null,
                'actor_name' => $actor['name'] ?? 'Demo User',
                'actor_role' => $actor['role'] ?? 'Unknown',
                'action' => $action,
                'resource' => $resource,
                'resource_id' => $resourceId,
                'details' => $details,
                'created_at' => now()->toIso8601String(),
            ];
            $encoded = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

            rewind($handle);
            if (! ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || ! fflush($handle)) {
                throw new RuntimeException('Unable to save the demo audit log JSON file.');
            }
        } catch (JsonException $exception) {
            throw new RuntimeException('The demo audit log JSON file is invalid.', previous: $exception);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
