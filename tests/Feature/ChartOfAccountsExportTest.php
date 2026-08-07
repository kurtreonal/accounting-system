<?php

namespace Tests\Feature;

use App\Services\DemoData\AccountDataService;
use App\Services\Exports\ChartOfAccountsExportService;
use App\Services\Exports\Pdf\ChartOfAccountsPdf;
use DateTimeImmutable;
use Tests\TestCase;

class ChartOfAccountsExportTest extends TestCase
{
    public function test_csv_export_is_excel_compatible_and_uses_the_shared_dataset(): void
    {
        $response = $this->withSession($this->demoSession())
            ->get(route('chart-of-accounts.export.csv'));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('chart-of-accounts-'.now()->format('Y-m-d').'.csv');

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Account Code', $content);
        $this->assertStringContainsString('Cash on Hand', $content);
    }

    public function test_exports_respect_valid_filters(): void
    {
        $response = $this->withSession($this->demoSession())
            ->get(route('chart-of-accounts.export.csv', ['type' => 'Liability']));

        $content = $response->streamedContent();

        $this->assertStringContainsString('Accounts Payable', $content);
        $this->assertStringNotContainsString('Cash on Hand', $content);
    }

    public function test_pdf_export_downloads_a_valid_fpdf_document(): void
    {
        $response = $this->withSession($this->demoSession())
            ->get(route('chart-of-accounts.export.pdf'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader(
                'content-disposition',
                'inline; filename="chart-of-accounts-'.now()->format('Y-m-d').'.pdf"',
            );

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_template_handles_empty_one_and_multi_page_datasets(): void
    {
        $emptyPdf = new ChartOfAccountsPdf(new DateTimeImmutable);
        $emptyPdf->render([]);
        $this->assertStringStartsWith('%PDF-', $emptyPdf->Output('S'));

        $row = app(AccountDataService::class)->all()[0];
        $row['name'] = str_repeat('Long account description ', 12);

        $oneRowPdf = new ChartOfAccountsPdf(new DateTimeImmutable);
        $oneRowPdf->render([$row]);
        $this->assertSame(1, $oneRowPdf->PageNo());
        $this->assertStringStartsWith('%PDF-', $oneRowPdf->Output('S'));

        $manyRowsPdf = new ChartOfAccountsPdf(new DateTimeImmutable);
        $manyRowsPdf->render(array_fill(0, 80, $row));
        $this->assertGreaterThan(1, $manyRowsPdf->PageNo());
        $this->assertStringStartsWith('%PDF-', $manyRowsPdf->Output('S'));
    }

    public function test_csv_writer_guards_text_against_formula_injection(): void
    {
        $row = app(AccountDataService::class)->all()[0];
        $row['name'] = '=DANGEROUS()';
        $stream = fopen('php://temp', 'w+b');

        $this->assertIsResource($stream);
        app(ChartOfAccountsExportService::class)->writeCsv($stream, [$row]);
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        $this->assertIsString($content);
        $this->assertStringContainsString("'=DANGEROUS()", $content);
    }

    /** @return array{demo_user: array{id: int, name: string, email: string, role: string}} */
    private function demoSession(): array
    {
        return [
            'demo_user' => [
                'id' => 1,
                'name' => 'Maria Santos',
                'email' => 'admin@gmail.com',
                'role' => 'Administrator',
            ],
        ];
    }
}
