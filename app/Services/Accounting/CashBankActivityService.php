<?php

namespace App\Services\Accounting;

use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\CashBankDataService;
use App\Services\DemoData\JournalEntryDataService;
use Illuminate\Support\Str;
use RuntimeException;

class CashBankActivityService
{
    public function __construct(
        private AccountDataService $accounts,
        private CashBankDataService $cashBank,
        private JournalEntryDataService $journals,
    ) {}

    /** @param array<string, mixed> $account */
    public function isCashOrBank(array $account): bool
    {
        return $account['type'] === 'Asset'
            && in_array(Str::lower(trim((string) ($account['sub_type'] ?? ''))), ['cash', 'bank'], true);
    }

    /** @return array<int, array<string, mixed>> */
    public function cashAccounts(bool $activeOnly = false): array
    {
        return collect($this->accounts->all($activeOnly ? ['status' => 'Active'] : []))
            ->filter(fn (array $account): bool => $this->isCashOrBank($account))
            ->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function eligibleOffsetAccounts(string $type, string $purpose, string $cashCode = ''): array
    {
        $allowed = $this->allowedTypes($type, $purpose);
        $controlCodes = $this->controlAccountCodes();

        return collect($this->accounts->all(['status' => 'Active']))
            ->filter(function (array $account) use ($allowed, $cashCode, $controlCodes): bool {
                return (string) $account['code'] !== $cashCode
                    && ! $this->isCashOrBank($account)
                    && ! in_array((string) $account['code'], $controlCodes, true)
                    && in_array((string) $account['type'], $allowed, true);
            })
            ->values()->all();
    }

    public function assertEligibleOffset(string $type, string $purpose, string $cashCode, string $offsetCode): void
    {
        $eligible = collect($this->eligibleOffsetAccounts($type, $purpose, $cashCode))->keyBy('code');
        if (! $eligible->has($offsetCode)) {
            throw new RuntimeException('Select an eligible active offset account for this transaction purpose.');
        }
    }

    /** @return array<int, string> */
    public function purposesFor(string $type): array
    {
        return match ($type) {
            'deposit' => ['revenue', 'owner_contribution', 'liability', 'asset_recovery'],
            'withdrawal' => ['expense', 'asset_purchase', 'liability_payment', 'owner_distribution'],
            'charge' => ['bank_charge'],
            'interest' => ['interest_income'],
            'adjustment_increase' => ['revenue', 'owner_contribution', 'liability', 'asset_recovery'],
            'adjustment_decrease' => ['expense', 'asset_purchase', 'liability_payment', 'owner_distribution'],
            default => [],
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function movements(): array
    {
        $accounts = collect($this->accounts->all())->keyBy('code');
        $journals = collect($this->journals->all())->keyBy('journal_number');
        $stored = $this->cashBank->transactions();
        $linkedJournals = collect($stored)->pluck('journal_entry_id')->filter()->map(fn ($id): string => (string) $id)->all();
        $reconciled = collect($this->cashBank->reconciliations())->flatMap(function (array $row): array {
            if (isset($row['movement_ids']) && is_array($row['movement_ids'])) return array_map('strval', $row['movement_ids']);
            return array_map(fn ($id): string => 'transaction:'.$id, (array) ($row['transaction_ids'] ?? []));
        })->all();

        $movements = array_map(function (array $row) use ($accounts, $journals, $reconciled): array {
            $movementId = 'transaction:'.(string) $row['id'];
            $journal = $journals->get((string) ($row['journal_entry_id'] ?? ''));
            $sourceType = (string) ($journal['source_type'] ?? 'Cash/Bank');
            return [
                ...$row,
                'movement_id' => $movementId,
                'source_type' => $sourceType,
                'source_label' => $this->sourceLabel($sourceType),
                'source_url' => $this->sourceUrl($sourceType),
                'cleared' => (bool) ($row['cleared'] ?? false) || in_array($movementId, $reconciled, true),
                'status' => ($row['reversed_at'] ?? null) ? 'Reversed' : (((bool) ($row['cleared'] ?? false) || in_array($movementId, $reconciled, true)) ? 'Cleared' : 'Uncleared'),
                'offset_account_name' => $accounts->get((string) ($row['offset_account_code'] ?? ''))['name'] ?? '',
            ];
        }, $stored);

        foreach ($journals as $journal) {
            if (! in_array((string) ($journal['status'] ?? ''), ['Posted', 'Reversed'], true)
                || in_array((string) $journal['journal_number'], $linkedJournals, true)) {
                continue;
            }

            $cashLines = collect((array) $journal['lines'])->filter(function (array $line) use ($accounts): bool {
                $account = $accounts->get((string) ($line['account_code'] ?? ''));
                return $account !== null && $this->isCashOrBank($account);
            })->values();
            if ($cashLines->isEmpty()) continue;

            foreach ($cashLines as $index => $line) {
                $accountCode = (string) $line['account_code'];
                $movementId = 'journal:'.$journal['journal_number'].':'.($line['id'] ?? $index + 1);
                $counterparts = collect((array) $journal['lines'])
                    ->filter(fn (array $candidate): bool => (string) ($candidate['account_code'] ?? '') !== $accountCode)
                    ->pluck('account_code')->map('strval')->values()->all();
                $offsetCode = count($counterparts) === 1 ? $counterparts[0] : '';
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);
                $sourceType = (string) ($journal['source_type'] ?? 'Manual');
                $movements[] = [
                    'id' => null,
                    'movement_id' => $movementId,
                    'transaction_number' => (string) $journal['journal_number'],
                    'journal_entry_id' => (string) $journal['journal_number'],
                    'date' => (string) $journal['date'],
                    'reference' => (string) ($journal['reference'] ?? ''),
                    'description' => (string) $journal['description'],
                    'type' => $this->derivedType($sourceType, $debit, $credit, $cashLines->count()),
                    'amount' => round($debit + $credit, 2),
                    'account_code' => $accountCode,
                    'offset_account_code' => $offsetCode,
                    'offset_account_name' => $accounts->get($offsetCode)['name'] ?? '',
                    'debit' => $debit,
                    'credit' => $credit,
                    'source_type' => $sourceType,
                    'source_label' => $sourceType === 'Payment' ? ($debit > $credit ? 'Customer Payment' : 'Vendor Payment') : $this->sourceLabel($sourceType),
                    'source_url' => $sourceType === 'Payment'
                        ? ($debit > $credit ? route('accounts-receivable') : route('accounts-payable'))
                        : $this->sourceUrl($sourceType),
                    'cleared' => in_array($movementId, $reconciled, true),
                    'status' => in_array($movementId, $reconciled, true) ? 'Cleared' : 'Uncleared',
                    'derived' => true,
                ];
            }
        }

        usort($movements, static fn (array $left, array $right): int => [
            $right['date'], $right['movement_id'],
        ] <=> [
            $left['date'], $left['movement_id'],
        ]);
        return $movements;
    }

    /** @return array<string, mixed> */
    public function activity(string $accountCode): array
    {
        $account = collect($this->accounts->all())->firstWhere('code', $accountCode);
        if (! $account || ! $this->isCashOrBank($account)) throw new RuntimeException('Cash or bank account was not found.');

        $rows = collect($this->movements())->filter(fn (array $row): bool => $this->touches($row, $accountCode))->values();
        $totalEffect = round((float) $rows->sum(fn (array $row): float => $this->signedAmount($row, $accountCode)), 2);
        $running = round((float) $account['balance'] - $totalEffect, 2);
        $beginning = $running;
        $ordered = $rows->sortBy(fn (array $row): string => $row['date'].'-'.$row['movement_id'])->values();
        $activity = [];
        foreach ($ordered as $row) {
            $effect = $this->signedAmount($row, $accountCode);
            $running = round($running + $effect, 2);
            $activity[] = [
                ...$row,
                'effect' => $effect,
                'debit' => $effect > 0 ? $effect : 0,
                'credit' => $effect < 0 ? abs($effect) : 0,
                'running_balance' => $running,
                'counterpart' => $this->counterpart($row, $accountCode),
            ];
        }

        return ['account' => $account, 'beginning_balance' => $beginning, 'ending_balance' => $running, 'rows' => array_reverse($activity)];
    }

    /** @param array<string, mixed> $row */
    public function touches(array $row, string $accountCode): bool
    {
        return (string) ($row['account_code'] ?? '') === $accountCode
            || (string) ($row['from_account_code'] ?? '') === $accountCode
            || (string) ($row['to_account_code'] ?? '') === $accountCode;
    }

    /** @param array<string, mixed> $row */
    public function signedAmount(array $row, string $accountCode): float
    {
        if (isset($row['cash_effects'][$accountCode])) return round((float) $row['cash_effects'][$accountCode], 2);
        $amount = round((float) ($row['amount'] ?? 0), 2);
        if (($row['type'] ?? '') === 'transfer') return (string) ($row['to_account_code'] ?? '') === $accountCode ? $amount : -$amount;
        if (isset($row['debit']) || isset($row['credit'])) return round((float) ($row['debit'] ?? 0) - (float) ($row['credit'] ?? 0), 2);
        if (($row['type'] ?? '') === 'adjustment') return ($row['direction'] ?? '') === 'increase' ? $amount : -$amount;
        return in_array($row['type'] ?? '', ['deposit', 'interest'], true) ? $amount : -$amount;
    }

    /** @return array<int, string> */
    private function allowedTypes(string $type, string $purpose): array
    {
        if (! in_array($purpose, $this->purposesFor($type), true)) return [];
        return match ($purpose) {
            'revenue', 'interest_income' => ['Revenue'],
            'owner_contribution', 'owner_distribution' => ['Equity'],
            'liability', 'liability_payment' => ['Liability'],
            'asset_recovery', 'asset_purchase' => ['Asset'],
            'expense', 'bank_charge' => ['Expense'],
            default => [],
        };
    }

    /** @return array<int, string> */
    private function controlAccountCodes(): array
    {
        $codes = [];
        foreach ($this->accounts->all() as $account) {
            $actualLabel = Str::lower((string) $account['name'].' '.(string) ($account['sub_type'] ?? ''));
            if (str_contains($actualLabel, 'receivable') || str_contains($actualLabel, 'payable') || str_contains($actualLabel, 'tax')) {
                $codes[] = (string) $account['code'];
            }
        }
        foreach ($this->journals->all() as $journal) {
            $source = (string) ($journal['source_type'] ?? '');
            foreach ((array) ($journal['lines'] ?? []) as $line) {
                $description = Str::lower((string) ($line['description'] ?? ''));
                $isControlUse = ($source === 'Invoice' && (float) ($line['debit'] ?? 0) > 0)
                    || ($source === 'Bill' && (float) ($line['credit'] ?? 0) > 0)
                    || ($source === 'Payment' && ! str_contains($description, 'payment received') && ! str_contains($description, 'payment disbursed'))
                    || str_contains($description, 'tax');
                if ($isControlUse) $codes[] = (string) $line['account_code'];
            }
        }
        return array_values(array_unique($codes));
    }

    private function derivedType(string $sourceType, float $debit, float $credit, int $cashLineCount): string
    {
        if ($sourceType === 'Reversal') return 'reversal';
        if ($sourceType === 'Bank Transfer' || $cashLineCount > 1) return 'transfer';
        if ($sourceType === 'Expense') return 'withdrawal';
        return $debit > $credit ? 'deposit' : 'withdrawal';
    }

    private function sourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'Payment' => 'Receivable / Payable',
            'Expense' => 'Expenses',
            'Bank Transfer' => 'Bank Transfer',
            'Cash Adjustment' => 'Cash Adjustment',
            'Cash/Bank' => 'Cash & Bank',
            'Manual' => 'Journal Entry',
            'Reversal' => 'Reversal',
            default => $sourceType,
        };
    }

    private function sourceUrl(string $sourceType): string
    {
        return match ($sourceType) {
            'Payment' => route('accounts-receivable'),
            'Expense' => route('expenses'),
            'Cash/Bank', 'Bank Transfer', 'Cash Adjustment' => route('cash-bank'),
            default => route('journal-entries'),
        };
    }

    /** @param array<string, mixed> $row */
    private function counterpart(array $row, string $accountCode): string
    {
        if (($row['type'] ?? '') === 'transfer') {
            $other = (string) (($row['from_account_code'] ?? '') === $accountCode ? ($row['to_account_code'] ?? '') : ($row['from_account_code'] ?? ''));
            return (string) (collect($this->accounts->all())->firstWhere('code', $other)['name'] ?? $other);
        }
        return (string) (($row['offset_account_name'] ?? '') ?: ($row['offset_account_code'] ?? 'Multiple accounts'));
    }
}
