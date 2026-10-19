<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\AdminDashboard;
use Illuminate\Http\Request;
class DashboardController extends Controller {
 public function balances(Request $r,AdminDashboard $dashboard){
  $user=$r->user();
  abort_unless($user->hasAdminPermission('orders.view') && !$user->isWarehouseOnly(),403);
  $kind=$r->query('kind')==='refunds'?'refunds':'dues';
  $query=\Illuminate\Support\Facades\DB::query()->fromSub($dashboard->balances(),'b')->where('balance_cents',$kind==='refunds'?'<':'>',0);
  $total=abs((int)(clone $query)->sum('balance_cents'));
  $rows=$query->orderBy('balance_cents',$kind==='refunds'?'asc':'desc')->orderBy('id')->paginate(25)->withQueryString();
  return view('admin.dashboard-balances',compact('rows','kind','total'));
 }
 public function __invoke(Request $r,AdminDashboard $dashboard){
  $days=(int)$r->query('days',30);if(!in_array($days,[7,30,90],true))$days=30;
  return view('admin.dashboard',$dashboard->build($r->user(),$days));
 }
}
