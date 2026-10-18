@php
if(!isset($doc)) {
 $doc=['brand'=>app(\App\Services\DocumentTheme::class)->brand(),'title'=>'Order Confirmation','number'=>$values['order_number'],'date'=>$date,'order_number'=>$values['order_number'],'billing'=>[$values['name'],$values['fulfillment']],'summary'=>['Order total'=>$values['total'],'Net received'=>$values['received'],'Balance'=>$values['balance']],'items'=>$items,'totals'=>['Product subtotal'=>$subtotal,'Delivery'=>$delivery,'Tax'=>$tax,'Total CAD'=>$values['total'],'Net received'=>$values['received'],'Balance'=>$values['balance']],'instructions'=>$values['instructions'],'payment'=>$values['payment_method'].' / '.$values['payment_status'],'delivery'=>$values['fulfillment'],'notes'=>null,'notice'=>$values['environment'].' An unpaid or pending order is not proof of payment.'];
}
$pdf=true;
@endphp
@include('documents.financial')
