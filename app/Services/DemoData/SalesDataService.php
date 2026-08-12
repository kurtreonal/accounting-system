<?php

namespace App\Services\DemoData;

use JsonException;
use RuntimeException;

class SalesDataService
{
    /** @return array<int, array<string, mixed>> */
    public function customers(): array
    {
        $customers = $this->load($this->customersPath(), 'customers');
        usort($customers, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $customers;
    }

    /** @return array<int, array<string, mixed>> */
    public function invoices(): array
    {
        $invoices = $this->load($this->invoicesPath(), 'invoices');
        $paymentTotals = $this->paymentTotals();
        usort($invoices, static fn (array $left, array $right): int => [$right['invoice_date'], $right['invoice_number']] <=> [$left['invoice_date'], $left['invoice_number']]);

        return array_map(
            fn (array $invoice): array => $this->withDisplayStatus($invoice, $paymentTotals[$invoice['invoice_number']] ?? 0),
            $invoices,
        );
    }

    /** @return array<string, mixed> */
    public function findInvoice(string $invoiceNumber): array
    {
        $paymentTotals = $this->paymentTotals();
        foreach ($this->load($this->invoicesPath(), 'invoices') as $invoice) {
            if ($invoice['invoice_number'] === $invoiceNumber) {
                return $this->withDisplayStatus($invoice, $paymentTotals[$invoiceNumber] ?? 0);
            }
        }

        throw new RuntimeException('The sales invoice could not be found.');
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function createCustomer(array $attributes): array
    {
        return $this->mutate($this->customersPath(), 'customers', function (array &$customers) use ($attributes): array {
            foreach ($customers as $customer) {
                if (strcasecmp($customer['code'], $attributes['code']) === 0) {
                    throw new RuntimeException('Customer code already exists.');
                }
            }

            $customer = [
                'id' => $this->nextId($customers),
                ...$attributes,
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];
            $customers[] = $customer;

            return $customer;
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function createInvoice(array $attributes): array
    {
        return $this->mutate($this->invoicesPath(), 'invoices', function (array &$invoices) use ($attributes): array {
            $now = now()->toIso8601String();
            $invoice = [
                'id' => $this->nextId($invoices),
                'invoice_number' => $this->nextInvoiceNumber($invoices, $attributes['invoice_date']),
                ...$attributes,
                'status' => 'Draft',
                'amount_paid' => 0,
                'journal_entry_id' => null,
                'posted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $invoices[] = $invoice;

            return $this->withDisplayStatus($invoice);
        });
    }

    /** @return array<string, mixed> */
    public function markPosted(string $invoiceNumber, string $journalNumber, array $actor): array
    {
        $paymentTotal = $this->paymentTotals()[$invoiceNumber] ?? 0;

        return $this->mutate($this->invoicesPath(), 'invoices', function (array &$invoices) use ($invoiceNumber, $journalNumber, $actor, $paymentTotal): array {
            $index = $this->invoiceIndex($invoices, $invoiceNumber);
            if ($invoices[$index]['status'] !== 'Draft') {
                throw new RuntimeException('Only draft invoices can be posted.');
            }

            $invoices[$index] = [
                ...$invoices[$index],
                'status' => 'Posted',
                'journal_entry_id' => $journalNumber,
                'posted_by' => [
                    'id' => $actor['id'] ?? null,
                    'name' => $actor['name'] ?? 'Demo User',
                ],
                'posted_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            return $this->withDisplayStatus($invoices[$index], $paymentTotal);
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function payments(): array
    {
        $payments = $this->load($this->paymentsPath(), 'customer payments');
        usort($payments, static fn (array $left, array $right): int => [$right['payment_date'], $right['receipt_number']] <=> [$left['payment_date'], $left['receipt_number']]);

        return $payments;
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function createPayment(array $attributes): array
    {
        return $this->mutate($this->paymentsPath(), 'customer payments', function (array &$payments) use ($attributes): array {
            foreach ($payments as $payment) {
                if (($payment['request_token'] ?? null) === ($attributes['request_token'] ?? null)) {
                    throw new RuntimeException('This payment request was already posted.');
                }
            }

            $now = now()->toIso8601String();
            $payment = [
                'id' => $this->nextId($payments),
                'receipt_number' => $this->nextReceiptNumber($payments, $attributes['payment_date']),
                ...$attributes,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $payments[] = $payment;

            return $payment;
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
            $records = $contents === false || trim($contents) === ''
                ? []
                : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
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

    /** @param array<int, array<string, mixed>> $invoices */
    private function nextInvoiceNumber(array $invoices, string $date): string
    {
        $year = substr($date, 0, 4);
        $highest = 0;
        foreach ($invoices as $invoice) {
            if (preg_match('/^INV-'.preg_quote($year, '/').'-(\d+)$/', $invoice['invoice_number'], $matches) === 1) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return sprintf('INV-%s-%04d', $year, $highest + 1);
    }

    /** @param array<int, array<string, mixed>> $payments */
    private function nextReceiptNumber(array $payments, string $date): string
    {
        $year = substr($date, 0, 4);
        $highest = 0;
        foreach ($payments as $payment) {
            if (preg_match('/^RCP-'.preg_quote($year, '/').'-(\d+)$/', $payment['receipt_number'], $matches) === 1) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return sprintf('RCP-%s-%04d', $year, $highest + 1);
    }

    /** @param array<int, array<string, mixed>> $invoices */
    private function invoiceIndex(array $invoices, string $invoiceNumber): int
    {
        foreach ($invoices as $index => $invoice) {
            if ($invoice['invoice_number'] === $invoiceNumber) {
                return $index;
            }
        }

        throw new RuntimeException('The sales invoice could not be found.');
    }

    /** @param array<string, mixed> $invoice
     * @return array<string, mixed>
     */
    private function withDisplayStatus(array $invoice, float $payments = 0): array
    {
        $amountPaid = round((float) ($invoice['amount_paid'] ?? 0) + $payments, 2);
        $remaining = max(0, round((float) $invoice['total'] - $amountPaid, 2));
        $status = $invoice['status'];
        if ($status === 'Posted' && $remaining <= 0.004) {
            $status = 'Paid';
        } elseif ($status === 'Posted' && $amountPaid > 0) {
            $status = 'Partially Paid';
        } elseif ($status === 'Posted' && $invoice['due_date'] < now()->toDateString()) {
            $status = 'Overdue';
        } elseif ($status === 'Posted') {
            $status = 'Unpaid';
        }

        return [...$invoice, 'amount_paid' => $amountPaid, 'remaining_balance' => $remaining, 'display_status' => $status];
    }

    /** @return array<string, float> */
    private function paymentTotals(): array
    {
        $totals = [];
        foreach ($this->payments() as $payment) {
            foreach ($payment['allocations'] ?? [] as $allocation) {
                $invoiceNumber = (string) ($allocation['invoice_number'] ?? '');
                if ($invoiceNumber === '') {
                    continue;
                }
                $totals[$invoiceNumber] = round(($totals[$invoiceNumber] ?? 0) + (float) ($allocation['amount'] ?? 0), 2);
            }
        }

        return $totals;
    }

    private function customersPath(): string
    {
        return (string) config('accounting.customers_path');
    }

    private function invoicesPath(): string
    {
        return (string) config('accounting.invoices_path');
    }

    private function paymentsPath(): string
    {
        return (string) config('accounting.customer_payments_path');
    }
}
