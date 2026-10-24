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
use Illuminate\Pagination\LengthAwarePaginator;
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
        $filters = $report->filters($request, $inventory);
        $selected = $report->selected($request);
        $query = $report->query($inventory, $filters);
        [$summary, $products] = DB::transaction(function () use ($report, $query, $filters) {
            return $report->page($query, $filters, max(1, LengthAwarePaginator::resolveCurrentPage()), (int) ($filters['per_page'] ?? 20));
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
        $filters = $report->filters($request, $inventory);
        $selected = $report->selected($request);
        $headers = $report->headers($selected);
        $query = $report->query($inventory, $filters);
        if ($selected) {
            $report->withColumns($query);
        }
        $filename = 'inventory-valuation-'.now()->format('Y-m-d-His');

        if ($request->input('format') === 'pdf') {
            // Keep the calculation legible on A4; repeat identity in each section.
            $core = ['record_id', 'product_code', 'premium_marketing_name', 'currency', 'carton_cost', 'inventory_quantity', 'inventory_value', 'valuation_status'];
            $groups = [
                ['title' => 'Inventory values', 'columns' => $core],
                ['title' => 'Supplier pricing breakdown', 'columns' => ['record_id', 'product_code', 'premium_marketing_name', 'currency', 'purchase_basis', 'purchase_price', 'discount', 'discounted_price', 'pieces_per_carton', 'carton_cost']],
            ];
            foreach (array_chunk($selected, 2) as $extra) {
                $groups[] = ['title' => 'Additional product fields', 'columns' => array_merge($core, $extra)];
            }
            [$summary, $rows] = DB::transaction(function () use ($report, $query, $selected, $filters) {
                return [$report->summary($query, $filters), collect($report->rows($query, $filters))->map(fn ($product) => [
                    'values' => $report->values($product, (bool) $selected), 'missing' => $report->missingPrice($product),
                ])->all()];
            });
            $doc = ['brand' => app(DocumentTheme::class)->brand(), 'title' => 'Inventory Valuation',
                'number' => 'Current stock · EUR / CAD', 'date' => now()->format('d M Y H:i').' '.config('app.timezone')];
            $warehouse = isset($filters['warehouse']) ? Warehouse::findOrFail($filters['warehouse'])->name : 'All warehouses combined';
            $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'defaultMediaType' => 'print']));
            $pdf->loadHtml(view('documents.inventory-valuation', compact('doc', 'rows', 'headers', 'groups', 'summary', 'report', 'warehouse', 'filters'))->render());
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();
            $pdf->getCanvas()->page_text(725, 579, 'Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('Helvetica'), 8);

            return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"']);
        }

        if ($request->input('format') === 'csv') {
            return response()->streamDownload(function () use ($query, $headers, $selected, $report, $filters) {
                DB::transaction(function () use ($query, $headers, $selected, $report, $filters) {
                    $summary = $report->summary($query, $filters);
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, array_values($headers), ',', '"', '');
                    foreach ($report->rows($query, $filters) as $product) {
                        $values = $report->values($product, (bool) $selected);
                        $row = [];
                        foreach ($headers as $key => $label) {
                            $value = $values[$key];
                            $row[] = $report->numeric($key) && is_numeric($value)
                                ? $value : $this->csvText($value === null ? $this->emptyValue($key) : (string) $value);
                        }
                        fputcsv($out, $row, ',', '"', '');
                    }
                    foreach ($this->footerRows($summary, $headers) as $total) {
                        fputcsv($out, $total, ',', '"', '');
                    }
                    fclose($out);
                });
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($query, $headers, $selected, $report, $filters) {
            DB::transaction(function () use ($query, $headers, $selected, $report, $filters) {
                $summary = $report->summary($query, $filters);
                $path = tempnam(sys_get_temp_dir(), 'mmc-valuation-');
                $writer = new Writer;
                try {
                    $writer->openToFile($path);
                    $sheet = $writer->getCurrentSheet();
                    foreach (array_keys($headers) as $index => $key) {
                        $sheet->setColumnWidth(match ($key) {
                            'premium_marketing_name' => 34, 'valuation_status' => 36,
                            'record_id' => 12, default => 20,
                        }, $index + 1);
                    }
                    $headerStyle = (new Style)->setFontBold()->setBackgroundColor('F7F0E6')->setShouldWrapText();
                    $writer->addRow(Row::fromValues(array_values($headers), $headerStyle));
                    foreach ($report->rows($query, $filters) as $product) {
                        $values = $report->values($product, (bool) $selected);
                        $cells = [];
                        foreach ($headers as $key => $label) {
                            $style = (new Style)->setShouldWrapText();
                            if (in_array($key, ['purchase_price', 'discounted_price', 'carton_cost'], true) && $report->missingPrice($product)) {
                                $style->setBackgroundColor('FDE2E5')->setFontColor('A00020');
                            }
                            if ($key === 'valuation_status' && $product->valuation['issues']) {
                                $style->setBackgroundColor('FFF0CC')->setFontColor('775000');
                            }
                            $value = $values[$key];
                            if ($report->numeric($key) && is_numeric($value)) {
                                $style->setFormat(match ($key) {
                                    'record_id' => '0', 'purchase_price', 'discounted_price', 'carton_cost' => '0.00######',
                                    'inventory_quantity' => '0.###',
                                    default => NumberDisplay::mode($key) === 'quantity' ? '0.###' : '0.00',
                                });
                                $cells[] = Cell::fromValue((float) $value, $style);
                            } else {
                                $cells[] = new StringCell($value === null ? $this->emptyValue($key) : (string) $value, $style);
                            }
                        }
                        $writer->addRow(new Row($cells));
                    }
                    foreach ($this->footerRows($summary, $headers) as $cells) {
                        $valueIndex = array_search('inventory_value', array_keys($headers), true);
                        if ($cells[$valueIndex] !== '') {
                            $cells[$valueIndex] = (float) $cells[$valueIndex];
                        }
                        $writer->addRow(Row::fromValues($cells, (new Style)->setFontBold()->setFormat('0.00')->setBackgroundColor('F7F0E6')));
                    }
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

    private function emptyValue(string $key): string
    {
        return match ($key) {
            'inventory_quantity' => 'Not set', 'inventory_value' => 'Excluded', default => '',
        };
    }

    private function footerRows(array $summary, array $headers): array
    {
        $rows = [];
        $keys = array_flip(array_keys($headers));
        foreach ($summary['currencies'] as $currency => $group) {
            $row = array_fill(0, count($headers), '');
            $row[0] = 'Grand total ('.$currency.')';
            $row[$keys['premium_marketing_name']] = $group['products'].' products';
            $row[$keys['currency']] = $currency;
            $row[$keys['inventory_value']] = $group['total'];
            $rows[] = $row;
        }
        foreach (['Matching products' => 'records', 'Missing supplier price' => 'missing_prices',
            'Inventory not set' => 'unset_quantities', 'Needs setup (excluded)' => 'needs_setup'] as $label => $field) {
            $row = array_fill(0, count($headers), '');
            $row[0] = $label;
            $row[$keys['premium_marketing_name']] = (string) $summary[$field];
            $rows[] = $row;
        }

        return $rows;
    }

    private function csvText(string $value): string
    {
        return preg_match('/^[=+@\-\t\r\n]/', $value) ? "'".$value : $value;
    }
}
