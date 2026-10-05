<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\ProductDnaOctoberUpdate;
class UpdateProductDnaOctober extends Command {
 protected $signature='products:update-dna-october {--apply : Back up and update existing products once}';
 protected $description='Preview or apply the October 2026 update-only product DNA dataset';
 public function handle(ProductDnaOctoberUpdate $updater):int {
  try{$r=$updater->run((bool)$this->option('apply'));}catch(\Throwable $e){$this->error($e->getMessage());return self::FAILURE;}
  if($r['already_applied']??false){$this->info('Already applied. No products changed. Report: '.$r['report']);return self::SUCCESS;}
  $this->table(['Excel row','Internal code','Product','Result','Business CAD','Individual CAD'],array_map(fn($e)=>[$e['row'],$e['internal_code'],$e['name'],$e['status'],$e['business_after']??'-',$e['individual_after']??'-'],$r['rows']));
  foreach($r['rows'] as $e){if(isset($e['reason']))$this->warn($e['internal_code'].': '.$e['reason']);foreach($e['warnings']??[] as $w)if(str_starts_with($w,'Incomplete'))$this->warn($e['internal_code'].': '.$w);}
  $this->info("Matched: {$r['matched']}; updated: {$r['updated']}; skipped: {$r['skipped']}; inserted: 0.");
  $this->line('Unknown categories retain their website category and are saved as product family. Images, internal codes, status, inventory and orders are unchanged.');
  if(isset($r['backup']))$this->info('Backup and detailed report: '.$r['backup']);
  if(!$this->option('apply'))$this->info('Preview only. Run with --apply after reviewing.');return self::SUCCESS;
 }
}
