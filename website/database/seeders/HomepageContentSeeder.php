<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class HomepageContentSeeder extends Seeder {
 public function run():void {$this->call([HomeCategoriesSeeder::class,MostLovedSeeder::class,SampleGallerySeeder::class]);}
}
