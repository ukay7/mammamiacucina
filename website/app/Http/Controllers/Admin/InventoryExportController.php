<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\DocumentTheme;
use App\Services\InventoryColumns;
use App\Services\InventoryProducts;
use App\Support\NumberDisplay;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class InventoryExportController extends Controller
{
    public function __invoke(Request $request, InventoryProducts $inventory, InventoryColumns $columns)
    {
        $request->validate(['format' => 'required|in:print,pdf,xlsx,csv']);
        $filters = $inventory->filters($request);
        $selected = $columns->selected($request);
        if (! $selected) {
            throw ValidationException::withMessages(['columns' => 'Choose at least one product column. Current stock is included automatically.']);
        }
        $headers = $columns->headers($selected);
        // Use the same active/default warehouse selection as Inventory Management.
        $warehouses = Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $warehouse = $warehouses->firstWhere('id', (int) $request->input('warehouse'))
            ?? $warehouses->firstWhere('is_default', true) ?? $warehouses->first();
        $query = $columns->withColumns($inventory->query($filters, $warehouse?->id));
        $filename = 'inventory-stock-'.now()->format('Y-m-d-His');

        if (in_array($request->input('format'), ['print', 'pdf'], true)) {
            [$summary, $rows] = DB::transaction(fn () => [
                $inventory->stockSummary($query),
                iterator_to_array($columns->rows($query, in_array('image', $selected, true)), false),
            ]);
            $doc = ['brand' => app(DocumentTheme::class)->brand(), 'title' => 'Inventory Stock',
                'number' => $warehouse?->name ?? 'All warehouses combined',
                'date' => now()->format('d M Y H:i').' '.config('app.timezone')];
            $groups = $columns->printGroups($selected);
            $pdf = $request->input('format') === 'pdf';
            $view = view('documents.inventory-stock', compact('doc', 'groups', 'rows', 'summary', 'filters', 'columns', 'pdf'));
            if (! $pdf) {
                return $view;
            }
            $renderer = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false,
                'defaultFont' => 'DejaVu Sans', 'defaultMediaType' => 'print']));
            $renderer->loadHtml($view->render());
            $renderer->setPaper('A4', 'landscape');
            $renderer->render();
            $renderer->getCanvas()->page_text(725, 579, 'Page {PAGE_NUM} of {PAGE_COUNT}', $renderer->getFontMetrics()->getFont('Helvetica'), 8);

            return response($renderer->output(), 200, ['Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"']);
        }

        if ($request->input('format') === 'csv') {
            return response()->streamDownload(function () use ($query, $headers, $inventory, $columns) {
                DB::transaction(function () use ($query, $headers, $inventory, $columns) {
                    $summary = $inventory->stockSummary($query);
                    $out = fopen('php://output', 'w');
                    fwrite($out, "\xEF\xBB\xBF");
                    fputcsv($out, array_values($headers), ',', '"', '');
                    foreach ($columns->rows($query) as $values) {
                        $cells = [];
                        foreach ($headers as $key => $label) {
                            $value = $values[$key];
                            $cells[] = $key === 'stock_quantity' && $value !== null ? NumberDisplay::quantity($value)
                                : ($columns->numeric($key) && is_numeric($value) ? $value
                                    : $this->csvText($value === null ? ($key === 'stock_quantity' ? 'Not set' : '') : (string) $value));
                        }
                        fputcsv($out, $cells, ',', '"', '');
                    }
                    foreach ($this->footerRows($summary, $headers) as $row) {
                        fputcsv($out, $row, ',', '"', '');
                    }
                    fclose($out);
                });
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($query, $headers, $inventory, $columns) {
            DB::transaction(function () use ($query, $headers, $inventory, $columns) {
                $summary = $inventory->stockSummary($query);
                $path = tempnam(sys_get_temp_dir(), 'mmc-stock-');
                $writer = new Writer;
                try {
                    $writer->openToFile($path);
                    $sheet = $writer->getCurrentSheet();
                    foreach (array_keys($headers) as $index => $key) {
                        $sheet->setColumnWidth($key === 'premium_marketing_name' ? 36 : 22, $index + 1);
                    }
                    $writer->addRow(Row::fromValues(array_values($headers), (new Style)->setFontBold()->setBackgroundColor('F7F0E6')));
                    foreach ($columns->rows($query) as $values) {
                        $cells = [];
                        foreach ($headers as $key => $label) {
                            $value = $values[$key];
                            $style = (new Style)->setShouldWrapText();
                            if ($columns->numeric($key) && is_numeric($value)) {
                                $style->setFormat($key === 'record_id' ? '0' : (NumberDisplay::mode($key) === 'quantity' ? '0.###' : '0.00'));
                                $cells[] = Cell::fromValue((float) $value, $style);
                            } else {
                                $cells[] = new StringCell($value === null ? ($key === 'stock_quantity' ? 'Not set' : '') : (string) $value, $style);
                            }
                        }
                        $writer->addRow(new Row($cells));
                    }
                    foreach ($this->footerRows($summary, $headers) as $row) {
                        $row[count($row) - 1] = (float) $row[count($row) - 1];
                        $writer->addRow(Row::fromValues($row, (new Style)->setFontBold()->setFormat('0.###')->setBackgroundColor('F7F0E6')));
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

    private function footerRows(array $summary, array $headers): array
    {
        $rows = [];
        foreach (['Total stock quantity' => 'quantity', 'Quantity × 5' => 'quantity_times_five'] as $label => $key) {
            $row = array_fill(0, count($headers), '');
            $row[0] = $label;
            $row[count($row) - 1] = $summary[$key];
            $rows[] = $row;
        }

        return $rows;
    }

    private function csvText(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@\-]|^[\t\r\n]/', $value) ? "'".$value : $value;
    }
}
