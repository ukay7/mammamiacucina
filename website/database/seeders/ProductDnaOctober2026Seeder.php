<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class ProductDnaOctober2026Seeder extends Seeder {
 public function run():void {
  if($this->command->call('products:update-dna-october',['--apply'=>true])!==0)throw new \RuntimeException('Product update failed. Review the error before retrying.');
 }
}
