<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class PurchaseDataService
{
    /** @return array<int, array<string, mixed>> */
    public function vendors(): array
    {
        $vendors = $this->load($this->vendorsPath(), 'vendors');
        usort($vendors, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $vendors;
    }

    /** @return array<int, array<string, mixed>> */
    public function bills(): array
    {
        $bills = $this->load($this->billsPath(), 'vendor bills');
        $paymentTotals = $this->paymentTotals();
        usort($bills, static fn (array $left, array $right): int => [$right['bill_date'], $right['bill_number']] <=> [$left['bill_date'], $left['bill_number']]);

        return array_map(
            fn (array $bill): array => $this->withDisplayStatus($bill, $paymentTotals[$bill['bill_number']] ?? 0),
            $bills,
        );
    }

    /** @return array<string, mixed> */
    public function findBill(string $billNumber): array
    {
        $paymentTotals = $this->paymentTotals();
        foreach ($this->load($this->billsPath(), 'vendor bills') as $bill) {
            if ($bill['bill_number'] === $billNumber) {
                return $this->withDisplayStatus($bill, $paymentTotals[$billNumber] ?? 0);
            }
        }

        throw new RuntimeException('The vendor bill could not be found.');
    }

    /** @return array<int, array<string, mixed>> */
    public function payments(): array
    {
        $payments = array_map($this->normalizePayment(...), $this->load($this->paymentsPath(), 'vendor payments'));
        usort($payments, static fn (array $left, array $right): int => [$right['payment_date'], $right['payment_number']] <=> [$left['payment_date'], $left['payment_number']]);

        return $payments;
    }

    /** @return array<int, array<string, mixed>> */
    public function postedPayments(): array
    {
        return array_values(array_filter($this->payments(), static fn (array $payment): bool => $payment['status'] === 'Posted'));
    }

    /** @return array<string, mixed> */
    public function findPayment(string $paymentNumber): array
    {
        foreach ($this->payments() as $payment) {
            if ($payment['payment_number'] === $paymentNumber) {
                return $payment;
            }
        }

        throw new RuntimeException('The vendor payment could not be found.');
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function createVendor(array $attributes): array
    {
        return $this->mutate($this->vendorsPath(), 'vendors', function (array &$vendors) use ($attributes): array {
            $this->requireUniqueVendorCode($vendors, $attributes['code']);
            $now = now()->toIso8601String();
            $vendor = [
                'id' => $this->nextId($vendors),
                ...$attributes,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $vendors[] = $vendor;

            return $vendor;
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function updateVendor(int $id, array $attributes): array
    {
        return $this->mutate($this->vendorsPath(), 'vendors', function (array &$vendors) use ($id, $attributes): array {
            $index = $this->vendorIndex($vendors, $id);
            $this->requireUniqueVendorCode($vendors, $attributes['code'], $id);
            $vendors[$index] = [
                ...$vendors[$index],
                ...$attributes,
                'id' => $id,
                'updated_at' => now()->toIso8601String(),
            ];

            return $vendors[$index];
        });
    }

    /** @return array<string, mixed> */
    public function updateVendorStatus(int $id, string $status): array
    {
        return $this->mutate($this->vendorsPath(), 'vendors', function (array &$vendors) use ($id, $status): array {
            $index = $this->vendorIndex($vendors, $id);
            $vendors[$index]['status'] = $status;
            $vendors[$index]['updated_at'] = now()->toIso8601String();

            return $vendors[$index];
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function createBill(array $attributes): array
    {
        return $this->mutate($this->billsPath(), 'vendor bills', function (array &$bills) use ($attributes): array {
            $this->requireUniqueBillReference($bills, $attributes['vendor_id'], $attributes['reference']);
            $now = now()->toIso8601String();
            $bill = [
                'id' => $this->nextId($bills),
                'bill_number' => $this->nextBillNumber($bills, $attributes['bill_date']),
                ...$attributes,
                'status' => 'Draft',
                'amount_paid' => 0,
                'journal_entry_id' => null,
                'posted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $bills[] = $bill;

            return $this->withDisplayStatus($bill);
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function updateBill(string $billNumber, array $attributes): array
    {
        return $this->mutate($this->billsPath(), 'vendor bills', function (array &$bills) use ($billNumber, $attributes): array {
            $index = $this->billIndex($bills, $billNumber);
            if ($bills[$index]['status'] !== 'Draft') {
                throw new RuntimeException('Only draft vendor bills can be edited.');
            }
            $this->requireUniqueBillReference($bills, $attributes['vendor_id'], $attributes['reference'], $billNumber);
            $bills[$index] = [
                ...$bills[$index],
                ...$attributes,
                'bill_number' => $billNumber,
                'status' => 'Draft',
                'updated_at' => now()->toIso8601String(),
            ];

            return $this->withDisplayStatus($bills[$index]);
        });
    }

    /** @return array<string, mixed> */
    public function markPosted(string $billNumber, string $journalNumber, array $actor): array
    {
        $paymentTotal = $this->paymentTotals()[$billNumber] ?? 0;

        return $this->mutate($this->billsPath(), 'vendor bills', function (array &$bills) use ($billNumber, $journalNumber, $actor, $paymentTotal): array {
            $index = $this->billIndex($bills, $billNumber);
            if ($bills[$index]['status'] !== 'Draft') {
                throw new RuntimeException('Only draft vendor bills can be posted.');
            }

            $bills[$index] = [
                ...$bills[$index],
                'status' => 'Posted',
                'journal_entry_id' => $journalNumber,
                'posted_by' => ['id' => $actor['id'] ?? null, 'name' => $actor['name'] ?? 'Demo User'],
                'posted_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            return $this->withDisplayStatus($bills[$index], $paymentTotal);
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function createPayment(array $attributes): array
    {
        return $this->mutate($this->paymentsPath(), 'vendor payments', function (array &$payments) use ($attributes): array {
            foreach ($payments as $payment) {
                if (($payment['request_token'] ?? null) === ($attributes['request_token'] ?? null)) {
                    throw new RuntimeException('This vendor payment request already exists.');
                }
            }

            $now = now()->toIso8601String();
            $payment = [
                'id' => $this->nextId($payments),
                'payment_number' => $this->nextPaymentNumber($payments, $attributes['payment_date']),
                ...$attributes,
                'status' => 'Draft',
                'journal_entry_id' => null,
                'submitted_by' => null,
                'submitted_at' => null,
                'posted_by' => null,
                'posted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $payments[] = $payment;

            return $payment;
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function updatePayment(string $paymentNumber, array $attributes): array
    {
        return $this->mutate($this->paymentsPath(), 'vendor payments', function (array &$payments) use ($paymentNumber, $attributes): array {
            $index = $this->paymentIndex($payments, $paymentNumber);
            $current = $this->normalizePayment($payments[$index]);
            if ($current['status'] !== 'Draft') {
                throw new RuntimeException('Only draft vendor payments can be edited.');
            }
            $payments[$index] = [...$current, ...$attributes, 'payment_number' => $paymentNumber, 'status' => 'Draft', 'updated_at' => now()->toIso8601String()];

            return $payments[$index];
        });
    }

    /** @return array<string, mixed> */
    public function submitPaymentForReview(string $paymentNumber, array $actor): array
    {
        return $this->transitionPayment($paymentNumber, 'Draft', 'For Review', ['submitted_by' => $this->actorSnapshot($actor), 'submitted_at' => now()->toIso8601String()]);
    }

    /** @return array<string, mixed> */
    public function returnPaymentToDraft(string $paymentNumber): array
    {
        return $this->transitionPayment($paymentNumber, 'For Review', 'Draft', ['submitted_by' => null, 'submitted_at' => null]);
    }

    /** @return array<string, mixed> */
    public function markPaymentPosted(string $paymentNumber, string $journalNumber, array $actor): array
    {
        return $this->mutate($this->paymentsPath(), 'vendor payments', function (array &$payments) use ($paymentNumber, $journalNumber, $actor): array {
            $index = $this->paymentIndex($payments, $paymentNumber);
            $payment = $this->normalizePayment($payments[$index]);
            if (! in_array($payment['status'], ['Draft', 'For Review'], true)) {
                throw new RuntimeException('Only draft or for-review vendor payments can be posted.');
            }
            $payments[$index] = [...$payment, 'status' => 'Posted', 'journal_entry_id' => $journalNumber, 'posted_by' => $this->actorSnapshot($actor), 'posted_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()];

            return $payments[$index];
        });
    }

    public function deletePayment(string $paymentNumber): void
    {
        $this->mutate($this->paymentsPath(), 'vendor payments', function (array &$payments) use ($paymentNumber): void {
            $index = $this->paymentIndex($payments, $paymentNumber);
            if ($this->normalizePayment($payments[$index])['status'] !== 'Draft') {
                throw new RuntimeException('Only draft vendor payments can be deleted.');
            }
            array_splice($payments, $index, 1);
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function load(string $path, string $resource): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("The demo {$resource} JSON file is missing.");
        }

        try {
            $records = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("The demo {$resource} JSON file is invalid.", previous: $exception);
        }

        if (! is_array($records)) {
            throw new RuntimeException("The demo {$resource} JSON file must contain an array.");
        }

        return $records;
    }

    /** @template TResult
     * @param  callable(array<int, array<string, mixed>>&): TResult  $callback
     * @return TResult
     */
    private function mutate(string $path, string $resource, callable $callback): mixed
    {
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException("Unable to open the demo {$resource} JSON file.");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException("Unable to lock the demo {$resource} JSON file.");
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            $records = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($records)) {
                throw new RuntimeException("The demo {$resource} JSON file must contain an array.");
            }

            $result = $callback($records);
            $encoded = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            rewind($handle);
            if (! ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || ! fflush($handle)) {
                throw new RuntimeException("Unable to save the demo {$resource} JSON file.");
            }

            return $result;
        } catch (JsonException $exception) {
            throw new RuntimeException("The demo {$resource} JSON file is invalid.", previous: $exception);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param array<int, array<string, mixed>> $records */
    private function nextId(array $records): int
    {
        return max([0, ...array_map(static fn (array $record): int => (int) ($record['id'] ?? 0), $records)]) + 1;
    }

    /** @param array<int, array<string, mixed>> $records */
    private function nextBillNumber(array $records, string $date): string
    {
        return $this->nextNumber($records, $date, 'BILL', 'bill_number');
    }

    /** @param array<int, array<string, mixed>> $records */
    private function nextPaymentNumber(array $records, string $date): string
    {
        return $this->nextNumber($records, $date, 'VPY', 'payment_number');
    }

    /** @param array<int, array<string, mixed>> $records */
    private function nextNumber(array $records, string $date, string $prefix, string $field): string
    {
        $year = substr($date, 0, 4);
        $highest = 0;
        foreach ($records as $record) {
            if (preg_match('/^'.preg_quote($prefix, '/').'-'.preg_quote($year, '/').'-(\d+)$/', (string) ($record[$field] ?? ''), $matches) === 1) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $highest + 1);
    }

    /** @param array<int, array<string, mixed>> $vendors */
    private function vendorIndex(array $vendors, int $id): int
    {
        foreach ($vendors as $index => $vendor) {
            if ((int) $vendor['id'] === $id) {
                return $index;
            }
        }

        throw new RuntimeException('The vendor could not be found.');
    }

    /** @param array<int, array<string, mixed>> $bills */
    private function billIndex(array $bills, string $billNumber): int
    {
        foreach ($bills as $index => $bill) {
            if ($bill['bill_number'] === $billNumber) {
                return $index;
            }
        }

        throw new RuntimeException('The vendor bill could not be found.');
    }

    /** @param array<int, array<string, mixed>> $payments */
    private function paymentIndex(array $payments, string $paymentNumber): int
    {
        foreach ($payments as $index => $payment) {
            if (($payment['payment_number'] ?? null) === $paymentNumber) {
                return $index;
            }
        }

        throw new RuntimeException('The vendor payment could not be found.');
    }

    /** @return array<string, mixed> */
    private function transitionPayment(string $paymentNumber, string $from, string $to, array $attributes): array
    {
        return $this->mutate($this->paymentsPath(), 'vendor payments', function (array &$payments) use ($paymentNumber, $from, $to, $attributes): array {
            $index = $this->paymentIndex($payments, $paymentNumber);
            $payment = $this->normalizePayment($payments[$index]);
            if ($payment['status'] !== $from) {
                throw new RuntimeException("Only {$from} vendor payments can move to {$to}.");
            }
            $payments[$index] = [...$payment, ...$attributes, 'status' => $to, 'updated_at' => now()->toIso8601String()];

            return $payments[$index];
        });
    }

    /** @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    private function normalizePayment(array $payment): array
    {
        return [...$payment, 'status' => $payment['status'] ?? 'Posted'];
    }

    /** @return array{id: mixed, name: mixed} */
    private function actorSnapshot(array $actor): array
    {
        return ['id' => $actor['id'] ?? null, 'name' => $actor['name'] ?? 'Demo User'];
    }

    /** @param array<int, array<string, mixed>> $vendors */
    private function requireUniqueVendorCode(array $vendors, string $code, ?int $exceptId = null): void
    {
        foreach ($vendors as $vendor) {
            if (strcasecmp($vendor['code'], $code) === 0 && (int) $vendor['id'] !== $exceptId) {
                throw new RuntimeException('Vendor code already exists.');
            }
        }
    }

    /** @param array<int, array<string, mixed>> $bills */
    private function requireUniqueBillReference(array $bills, int $vendorId, string $reference, ?string $exceptBillNumber = null): void
    {
        foreach ($bills as $bill) {
            if ((int) $bill['vendor_id'] === (int) $vendorId
                && strcasecmp(trim((string) $bill['reference']), trim($reference)) === 0
                && $bill['bill_number'] !== $exceptBillNumber) {
                throw new RuntimeException('This vendor bill reference already exists.');
            }
        }
    }

    /** @param array<string, mixed> $bill
     * @return array<string, mixed>
     */
    private function withDisplayStatus(array $bill, float $payments = 0): array
    {
        $amountPaid = round((float) ($bill['amount_paid'] ?? 0) + $payments, 2);
        $remaining = max(0, round((float) $bill['total'] - $amountPaid, 2));
        $status = $bill['status'];
        if ($status === 'Posted' && $remaining <= 0.004) {
            $status = 'Paid';
        } elseif ($status === 'Posted' && $amountPaid > 0) {
            $status = 'Partially Paid';
        } elseif ($status === 'Posted' && $bill['due_date'] < now()->toDateString()) {
            $status = 'Overdue';
        } elseif ($status === 'Posted') {
            $status = 'Unpaid';
        }

        return [...$bill, 'amount_paid' => $amountPaid, 'remaining_balance' => $remaining, 'display_status' => $status];
    }

    /** @return array<string, float> */
    private function paymentTotals(): array
    {
        $totals = [];
        foreach ($this->postedPayments() as $payment) {
            foreach ($payment['allocations'] ?? [] as $allocation) {
                $billNumber = (string) ($allocation['bill_number'] ?? '');
                if ($billNumber === '') {
                    continue;
                }
                $totals[$billNumber] = round(($totals[$billNumber] ?? 0) + (float) ($allocation['amount'] ?? 0), 2);
            }
        }

        return $totals;
    }

    private function vendorsPath(): string
    {
        return (string) config('accounting.vendors_path');
    }

    private function billsPath(): string
    {
        return (string) config('accounting.bills_path');
    }

    private function paymentsPath(): string
    {
        return (string) config('accounting.vendor_payments_path');
    }
}
