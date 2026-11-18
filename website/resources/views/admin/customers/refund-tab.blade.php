@if(auth()->user()->isSuper())
<div class="tab-pane fade" id="customer-refunds" role="tabpanel" aria-labelledby="customer-tab-refunds">
<div class="d-flex justify-content-between align-items-center mb-3"><h3>Refunds</h3><a class="btn btn-primary" href="{{ route('admin.customers.refunds.create',$customer) }}">Add Refund</a></div>
<p>Refunds against delivered / completed orders. Return refunds create sales credits. Overpayment refunds settle an existing Refund Due without another sales credit. Pending cheques reserve funds until cleared or cancelled.</p>
@include('admin.customers.refund-table')
</div>
@endif
