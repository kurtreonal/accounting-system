<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class UserDataService
{
    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        $rows = $this->load();
        usort($rows, static fn (array $a, array $b): int => ($a['employee_code'] ?? '') <=> ($b['employee_code'] ?? ''));
        return $rows;
    }

    /** @return array<string, mixed> */
    public function find(int $id): array
    {
        foreach ($this->all() as $row) if ((int) $row['id'] === $id) return $row;
        throw new RuntimeException('Demo user could not be found.');
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        return $this->mutate(function (array &$rows) use ($attributes): array {
            $this->assertUnique($rows, $attributes['email'], $attributes['employee_code']);
            $user = [
                'id' => (int) collect($rows)->max('id') + 1,
                ...$attributes,
                'password_hash' => password_hash($attributes['password'], PASSWORD_BCRYPT),
                'avatar_data_url' => null,
                'active' => true,
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];
            unset($user['password']);
            $rows[] = $user;
            return $user;
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function update(int $id, array $attributes): array
    {
        return $this->mutate(function (array &$rows) use ($id, $attributes): array {
            $index = $this->index($rows, $id);
            $this->assertUnique($rows, $attributes['email'], $attributes['employee_code'], $id);
            $rows[$index] = [...$rows[$index], ...$attributes, 'updated_at' => now()->toIso8601String()];
            return $rows[$index];
        });
    }

    /** @return array<string, mixed> */
    public function setActive(int $id, bool $active): array
    {
        return $this->mutate(function (array &$rows) use ($id, $active): array {
            $index = $this->index($rows, $id);
            $rows[$index]['active'] = $active;
            $rows[$index]['updated_at'] = now()->toIso8601String();
            return $rows[$index];
        });
    }

    public function resetPassword(int $id, string $password): void
    {
        $this->mutate(function (array &$rows) use ($id, $password): void {
            $index = $this->index($rows, $id);
            $rows[$index]['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
            $rows[$index]['updated_at'] = now()->toIso8601String();
        });
    }

    /** @return array<string, mixed> */
    public function updateAvatar(int $id, ?string $avatarDataUrl): array
    {
        return $this->mutate(function (array &$rows) use ($id, $avatarDataUrl): array {
            $index = $this->index($rows, $id);
            $rows[$index]['avatar_data_url'] = $avatarDataUrl;
            $rows[$index]['updated_at'] = now()->toIso8601String();

            return $rows[$index];
        });
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function assertUnique(array $rows, string $email, string $employeeCode, ?int $except = null): void
    {
        foreach ($rows as $row) {
            if ($except !== null && (int) $row['id'] === $except) continue;
            if (strtolower((string) $row['email']) === strtolower($email)) throw new RuntimeException('Email address is already assigned.');
            if (strtolower((string) ($row['employee_code'] ?? '')) === strtolower($employeeCode)) throw new RuntimeException('Employee code is already assigned.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function load(): array
    {
        try { $rows = json_decode((string) file_get_contents($this->path()), true, flags: JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('Demo users JSON is invalid.', previous: $e); }
        return is_array($rows) ? $rows : [];
    }

    private function mutate(callable $callback): mixed
    {
        $handle = fopen($this->path(), 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) throw new RuntimeException('Unable to lock demo users.');
        try {
            rewind($handle);
            $content = stream_get_contents($handle);
            $rows = $content === false || trim($content) === '' ? [] : json_decode($content, true, flags: JSON_THROW_ON_ERROR);
            $result = $callback($rows);
            $encoded = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            rewind($handle);
            if (! ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || ! fflush($handle)) throw new RuntimeException('Unable to save demo users.');
            return $result;
        } catch (JsonException $e) {
            throw new RuntimeException('Demo users JSON is invalid.', previous: $e);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function index(array $rows, int $id): int
    {
        foreach ($rows as $index => $row) if ((int) $row['id'] === $id) return $index;
        throw new RuntimeException('Demo user could not be found.');
    }

    private function path(): string
    {
        return (string) config('accounting.users_path', storage_path('demo-data/users.json'));
    }
}
