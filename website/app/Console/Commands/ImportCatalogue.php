<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\CatalogueImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportCatalogue extends Command
{
    protected $signature = 'catalogue:import {file : Path to an XLSX catalogue} {--commit : Save valid products after preview}';

    protected $description = 'Review an XLSX catalogue, optionally creating new products and updating matches';

    public function handle(CatalogueImporter $service): int
    {
        $path = realpath($this->argument('file'));
        if (! $path || ! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || filesize($path) > 10 * 1024 * 1024) {
            $this->error('Provide an existing .xlsx file smaller than 10 MB.');

            return self::FAILURE;
        }
        $stored = 'catalogue-imports/'.Str::uuid().'.xlsx';
        $stream = fopen($path, 'rb');
        try {
            $saved = Storage::disk('local')->put($stored, $stream);
        } finally {
            fclose($stream);
        }if (! $saved) {
            $this->error('Cannot save the private workbook copy.');

            return self::FAILURE;
        }
        try {
            $import = $service->preview(Storage::disk('local')->path($stored), basename($path), null, Category::where('slug', 'general')->firstOrFail()->id, $stored);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);
            throw $e;
        }
        $this->info("Import #{$import->id}: {$import->total_rows} product rows; {$import->failed_count} invalid rows.");
        foreach ($import->rows()->where('status', 'invalid')->get() as $row) {
            $this->error("Row {$row->row_number}: ".implode(' ', $row->errors));
        }
        if ($import->failed_count) {
            return self::FAILURE;
        }
        if ($this->option('commit')) {
            $import = $service->commit($import);
            $this->info("Saved: {$import->created_count} created, {$import->updated_count} updated, {$import->unchanged_count} unchanged.");
        } else {
            $this->line('Review and commit this import in Admin > Product Uploader.');
        }

        return self::SUCCESS;
    }
}
