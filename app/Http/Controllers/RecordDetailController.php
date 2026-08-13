<?php

namespace App\Http\Controllers;

use App\Services\DemoAccessService;
use App\Services\DemoData\AccountDataService;
use App\Services\DemoData\ExpenseDataService;
use App\Services\DemoData\ExpensePaymentDataService;
use App\Services\DemoData\JournalEntryDataService;
use App\Services\DemoData\PurchaseDataService;
use App\Services\DemoData\SalesDataService;
use App\Services\DemoData\TaxCodeDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RecordDetailController extends Controller
{
    public function show(
        Request $request,
        string $resource,
        string $identifier,
        SalesDataService $sales,
        PurchaseDataService $purchases,
        JournalEntryDataService $journals,
        ExpenseDataService $expenses,
        ExpensePaymentDataService $expensePayments,
        TaxCodeDataService $taxCodes,
        AccountDataService $accounts,
        DemoAccessService $access,
    ): JsonResponse {
        if (! $request->session()->has('demo_user')) {
            return response()->json(['message' => 'Authentication is required.'], 401);
        }

        try {
            $detail = match ($resource) {
                'sales_invoice' => $this->invoice($sales->findInvoice($identifier)),
                'vendor_bill' => $this->bill($purchases->findBill($identifier)),
                'journal_entry' => $this->journal($journals->find($identifier)),
                'expense' => $this->expense($expenses->find($identifier)),
                'expense_payment' => $this->expensePayment($expensePayments->find($identifier)),
                'customer_payment' => $this->customerPayment($sales->findPayment($identifier)),
                'vendor_payment' => $this->vendorPayment($purchases->findPayment($identifier)),
                'customer' => $this->customer($this->findParty($sales->customers(), $identifier, 'customer')),
                'vendor' => $this->vendor($this->findParty($purchases->vendors(), $identifier, 'vendor')),
                'tax_code' => $this->taxCode($this->findBy($taxCodes->all(), 'code', $identifier, 'tax code')),
                'account' => $this->account($this->findBy($accounts->all(), 'code', $identifier, 'account')),
                default => throw new RuntimeException('Record type is not supported.'),
            };
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        if ($access->isViewer($request) && ! $this->viewerMaySee($resource, (string) ($detail['status'] ?? ''))) {
            return response()->json(['message' => 'This record is not available to Viewer / Auditor.'], 403);
        }

        return response()->json(['record' => $detail]);
    }

    /** @param array<string, mixed> $row */
    private function invoice(array $row): array
    {
        return $this->record('Sales Invoice', (string) $row['invoice_number'], (string) $row['display_status'], [
            ['Customer', $row['customer_name']], ['Customer Code', $row['customer_code']],
            ['Invoice Date', $row['invoice_date']], ['Due Date', $row['due_date']],
            ['Reference', $row['reference'] ?: '—'], ['Journal Entry', $row['journal_entry_id'] ?: 'Not posted'],
            ['Subtotal', $this->money($row['subtotal'])], ['Tax', $this->money($row['tax'])],
            ['Discount', $this->money($row['discount'] ?? 0)], ['Total', $this->money($row['total'])],
            ['Paid', $this->money($row['amount_paid'])], ['Balance', $this->money($row['remaining_balance'])],
            ['Memo', $row['memo'] ?: '—'], ['Created By', $row['created_by']['name'] ?? '—'],
        ], [$this->lineSection('Invoice Lines', $row['lines'] ?? [], false)]);
    }

    /** @param array<string, mixed> $row */
    private function bill(array $row): array
    {
        return $this->record('Vendor Bill', (string) $row['bill_number'], (string) $row['display_status'], [
            ['Vendor', $row['vendor_name']], ['Vendor Code', $row['vendor_code']],
            ['Bill Date', $row['bill_date']], ['Due Date', $row['due_date']],
            ['Reference', $row['reference'] ?: '—'], ['Journal Entry', $row['journal_entry_id'] ?: 'Not posted'],
            ['Subtotal', $this->money($row['subtotal'])], ['Tax', $this->money($row['tax'])],
            ['Total', $this->money($row['total'])], ['Paid', $this->money($row['amount_paid'])],
            ['Balance', $this->money($row['remaining_balance'])], ['Memo', $row['memo'] ?: '—'],
            ['Attachment', $row['attachment']['name'] ?? 'None'], ['Created By', $row['created_by']['name'] ?? '—'],
        ], [$this->lineSection('Bill Lines', $row['lines'] ?? [], true)]);
    }

    /** @param array<string, mixed> $row */
    private function journal(array $row): array
    {
        $lines = array_map(fn (array $line): array => [
            (string) ($line['account_code'] ?? ''), (string) ($line['account_name'] ?? ''),
            (string) ($line['description'] ?? ''), $this->money($line['debit'] ?? 0), $this->money($line['credit'] ?? 0),
        ], $row['lines'] ?? []);

        return $this->record('Journal Entry', (string) $row['journal_number'], (string) $row['status'], [
            ['Date', $row['date']], ['Reference', $row['reference'] ?: '—'],
            ['Source', $row['source_type']], ['Description', $row['description']],
            ['Total Debit', $this->money($row['total_debit'])], ['Total Credit', $this->money($row['total_credit'])],
            ['Created By', $row['created_by']['name'] ?? '—'], ['Posted By', $row['posted_by']['name'] ?? '—'],
            ['Reversal Of', $row['reversal_of'] ?? '—'], ['Reversal Entry', $row['reversal_entry_number'] ?? '—'],
        ], [['title' => 'Journal Lines', 'columns' => ['Account', 'Account Name', 'Description', 'Debit', 'Credit'], 'rows' => $lines]]);
    }

    /** @param array<string, mixed> $row */
    private function expense(array $row): array
    {
        return $this->record('Expense', (string) $row['expense_number'], (string) $row['status'], [
            ['Date', $row['date']], ['Payee', $row['payee']], ['Category', $row['category_name']],
            ['Payment Status', $row['payment_status']], ['Payment Method', $row['payment_method'] ?: 'Pay later'],
            ['Due Date', $row['due_date'] ?? '—'], ['Cash Account', $row['cash_account_code'] ?: '—'],
            ['Subtotal', $this->money($row['subtotal'])], ['Tax Rate', number_format((float) $row['tax_rate'], 2).'%'],
            ['Tax', $this->money($row['tax'])], ['Total', $this->money($row['total'])],
            ['Source Journal', $row['journal_entry_id'] ?: 'Not posted'],
            ['Expense Payment', $row['expense_payment_number'] ?? '—'],
            ['Payment Journal', $row['payment_journal_entry_id'] ?? '—'],
            ['Reversal Journal', $row['reversal_journal_entry_id'] ?? '—'],
            ['Receipt', $row['receipt']['name'] ?? 'None'], ['Memo', $row['memo'] ?: '—'],
        ]);
    }

    /** @param array<string, mixed> $row */
    private function expensePayment(array $row): array
    {
        return $this->record('Expense Payment', (string) $row['payment_number'], (string) $row['status'], [
            ['Expense', $row['expense_number']], ['Payee', $row['payee']], ['Payment Date', $row['payment_date']],
            ['Amount', $this->money($row['amount'])], ['Method', $row['payment_method']],
            ['Cash Account', $row['cash_account_code']], ['Reference', $row['reference'] ?: '—'],
            ['Journal Entry', $row['journal_entry_id'] ?? 'Not posted'],
            ['Reversal Journal', $row['reversal_journal_entry_id'] ?? '—'], ['Memo', $row['memo'] ?: '—'],
        ]);
    }

    /** @param array<string, mixed> $row */
    private function customerPayment(array $row): array
    {
        return $this->payment('Customer Payment', (string) $row['receipt_number'], $row, 'Customer', 'customer_name', 'invoice_number');
    }

    /** @param array<string, mixed> $row */
    private function vendorPayment(array $row): array
    {
        return $this->payment('Vendor Payment', (string) $row['payment_number'], $row, 'Vendor', 'vendor_name', 'bill_number');
    }

    /** @param array<string, mixed> $row */
    private function payment(string $title, string $number, array $row, string $partyLabel, string $partyKey, string $allocationKey): array
    {
        $allocations = array_map(fn (array $allocation): array => [
            (string) ($allocation[$allocationKey] ?? ''),
            $this->money($allocation['invoice_total'] ?? $allocation['bill_total'] ?? 0),
            $this->money($allocation['amount'] ?? 0),
        ], $row['allocations'] ?? []);

        return $this->record($title, $number, (string) ($row['status'] ?? 'Posted'), [
            ['Payment Date', $row['payment_date']], [$partyLabel, $row[$partyKey]],
            ['Cash / Bank Account', trim(($row['cash_account_code'] ?? '').' — '.($row['cash_account_name'] ?? ''), ' —')],
            ['Reference', $row['reference'] ?: '—'], ['Amount', $this->money($row['amount'])],
            ['Journal Entry', $row['journal_entry_id'] ?: 'Not posted'], ['Memo', $row['memo'] ?: '—'],
            ['Created By', $row['created_by']['name'] ?? $row['posted_by']['name'] ?? '—'],
        ], [['title' => 'Allocations', 'columns' => [$allocationKey === 'invoice_number' ? 'Invoice' : 'Bill', 'Document Total', 'Applied Amount'], 'rows' => $allocations]]);
    }

    /** @param array<string, mixed> $row */
    private function customer(array $row): array
    {
        return $this->record('Customer', (string) $row['code'], (string) $row['status'], [
            ['Name', $row['name']], ['Contact Person', $row['contact_person'] ?: '—'], ['Email', $row['email'] ?: '—'],
            ['Phone', $row['phone'] ?: '—'], ['Billing Address', $row['billing_address'] ?: '—'], ['Tax ID', $row['tax_id'] ?: '—'],
            ['Credit Terms', $row['credit_terms_days'].' days'], ['Opening Balance', $this->money($row['opening_balance'])],
        ]);
    }

    /** @param array<string, mixed> $row */
    private function vendor(array $row): array
    {
        return $this->record('Vendor', (string) $row['code'], (string) $row['status'], [
            ['Name', $row['name']], ['Contact Person', $row['contact_person'] ?: '—'], ['Email', $row['email'] ?: '—'],
            ['Phone', $row['phone'] ?: '—'], ['Address', $row['address'] ?: '—'], ['Tax ID', $row['tax_id'] ?: '—'],
            ['Payment Terms', $row['payment_terms_days'].' days'], ['Opening Balance', $this->money($row['opening_balance'])],
        ]);
    }

    /** @param array<string, mixed> $row */
    private function taxCode(array $row): array
    {
        return $this->record('Tax Code', (string) $row['code'], (string) $row['status'], [
            ['Name', $row['name']], ['Rate', number_format((float) $row['rate'], 2).'%'], ['Type', $row['type']],
            ['Applies To', $row['applies_to']], ['Default', $row['is_default'] ? 'Yes' : 'No'],
        ]);
    }

    /** @param array<string, mixed> $row */
    private function account(array $row): array
    {
        return $this->record('Account', (string) $row['code'], (string) $row['status'], [
            ['Name', $row['name']], ['Type', $row['type']], ['Sub-type', $row['sub_type'] ?: '—'],
            ['Current Balance', $this->money($row['balance'])],
        ]);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function findParty(array $rows, string $identifier, string $label): array
    {
        foreach ($rows as $row) {
            if ((string) ($row['id'] ?? '') === $identifier || (string) ($row['code'] ?? '') === $identifier) {
                return $row;
            }
        }
        throw new RuntimeException("The {$label} could not be found.");
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function findBy(array $rows, string $key, string $identifier, string $label): array
    {
        foreach ($rows as $row) {
            if ((string) ($row[$key] ?? '') === $identifier) return $row;
        }
        throw new RuntimeException("The {$label} could not be found.");
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function lineSection(string $title, array $lines, bool $includeAccount): array
    {
        $columns = $includeAccount ? ['Account', 'Description', 'Qty', 'Unit Price', 'Tax', 'Total'] : ['Description', 'Qty', 'Unit Price', 'Tax', 'Total'];
        $rows = array_map(function (array $line) use ($includeAccount): array {
            $values = [(string) ($line['description'] ?? ''), (string) ($line['quantity'] ?? ''), $this->money($line['unit_price'] ?? 0), $this->money($line['tax'] ?? 0), $this->money($line['total'] ?? 0)];
            if ($includeAccount) array_unshift($values, trim(($line['account_code'] ?? '').' — '.($line['account_name'] ?? ''), ' —'));
            return $values;
        }, $lines);
        return ['title' => $title, 'columns' => $columns, 'rows' => $rows];
    }

    /** @param array<int, array{0: string, 1: mixed}> $fields
     * @param array<int, array<string, mixed>> $sections
     */
    private function record(string $title, string $identifier, string $status, array $fields, array $sections = []): array
    {
        return ['title' => $title, 'identifier' => $identifier, 'status' => $status, 'fields' => array_map(fn (array $field): array => ['label' => $field[0], 'value' => (string) ($field[1] ?? '—')], $fields), 'sections' => $sections];
    }

    private function viewerMaySee(string $resource, string $status): bool
    {
        return match ($resource) {
            'sales_invoice', 'vendor_bill', 'customer_payment', 'vendor_payment', 'journal_entry' => in_array($status, ['Posted', 'Paid', 'Partially Paid', 'Unpaid', 'Overdue', 'Reversed'], true),
            'expense' => in_array($status, ['Approved', 'Reversed'], true),
            'expense_payment' => in_array($status, ['Posted', 'Reversed'], true),
            default => true,
        };
    }

    private function money(mixed $value): string
    {
        return '₱'.number_format((float) $value, 2);
    }
}
