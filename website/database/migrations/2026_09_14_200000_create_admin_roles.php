<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->json('permissions');
            $t->boolean('is_super')->default(false);
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $t->boolean('is_active')->default(true);
        });
        foreach ([
            ['Super Admin', array_keys(config('admin.permissions')), true],
            ['Administrator', array_keys(config('admin.permissions')), false],
            ['Manager', ['dashboard.view', 'users.view'], false],
            ['Staff', ['dashboard.view'], false],
        ] as [$name,$permissions,$super]) {
            DB::table('roles')->insert(['name' => $name, 'permissions' => json_encode($permissions), 'is_super' => $super, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('role_id');
            $t->dropColumn('is_active');
        });
        Schema::dropIfExists('roles');
    }
};
