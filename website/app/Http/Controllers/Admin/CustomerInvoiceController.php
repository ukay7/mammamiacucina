<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\CustomerInvoice;
use Illuminate\Http\Request;
class CustomerInvoiceController extends Controller {
 public function show(CustomerInvoice $invoice){$invoice->load('order.payment','order.settlements');return view('admin.customers.invoice',compact('invoice'));}
 public function email(Request $request,CustomerInvoice $invoice){
  $o=$invoice->order;abort_unless(filter_var($o->email,FILTER_VALIDATE_EMAIL),422,'Order has no valid email address.');
  app(\App\Services\CustomerEmails::class)->queue('invoice-email:'.$invoice->id,'customer_invoice',$o->email,['name'=>trim($o->first_name.' '.$o->last_name),'invoice_number'=>$invoice->label,'order_number'=>$o->number,'invoice_url'=>\Illuminate\Support\Facades\URL::temporarySignedRoute('invoice.document',now()->addDays(30),['invoice'=>$invoice->id])]);
  return back()->with('status','Invoice email queued. Repeated clicks will not send duplicates.');
 }
}
