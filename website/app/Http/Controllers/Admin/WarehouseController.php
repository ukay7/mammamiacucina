<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class WarehouseController extends Controller {
 public function index(){return view('admin.warehouses.index',['warehouses'=>Warehouse::withCount('inventories')->orderByDesc('is_default')->orderBy('name')->get()]);}
 public function create(){return view('admin.warehouses.form',['warehouse'=>new Warehouse(['is_active'=>true,'storage_type'=>'ambient'])]);}
 public function edit(Warehouse $warehouse){return view('admin.warehouses.form',compact('warehouse'));}
 public function store(Request $r){Warehouse::create($this->data($r));return redirect()->route('admin.warehouses.index')->with('status','Warehouse created.');}
 public function update(Request $r,Warehouse $warehouse){$data=$this->data($r,$warehouse);if($warehouse->is_default&&!$data['is_active'])throw \Illuminate\Validation\ValidationException::withMessages(['is_active'=>'The default warehouse must remain active.']);$warehouse->update($data);return redirect()->route('admin.warehouses.index')->with('status','Warehouse updated.');}
 private function data(Request $r,?Warehouse $warehouse=null):array{return $r->validate(['name'=>['required','string','max:120',Rule::unique('warehouses')->ignore($warehouse?->id)],'description'=>'nullable|string|max:2000','storage_type'=>'required|in:ambient,chilled,freezer','is_active'=>'required|boolean']);}
}
