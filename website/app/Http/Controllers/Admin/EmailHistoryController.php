<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\EmailHistory;
use Illuminate\Http\Request;
class EmailHistoryController extends Controller {
 public function index(Request $request){
  $search=trim((string)$request->query('search',''));
  $emails=EmailHistory::select(['id','recipient','subject','type','status','mailer','attempts','available_at','delivery_note','sent_at','message_id','created_at','archived_at'])->when($search!=='',fn($q)=>$q->where('recipient','like','%'.$search.'%'))->latest('id')->paginate(30)->withQueryString();
  return response()->view('admin.settings.email-history',compact('emails','search'))->header('Cache-Control','no-store, private');
 }
 public function show(EmailHistory $email){
  $archive=$email->archive;
  return response()->view('admin.settings.email-history-show',compact('email','archive'))->header('Cache-Control','no-store, private');
 }
 public function attachment(EmailHistory $email){
  $pdf=base64_decode($email->archive['attachment'] ?? '',true);
  abort_unless($pdf && str_starts_with($pdf,'%PDF-'),404);
  return response($pdf)->header('Content-Type','application/pdf')->header('Content-Disposition','attachment; filename="order-'.$email->id.'.pdf"')->header('Cache-Control','no-store, private')->header('X-Content-Type-Options','nosniff');
 }
}
