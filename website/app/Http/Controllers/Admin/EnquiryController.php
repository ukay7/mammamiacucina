<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\ContactEnquiry;
use Illuminate\Http\Request;
class EnquiryController extends Controller {
 public function index(Request $r){$d=$r->validate(['q'=>'nullable|string|max:200','status'=>'nullable|in:new,read,closed']);$q=$d['q']??'';$status=$d['status']??'';return view('admin.enquiries.index',['enquiries'=>ContactEnquiry::when($q,fn($b)=>$b->where(fn($b)=>$b->where('name','like','%'.$q.'%')->orWhere('email','like','%'.$q.'%')))->when($status,fn($b)=>$b->where('status',$status))->latest('id')->paginate(20)->withQueryString(),'q'=>$q,'status'=>$status]);}
 public function show(ContactEnquiry $enquiry){return response()->view('admin.enquiries.show',compact('enquiry'))->header('Cache-Control','no-store, private');}
 public function update(Request $r,ContactEnquiry $enquiry){$enquiry->update($r->validate(['status'=>'required|in:new,read,closed']));return back()->with('status','Enquiry status updated.');}
}
