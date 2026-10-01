<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\EmailHistory;
use Illuminate\Http\Request;
class EmailHistoryController extends Controller {
 public function index(Request $request){
  $search=trim((string)$request->query('search',''));
  $emails=EmailHistory::when($search!=='',fn($q)=>$q->where('recipient','like','%'.$search.'%'))->latest('id')->paginate(30)->withQueryString();
  return response()->view('admin.settings.email-history',compact('emails','search'))->header('Cache-Control','no-store, private');
 }
}
