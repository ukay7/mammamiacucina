<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class CustomerAccess {
 public function handle(Request $request, Closure $next, string $verified='yes') {
  $user=$request->user();
  if(!$user)return redirect()->route('customer.login');
  abort_unless($user->is_active && $user->isCustomer(),403);
  if($verified==='yes'&&!$user->email_verified_at)return redirect()->route('customer.verify.notice');
  if($verified==='yes' && $user->businessApprovalPending()){
   if($request->expectsJson())return response()->json(['message'=>'You cannot place an order until your business account is approved. Please contact administration.'],403);
   return redirect()->route('customer.business.pending');
  }
  $response=$next($request);$response->headers->set('Cache-Control','no-store, private');return $response;
 }
}
