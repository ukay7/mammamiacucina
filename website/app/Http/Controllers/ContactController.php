<?php
namespace App\Http\Controllers;
use App\Models\ContactEnquiry;
use Illuminate\Http\Request;
class ContactController extends Controller {
 public function store(Request $r){$data=$r->validate(['name'=>'required|string|max:120','email'=>'required|email|max:255','phone'=>'nullable|string|max:60','message'=>'required|string|max:5000']);ContactEnquiry::create($data);return redirect()->route('theme.contact')->with('contact_success','Thank you! Your message has been received. Our team will get back to you.');}
}
