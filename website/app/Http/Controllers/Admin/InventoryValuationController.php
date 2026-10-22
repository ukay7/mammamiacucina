<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Warehouse;
use App\Services\DocumentTheme;
use App\Services\InventoryProducts;
use App\Services\InventoryValuation;
use App\Support\NumberDisplay;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class InventoryValuationController extends Controller
{
    public function index(Request $request, InventoryProducts $inventory, InventoryValuation $report)
    {
        $filters = $inventory->filters($request);
        $selected = $report->selected($request);
        $query = $inventory->query($filters, isset($filters['warehouse']) ? (int) $filters['warehouse'] : null);
        [$summary, $products] = DB::transaction(function () use ($inventory, $report, $query, $filters) {
            return [$inventory->summary($query), $report->withColumns($query)->paginate((int) ($filters['per_page'] ?? 20))->withQueryString()];
        });

        return view('admin.inventory.valuation', [
            'filters' => $filters, 'selected' => $selected, 'summary' => $summary,
            'products' => $products, 'report' => $report, 'extraLabels' => $report->extraLabels(),
            'categories' => Category::orderBy('name')->get(),
            'warehouses' => Warehouse::orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request, InventoryProducts $inventory, InventoryValuation $report)
    {
        $request->validate(['format' => 'required|in:xlsx,csv,pdf']);
        $filters = $inventory->filters($request);
        $selected = $report->selected($request);
        $headers = $report->headers($selected);
        $query = $inventory->query($filters, isset($filters['warehouse']) ? (int) $filters['warehouse'] : null);
        if ($selected) {
            $report->withColumns($query);
        }
        $filename = 'inventory-valuation-'.now()->format('Y-m-d-His');

        if ($request->input('format') === 'pdf') {
            // Keep all selected columns readable: repeat the six identity/value columns
            // in landscape A4 sections containing up to three additional fields each.
            $groups = array_chunk($selected, 3) ?: [[]];
            [$summary, $rows] = DB::transaction(function () use ($inventory, $report, $query, $selected) {
                return [$inventory->summary($query), $query->lazy(200)->map(fn ($product) => [
                    'values' => $report->values($product, (bool) $selected), 'missing' => $report->missingPrice($product),
                ])->all()];
            });
            $doc = ['brand' => app(DocumentTheme::class)->brand(), 'title' => 'Inventory Valuation',
                'number' => 'Current stock · EUR', 'date' => now()->format('d M Y H:i').' '.config('app.timezone')];
            $warehouse = isset($filters['warehouse']) ? Warehouse::findOrFail($filters['warehouse'])->name : 'All warehouses combined';
            $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'defaultMediaType' => 'print']));
            $pdf->loadHtml(view('documents.inventory-valuation', compact('doc', 'rows', 'headers', 'groups', 'summary', 'report', 'warehouse', 'filters'))->render());
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();
            $pdf->getCanvas()->page_text(725, 579, 'Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('Helvetica'), 8);

            return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"']);
        }

        if ($request->input('format') === 'csv') {
            return response()->streamDownload(function () use ($query, $headers, $selected, $inventory, $report) {
                DB::transaction(function () use ($query, $headers, $selected, $inventory, $report) {
                    $summary = $inventory->summary($query);
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, [...array_values($headers), 'Price status'], ',', '"', '');
                    foreach ($query->lazy(200) as $product) {
                        $values = $report->values($product, (bool) $selected);
                        $row = [];
                        foreach ($headers as $key => $label) {
                            $value = $values[$key];
                            $row[] = $report->numeric($key) && is_numeric($value)
                                ? $value : $this->csvText($value === null ? ($key === 'inventory_quantity' ? 'Not set' : '') : (string) $value);
                        }
                        $row[] = $report->missingPrice($product) ? 'Missing price' : 'Priced';
                        fputcsv($out, $row, ',', '"', '');
                    }
                    $total = array_fill(0, count($headers) + 1, '');
                    $total[0] = 'Grand total (EUR)';
                    $total[5] = $summary['total'];
                    fputcsv($out, $total, ',', '"', '');
                    fclose($out);
                });
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($query, $headers, $selected, $inventory, $report) {
            DB::transaction(function () use ($query, $headers, $selected, $inventory, $report) {
                $summary = $inventory->summary($query);
                $path = tempnam(sys_get_temp_dir(), 'mmc-valuation-');
                $writer = new Writer;
                try {
                    $writer->openToFile($path);
                    $sheet = $writer->getCurrentSheet();
                    $sheet->setColumnWidth(14, 1, 2, 4, 5, 6);
                    $sheet->setColumnWidth(34, 3);
                    if (count($headers) > 6) {
                        $sheet->setColumnWidth(24, ...range(7, count($headers)));
                    }
                    $headerStyle = (new Style)->setFontBold()->setBackgroundColor('F7F0E6')->setShouldWrapText();
                    $writer->addRow(Row::fromValues(array_values($headers), $headerStyle));
                    foreach ($query->lazy(200) as $product) {
                        $values = $report->values($product, (bool) $selected);
                        $cells = [];
                        foreach ($headers as $key => $label) {
                            $style = (new Style)->setShouldWrapText();
                            if ($key === 'supplier_unit_price_eur' && $report->missingPrice($product)) {
                                $style->setBackgroundColor('FDE2E5')->setFontColor('A00020');
                            }
                            $value = $values[$key];
                            if ($report->numeric($key) && is_numeric($value)) {
                                $style->setFormat(match ($key) {
                                    'record_id' => '0', 'supplier_unit_price_eur' => '0.00######',
                                    'inventory_quantity' => '0.###',
                                    default => NumberDisplay::mode($key) === 'quantity' ? '0.###' : '0.00',
                                });
                                $cells[] = Cell::fromValue((float) $value, $style);
                            } else {
                                $cells[] = new StringCell($value === null ? ($key === 'inventory_quantity' ? 'Not set' : '') : (string) $value, $style);
                            }
                        }
                        $writer->addRow(new Row($cells));
                    }
                    $cells = array_fill(0, count($headers), '');
                    $cells[0] = 'Grand total (EUR)';
                    $cells[5] = (float) $summary['total'];
                    $writer->addRow(Row::fromValues($cells, (new Style)->setFontBold()->setFormat('0.00')->setBackgroundColor('F7F0E6')));
                    $writer->close();
                    readfile($path);
                } finally {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
            });
        }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function csvText(string $value): string
    {
        return preg_match('/^[=+@\-\t\r\n]/', $value) ? "'".$value : $value;
    }
}
