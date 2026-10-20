<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id(); $t->string('name')->unique(); $t->text('description')->nullable();
            $t->enum('storage_type',['ambient','chilled','freezer'])->default('ambient');
            $t->boolean('is_default')->default(false); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('warehouse_inventories', function (Blueprint $t) {
            $t->id(); $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity_on_hand',15,3)->nullable(); $t->timestamps();
            $t->unique(['warehouse_id','product_id']);
        });
        Schema::table('inventory_movements', fn(Blueprint $t) => $t->foreignId('warehouse_id')->nullable()->after('product_id')->constrained()->nullOnDelete());
        Schema::table('order_items', fn(Blueprint $t) => $t->foreignId('inventory_warehouse_id')->nullable()->after('product_id')->constrained('warehouses')->nullOnDelete());

        Schema::create('stock_count_tasks', function (Blueprint $t) {
            $t->id(); $t->string('number')->unique(); $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->foreignId('assigned_to')->constrained('users')->restrictOnDelete(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->enum('scope',['full','specific']); $t->enum('status',['scheduled','in_progress','submitted','cancelled'])->default('scheduled');
            $t->dateTime('starts_at')->nullable(); $t->dateTime('due_at')->nullable(); $t->text('instructions')->nullable();
            $t->dateTime('last_saved_at')->nullable(); $t->dateTime('submitted_at')->nullable(); $t->timestamps();
        });
        Schema::create('stock_count_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('stock_count_task_id')->constrained()->cascadeOnDelete(); $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->decimal('actual_quantity',15,3)->nullable(); $t->decimal('system_quantity_snapshot',15,3)->nullable();
            $t->text('note')->nullable(); $t->dateTime('counted_at')->nullable(); $t->timestamps();
            $t->unique(['stock_count_task_id','product_id']);
        });

        $now=now(); $warehouseId=DB::table('warehouses')->insertGetId(['name'=>'Main Warehouse','description'=>'Default warehouse created from existing inventory.','storage_type'=>'ambient','is_default'=>true,'is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
        DB::table('inventory_movements')->whereNull('warehouse_id')->update(['warehouse_id'=>$warehouseId]);
        DB::table('order_items')->where('stock_deducted',true)->whereNull('inventory_warehouse_id')->update(['inventory_warehouse_id'=>$warehouseId]);
        DB::table('inventories')->whereNotNull('quantity_on_hand')->orderBy('id')->chunkById(250,function($rows)use($warehouseId,$now){foreach($rows as $row)DB::table('warehouse_inventories')->insert(['warehouse_id'=>$warehouseId,'product_id'=>$row->product_id,'quantity_on_hand'=>$row->quantity_on_hand,'created_at'=>$now,'updated_at'=>$now]);});

        $warehouseRole=DB::table('roles')->where('name','Warehouse User')->first();
        if(!$warehouseRole){DB::table('roles')->insert(['name'=>'Warehouse User','permissions'=>json_encode(['dashboard.view','warehouse.pack','stock-counts.perform']),'is_super'=>false,'created_at'=>$now,'updated_at'=>$now]);}
        else { $p=json_decode($warehouseRole->permissions,true)?:[]; $p=array_values(array_unique([...$p,'stock-counts.perform'])); DB::table('roles')->where('id',$warehouseRole->id)->update(['permissions'=>json_encode($p),'updated_at'=>$now]); }
        foreach(DB::table('roles')->where('name','Administrator')->get() as $role){$p=json_decode($role->permissions,true)?:[];$p=array_values(array_unique([...$p,'access.admin.warehouses.index','warehouses.view','warehouses.manage','access.admin.stock-counts.index','stock-counts.view','stock-counts.manage']));DB::table('roles')->where('id',$role->id)->update(['permissions'=>json_encode($p),'updated_at'=>$now]);}
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items'); Schema::dropIfExists('stock_count_tasks');
        Schema::table('order_items',fn(Blueprint $t)=>$t->dropConstrainedForeignId('inventory_warehouse_id'));
        Schema::table('inventory_movements',fn(Blueprint $t)=>$t->dropConstrainedForeignId('warehouse_id'));
        Schema::dropIfExists('warehouse_inventories'); Schema::dropIfExists('warehouses');
    }
};
