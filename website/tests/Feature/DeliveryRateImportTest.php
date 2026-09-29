<?php
namespace Tests\Feature;
use App\Services\DeliveryQuote;
use Database\Seeders\DeliveryRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class DeliveryRateImportTest extends TestCase {
 use RefreshDatabase;
 public function test_source_cells_and_repeatable_seed():void {
  $this->seed(DeliveryRateSeeder::class);$this->seed(DeliveryRateSeeder::class);
  $this->assertDatabaseCount('delivery_postal_zones',169);$this->assertDatabaseCount('delivery_services',5);$this->assertDatabaseCount('delivery_rates',4500);
  $this->assertSame(117,DB::table('delivery_rates')->whereNull('amount_cents')->count());
  $source=new \DOMDocument();@$source->loadHTMLFile(base_path('tests/Fixtures/LittleGuys_all-services_RateCard.html'));$xp=new \DOMXPath($source);
  $codes=['bullet','direct','rush','same_day','overnight'];
  foreach($xp->query('//table[@class="matrix-tbl"]') as $i=>$table){
   $saved=DB::table('delivery_rates')->where('service_code',$codes[$i])->get()->keyBy(fn($r)=>$r->from_zone.':'.$r->to_zone);
   foreach($xp->query('.//tbody/tr',$table) as $from=>$row)foreach($xp->query('./td',$row) as $to=>$cell){
    $v=trim($cell->textContent);$this->assertSame($v==='N/A'?null:(int)str_replace('.','',$v),$saved[($from+1).':'.($to+1)]->amount_cents);
   }
  }
  foreach($xp->query('(//table[@class="postal-tbl"])[1]//td[@class="pc"]') as $cell)$this->assertEquals((int)$cell->nextSibling->textContent,DB::table('delivery_postal_zones')->where('prefix',$cell->textContent)->value('zone'));
 }
 public function test_services_and_directional_unavailable_routes():void {
  $this->seed(DeliveryRateSeeder::class);$service=app(DeliveryQuote::class);
  foreach(['bullet'=>5876,'direct'=>3734,'rush'=>2983,'same_day'=>1865,'overnight'=>1490] as $code=>$amount)$this->assertSame($amount,$service->quote('m2n 1a1','m1p 1a1',$code)['delivery_cents']);
  $this->assertSame(1490,$service->quote('M2N','L6R','overnight')['delivery_cents']);
  $this->expectException(ValidationException::class);$service->quote('L6R','M2N','overnight');
 }
}
