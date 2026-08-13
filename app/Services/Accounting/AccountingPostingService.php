<?php

namespace App\Services\Accounting;

use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\AuditLogDataService;
use App\Services\DemoData\JournalEntryDataService;
use Illuminate\Support\Str;
use RuntimeException;

class AccountingPostingService
{
    public const ENGINE_VERSION = 'accounting-v1';

    public function __construct(
        private AccountDataService $accounts,
        private JournalEntryDataService $journals,
        private AuditLogDataService $auditLogs,
    ) {}

    /** @param array<string, mixed> $invoice
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>|null  $clientPosting
     * @return array<string, mixed>
     */
    public function postCreditSale(array $invoice, array $actor, mixed $clientPosting = null): array
    {
        $accounts = $this->activeAccounts();
        $receivable = $this->findAccount($accounts, fn (array $account): bool => $account['type'] === 'Asset' && $this->nameContains($account, 'receivable'));
        $revenue = $this->findAccount($accounts, fn (array $account): bool => $account['type'] === 'Revenue');
        $outputTax = $this->findAccount($accounts, fn (array $account): bool => $this->nameContains($account, 'output tax'), false);

        if ((float) $invoice['tax'] > 0 && $outputTax === null) {
            throw new RuntimeException('Posting a taxed invoice needs an active Output Tax account.');
        }

        $lines = [
            $this->line($receivable, 'Invoice '.$invoice['invoice_number'], (string) $invoice['customer_code'], (float) $invoice['total'], 0),
            $this->line($revenue, 'Sales revenue '.$invoice['invoice_number'], (string) $invoice['customer_code'], 0, (float) $invoice['subtotal']),
        ];
        if ((float) $invoice['tax'] > 0) {
            $lines[] = $this->line($outputTax, 'Output tax '.$invoice['invoice_number'], (string) $invoice['customer_code'], 0, (float) $invoice['tax']);
        }

        return $this->postSource(
            'invoice:'.$invoice['invoice_number'],
            [
                'date' => $invoice['invoice_date'],
                'reference' => $invoice['invoice_number'],
                'description' => 'Sales invoice '.$invoice['invoice_number'].' - '.$invoice['customer_name'],
                'source_type' => 'Invoice',
                'lines' => $lines,
            ],
            $actor,
            $clientPosting,
            ['invoice_number' => $invoice['invoice_number']],
        );
    }

    /** @param array<string, mixed> $customer
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>|null  $clientPosting
     * @return array<string, mixed>
     */
    public function postCustomerPayment(array $customer, array $payment, array $actor, mixed $clientPosting = null): array
    {
        $accounts = $this->activeAccounts();
        $cash = $this->accountByCode($accounts, (string) $payment['cash_account_code']);
        if (! $this->isCashOrBank($cash)) {
            throw new RuntimeException('Select an active cash or bank account.');
        }
        $receivable = $this->findAccount($accounts, fn (array $account): bool => $account['type'] === 'Asset' && $this->nameContains($account, 'receivable'));
        $total = $this->money($payment['amount']);

        return $this->postSource(
            'customer-payment:'.$payment['request_token'],
            [
                'date' => $payment['payment_date'],
                'reference' => trim((string) ($payment['reference'] ?? '')) ?: 'Customer payment',
                'description' => 'Customer payment - '.$customer['name'],
                'source_type' => 'Payment',
                'lines' => [
                    $this->line($cash, 'Customer payment received', (string) $customer['code'], $total, 0),
                    $this->line($receivable, 'Accounts receivable collection', (string) $customer['code'], 0, $total),
                ],
            ],
            $actor,
            $clientPosting,
            ['request_token' => $payment['request_token'], 'customer_id' => $customer['id']],
        );
    }

    /** @param array<string, mixed> $bill
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>|null  $clientPosting
     * @return array<string, mixed>
     */
    public function postVendorBill(array $bill, array $actor, mixed $clientPosting = null): array
    {
        $accounts = $this->activeAccounts();
        $payable = $this->findAccount($accounts, fn (array $account): bool => $account['type'] === 'Liability' && $this->nameContains($account, 'payable') && ! $this->nameContains($account, 'tax'));
        $inputTax = $this->findAccount($accounts, fn (array $account): bool => $this->nameContains($account, 'input tax'), false);
        if ((float) $bill['tax'] > 0 && $inputTax === null) {
            throw new RuntimeException('Posting taxed vendor bill needs active Input Tax account.');
        }

        $lines = [];
        foreach ($bill['lines'] as $billLine) {
            $account = $this->accountByCode($accounts, (string) $billLine['account_code']);
            if (! $this->isPurchaseAccount($account)) {
                throw new RuntimeException('Vendor bill contains inactive or invalid expense/asset account.');
            }
            $lines[] = $this->line($account, (string) $billLine['description'], (string) $bill['vendor_code'], (float) $billLine['subtotal'], 0);
        }
        if ((float) $bill['tax'] > 0) {
            $lines[] = $this->line($inputTax, 'Input tax '.$bill['bill_number'], (string) $bill['vendor_code'], (float) $bill['tax'], 0);
        }
        $lines[] = $this->line($payable, 'Accounts payable '.$bill['bill_number'], (string) $bill['vendor_code'], 0, (float) $bill['total']);

        return $this->postSource(
            'bill:'.$bill['bill_number'],
            [
                'date' => $bill['bill_date'],
                'reference' => $bill['bill_number'],
                'description' => 'Vendor bill '.$bill['bill_number'].' - '.$bill['vendor_name'],
                'source_type' => 'Bill',
                'lines' => $lines,
            ],
            $actor,
            $clientPosting,
            ['bill_number' => $bill['bill_number']],
        );
    }

    /** @param array<string, mixed> $vendor
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>|null  $clientPosting
     * @return array<string, mixed>
     */
    public function postVendorPayment(array $vendor, array $payment, array $actor, mixed $clientPosting = null): array
    {
        $accounts = $this->activeAccounts();
        $cash = $this->accountByCode($accounts, (string) $payment['cash_account_code']);
        if (! $this->isCashOrBank($cash)) {
            throw new RuntimeException('Select an active cash or bank account.');
        }
        $payable = $this->findAccount($accounts, fn (array $account): bool => $account['type'] === 'Liability' && $this->nameContains($account, 'payable') && ! $this->nameContains($account, 'tax'));
        $total = $this->money($payment['amount']);

        return $this->postSource(
            'vendor-payment:'.$payment['request_token'],
            [
                'date' => $payment['payment_date'],
                'reference' => trim((string) ($payment['reference'] ?? '')) ?: 'Vendor payment',
                'description' => 'Vendor payment - '.$vendor['name'],
                'source_type' => 'Vendor Payment',
                'lines' => [
                    $this->line($payable, 'Vendor payment applied', (string) $vendor['code'], $total, 0),
                    $this->line($cash, 'Vendor payment disbursed', (string) $vendor['code'], 0, $total),
                ],
            ],
            $actor,
            $clientPosting,
            ['request_token' => $payment['request_token'], 'vendor_id' => $vendor['id']],
        );
    }

    /** @param array<string, mixed> $transaction
     * @param  array<string, mixed>  $actor
     * @return array<string, mixed>
     */
    public function postCashBankTransaction(array $transaction, array $actor): array
    {
        $accounts = $this->activeAccounts();
        $type = (string) $transaction['type'];
        $amount = $this->money($transaction['amount']);
        $reference = trim((string) ($transaction['reference'] ?? '')) ?: ucfirst(str_replace('_', ' ', $type));
        $description = trim((string) ($transaction['description'] ?? '')) ?: $reference;

        if ($type === 'transfer') {
            $from = $this->accountByCode($accounts, (string) $transaction['from_account_code']);
            $to = $this->accountByCode($accounts, (string) $transaction['to_account_code']);
            if (! $this->isCashOrBank($from) || ! $this->isCashOrBank($to) || $from['code'] === $to['code']) {
                throw new RuntimeException('Transfer requires two different active cash or bank accounts.');
            }
            $lines = [
                $this->line($to, 'Transfer received - '.$description, '', $amount, 0),
                $this->line($from, 'Transfer sent - '.$description, '', 0, $amount),
            ];
        } else {
            $cash = $this->accountByCode($accounts, (string) $transaction['account_code']);
            $offset = $this->accountByCode($accounts, (string) $transaction['offset_account_code']);
            if (! $this->isCashOrBank($cash) || $cash['code'] === $offset['code']) {
                throw new RuntimeException('Select a valid cash account and a different offset account.');
            }
            $inflow = in_array($type, ['deposit', 'interest'], true);
            $lines = $inflow
                ? [$this->line($cash, $description, '', $amount, 0), $this->line($offset, $description, '', 0, $amount)]
                : [$this->line($offset, $description, '', $amount, 0), $this->line($cash, $description, '', 0, $amount)];
        }

        return $this->postSource(
            'cash-bank:'.$transaction['request_token'],
            [
                'date' => $transaction['date'],
                'reference' => $reference,
                'description' => $description,
                'source_type' => $type === 'transfer' ? 'Bank Transfer' : 'Cash/Bank',
                'lines' => $lines,
            ],
            $actor,
            null,
            ['request_token' => $transaction['request_token'], 'cash_bank_type' => $type],
        );
    }

    /** @param array<string, mixed> $expense
     * @param  array<string, mixed>  $actor
     * @return array<string, mixed>
     */
    public function postExpense(array $expense, array $actor): array
    {
        $accounts = $this->activeAccounts();
        $category = $this->accountByCode($accounts, (string) $expense['category_account_code']);
        if ($category['type'] !== 'Expense') {
            throw new RuntimeException('Select an active expense account.');
        }

        $lines = [$this->line($category, (string) $expense['memo'], (string) $expense['payee'], (float) $expense['subtotal'], 0)];
        if ((float) $expense['tax'] > 0) {
            $inputTax = $this->findAccount($accounts, fn (array $account): bool => $this->nameContains($account, 'input tax'), false);
            if ($inputTax === null) {
                throw new RuntimeException('Posting a taxed expense needs an active Input Tax account.');
            }
            $lines[] = $this->line($inputTax, 'Input tax '.$expense['expense_number'], (string) $expense['payee'], (float) $expense['tax'], 0);
        }

        if ($expense['payment_status'] === 'Paid') {
            $creditAccount = $this->accountByCode($accounts, (string) $expense['cash_account_code']);
            if (! $this->isCashOrBank($creditAccount)) {
                throw new RuntimeException('Paid expenses require an active cash or bank account.');
            }
            if ((float) $creditAccount['balance'] < (float) $expense['total']) {
                throw new RuntimeException('Expense total exceeds the available cash or bank balance.');
            }
        } else {
            $creditAccount = $this->findAccount($accounts, fn (array $account): bool => $account['type'] === 'Liability' && $this->nameContains($account, 'payable') && ! $this->nameContains($account, 'tax'));
        }
        $lines[] = $this->line($creditAccount, 'Payment for '.$expense['expense_number'], (string) $expense['payee'], 0, (float) $expense['total']);

        return $this->postSource(
            'expense:'.$expense['expense_number'],
            [
                'date' => $expense['date'],
                'reference' => $expense['expense_number'],
                'description' => 'Expense '.$expense['expense_number'].' - '.$expense['payee'],
                'source_type' => 'Expense',
                'lines' => $lines,
            ],
            $actor,
            null,
            ['expense_number' => $expense['expense_number']],
        );
    }

    /** @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    public function postManual(string $journalNumber, array $actor): array
    {
        $entry = $this->journals->find($journalNumber);
        $this->assertBalanced($entry['lines']);
        $entry = $this->journals->post($journalNumber, $actor, [
            'source_key' => 'manual:'.$journalNumber,
            'posting_engine' => self::ENGINE_VERSION,
        ]);
        $this->accounts->applyJournalLines($entry['lines']);
        $this->auditLogs->record($actor, 'posted', $journalNumber, ['posting_engine' => self::ENGINE_VERSION]);

        return $entry;
    }

    /** @param array<string, mixed> $actor
     * @return array{entry: array<string, mixed>, reversal: array<string, mixed>}
     */
    public function reverse(string $journalNumber, array $actor): array
    {
        $entry = $this->journals->find($journalNumber);
        if (($entry['source_type'] ?? 'Manual') !== 'Manual') {
            throw new RuntimeException('Direct reversal is disabled for source-generated journals because source-document voiding is not implemented.');
        }
        $result = $this->journals->reverse($journalNumber, $actor);
        $this->assertBalanced($result['reversal']['lines']);
        $this->accounts->applyJournalLines($result['reversal']['lines']);
        $this->auditLogs->record($actor, 'reversed', $journalNumber, [
            'reversal_entry_number' => $result['reversal']['journal_number'],
            'posting_engine' => self::ENGINE_VERSION,
        ]);

        return $result;
    }

    /** @param array<string, mixed> $attributes
     * @param  array<string, mixed>  $actor
     * @param  array<string, mixed>|null  $clientPosting
     * @param  array<string, mixed>  $auditDetails
     * @return array<string, mixed>
     */
    private function postSource(string $sourceKey, array $attributes, array $actor, mixed $clientPosting, array $auditDetails): array
    {
        $lines = $this->normalizeLines($attributes['lines']);
        $totals = $this->assertBalanced($lines);
        $canonical = [
            ...$attributes,
            'source_key' => $sourceKey,
            'posting_engine' => self::ENGINE_VERSION,
            'lines' => $lines,
            ...$totals,
            'created_by' => $this->actorSnapshot($actor),
        ];
        $this->assertClientPosting($clientPosting, $canonical);

        $existing = collect($this->journals->all())->first(function (array $entry) use ($sourceKey, $attributes): bool {
            if (($entry['source_key'] ?? null) === $sourceKey) {
                return true;
            }

            return in_array($attributes['source_type'], ['Invoice', 'Bill'], true)
                && $entry['source_type'] === $attributes['source_type']
                && $entry['reference'] === $attributes['reference'];
        });
        if ($existing) {
            if ($existing['status'] !== 'Posted') {
                throw new RuntimeException('Journal entry already exists for this source but is not posted. Review it in Journal Entries.');
            }

            return $existing;
        }

        $journal = $this->journals->create($canonical);
        $this->journals->submitForReview($journal['journal_number']);
        $journal = $this->journals->post($journal['journal_number'], $actor);
        $this->accounts->applyJournalLines($journal['lines']);
        $this->auditLogs->record($actor, 'posted_from_source', $journal['journal_number'], [
            ...$auditDetails,
            'source_key' => $sourceKey,
            'posting_engine' => self::ENGINE_VERSION,
        ]);

        return $journal;
    }

    /** @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(array $lines): array
    {
        return array_values(array_map(fn (array $line, int $index): array => [
            ...$line,
            'id' => $index + 1,
            'account_code' => (string) $line['account_code'],
            'debit' => $this->money($line['debit'] ?? 0),
            'credit' => $this->money($line['credit'] ?? 0),
        ], $lines, array_keys($lines)));
    }

    /** @param array<int, array<string, mixed>> $lines
     * @return array{total_debit: float, total_credit: float}
     */
    private function assertBalanced(array $lines): array
    {
        if (count($lines) < 2) {
            throw new RuntimeException('Journal entry needs at least two lines.');
        }

        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $lineDebit = $this->money($line['debit'] ?? 0);
            $lineCredit = $this->money($line['credit'] ?? 0);
            if ($lineDebit < 0 || $lineCredit < 0 || (($lineDebit > 0) === ($lineCredit > 0))) {
                throw new RuntimeException('Each journal line needs one non-negative debit or credit amount.');
            }
            $debit += $lineDebit;
            $credit += $lineCredit;
        }
        $debit = $this->money($debit);
        $credit = $this->money($credit);
        if ($debit <= 0 || abs($debit - $credit) > 0.004) {
            throw new RuntimeException('Journal entry must balance before posting.');
        }

        return ['total_debit' => $debit, 'total_credit' => $credit];
    }

    /** @param array<string, mixed>|null $clientPosting
     * @param  array<string, mixed>  $canonical
     */
    private function assertClientPosting(mixed $clientPosting, array $canonical): void
    {
        if ($clientPosting === null) {
            return;
        }
        if (! is_array($clientPosting)
            || ! is_array($clientPosting['lines'] ?? null)
            || ! isset($clientPosting['engine'], $clientPosting['source_key'], $clientPosting['date'], $clientPosting['source_type'])) {
            throw new RuntimeException('Posting preview is invalid. Refresh and retry.');
        }
        foreach ($clientPosting['lines'] as $line) {
            if (! is_array($line)
                || ! array_key_exists('account_code', $line)
                || ! array_key_exists('debit', $line)
                || ! array_key_exists('credit', $line)) {
                throw new RuntimeException('Posting preview is invalid. Refresh and retry.');
            }
        }

        $clientLines = $this->normalizeLines((array) ($clientPosting['lines'] ?? []));
        $client = [
            'engine' => (string) ($clientPosting['engine'] ?? ''),
            'source_key' => (string) ($clientPosting['source_key'] ?? ''),
            'date' => (string) ($clientPosting['date'] ?? ''),
            'source_type' => (string) ($clientPosting['source_type'] ?? ''),
            'lines' => array_map(fn (array $line): array => [
                'account_code' => $line['account_code'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ], $clientLines),
        ];
        $expected = [
            'engine' => self::ENGINE_VERSION,
            'source_key' => $canonical['source_key'],
            'date' => $canonical['date'],
            'source_type' => $canonical['source_type'],
            'lines' => array_map(fn (array $line): array => [
                'account_code' => $line['account_code'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ], $canonical['lines']),
        ];
        if ($client !== $expected) {
            throw new RuntimeException('Posting preview does not match accounting engine rules. Refresh and retry.');
        }
    }

    /** @param array<int, array<string, mixed>> $accounts
     * @return array<string, mixed>
     */
    private function accountByCode(array $accounts, string $code): array
    {
        return $this->findAccount($accounts, static fn (array $account): bool => (string) $account['code'] === $code);
    }

    /** @param array<int, array<string, mixed>> $accounts
     * @param  callable(array<string, mixed>): bool  $predicate
     * @return array<string, mixed>|null
     */
    private function findAccount(array $accounts, callable $predicate, bool $required = true): ?array
    {
        foreach ($accounts as $account) {
            if ($predicate($account)) {
                return $account;
            }
        }
        if ($required) {
            throw new RuntimeException('Required active account mapping is missing from Chart of Accounts.');
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function activeAccounts(): array
    {
        return $this->accounts->all(['status' => 'Active']);
    }

    /** @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    private function line(?array $account, string $description, string $partyReference, float $debit, float $credit): array
    {
        if ($account === null) {
            throw new RuntimeException('Required active account mapping is missing from Chart of Accounts.');
        }

        return [
            'account_code' => (string) $account['code'],
            'account_name' => (string) $account['name'],
            'description' => $description,
            'party_reference' => $partyReference,
            'cost_center' => '',
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    /** @param array<string, mixed> $account */
    private function nameContains(array $account, string $needle): bool
    {
        return str_contains(Str::lower((string) $account['name']), $needle);
    }

    /** @param array<string, mixed> $account */
    private function isCashOrBank(array $account): bool
    {
        $text = Str::lower($account['name'].' '.$account['sub_type']);

        return $account['type'] === 'Asset' && (str_contains($text, 'cash') || str_contains($text, 'bank'));
    }

    /** @param array<string, mixed> $account */
    private function isPurchaseAccount(array $account): bool
    {
        return $account['type'] === 'Expense'
            || ($account['type'] === 'Asset' && ! $this->isCashOrBank($account) && ! $this->nameContains($account, 'receivable') && ! $this->nameContains($account, 'input tax'));
    }

    private function money(mixed $amount): float
    {
        return round((float) $amount, 2);
    }

    /** @param array<string, mixed> $actor
     * @return array{id: int|string|null, name: string}
     */
    private function actorSnapshot(array $actor): array
    {
        return ['id' => $actor['id'] ?? null, 'name' => (string) ($actor['name'] ?? 'Demo User')];
    }
}
