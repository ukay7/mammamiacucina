<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->text('heading');
            $t->string('subheading');
            $t->string('button_text', 80);
            $t->string('alt_text');
            $t->string('asset_path')->nullable();
            $t->string('image_path')->nullable();
            $t->unsignedInteger('priority')->default(1);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        $slides = [
            ['italian-pastries-hero.png', "Authentic Italian\nCakes & Pastries", 'Homemade with love · Product of Italy', 'Explore the Menu', 'Sicilian cassata, cannoli and chocolate-drizzled pastries'],
            ['cakes-hero.png', "A Taste of Italy,\nA Slice of Joy", 'Italian favourites · Made to celebrate', 'Discover Our Cakes', 'Chocolate caprese, ricotta cake and a fresh berry tart'],
            ['pastries-hero.png', "Golden Layers,\nSweet Moments", 'Flaky pastries · A little Italian indulgence', 'Explore Our Pastries', 'Golden sfogliatelle and cornetti with orange and espresso'],
            ['cannoli-hero.png', "A Sicilian Classic,\nFilled with Love", 'Crisp shells · Deliciously creamy centres', 'Discover Our Cannoli', 'Pistachio and chocolate chip ricotta-filled Sicilian cannoli'],
        ];
        foreach ($slides as $i => $s) {
            DB::table('banners')->insert(['asset_path' => 'assets/images/mmc/'.$s[0], 'heading' => $s[1], 'subheading' => $s[2], 'button_text' => $s[3], 'alt_text' => $s[4], 'priority' => $i + 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $role = DB::table('roles')->where('name', 'Administrator')->first();
        if ($role) {
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique([...json_decode($role->permissions, true), 'banners.view', 'banners.manage'])))]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
