@php
 $scheduleSavedAt=$scheduleSavedAt??null;
 $scheduleDefaults=app(\App\Services\DeliverySchedule::class)->defaults($scheduleSavedAt);
 $scheduleSavedDate=$scheduleSavedAt?\App\Support\BusinessTime::local($scheduleSavedAt)->format('Y-m-d'):'';
 $scheduleMinDate=min(\App\Support\BusinessTime::now()->format('Y-m-d'),$scheduleSavedDate?:'9999-12-31');
 $schedulePrefix=$schedulePrefix??'checkout';
 $scheduleFulfillment=$scheduleFulfillment??'#checkout-fulfillment';
 $scheduleDelivery=old('fulfillment',$scheduleInitialFulfillment??'delivery')==='delivery';
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/delivery-schedule.css') }}?v={{ filemtime(public_path('assets/css/delivery-schedule.css')) }}">
<fieldset class="delivery-schedule" data-delivery-schedule data-fulfillment="{{ $scheduleFulfillment }}" data-timezone="{{ \App\Support\BusinessTime::zone() }}" data-clock="{{ now('UTC')->timestamp*1000 }}" data-saved-date="{{ $scheduleSavedDate }}" data-edited="{{ $scheduleSavedAt||session()->hasOldInput('delivery_date')||session()->hasOldInput('delivery_time')?'1':'0' }}" @if(!$scheduleDelivery) hidden @endif>
<legend>Delivery date &amp; time</legend>
<p>All times are in Toronto time. {{ $scheduleSavedAt?'Change the saved delivery date and time below.':'We have selected 2 hours from now; you can change it below.' }}</p>
<div class="delivery-schedule-fields">
<label for="{{ $schedulePrefix }}-delivery-date">Delivery date<input class="form-control" type="date" id="{{ $schedulePrefix }}-delivery-date" name="delivery_date" value="{{ old('delivery_date',$scheduleDefaults['delivery_date']) }}" min="{{ $scheduleMinDate }}" @required($scheduleDelivery) @disabled(!$scheduleDelivery) @error('delivery_date') aria-invalid="true" @enderror></label>
<label for="{{ $schedulePrefix }}-delivery-time">Delivery time<input class="form-control" type="time" id="{{ $schedulePrefix }}-delivery-time" name="delivery_time" value="{{ old('delivery_time',$scheduleDefaults['delivery_time']) }}" step="60" @required($scheduleDelivery) @disabled(!$scheduleDelivery) @error('delivery_time') aria-invalid="true" @enderror></label>
</div>
<input type="hidden" name="delivery_time_reference" value="{{ old('delivery_time_reference',$scheduleDefaults['delivery_time_reference']) }}" @disabled(!$scheduleDelivery)>
</fieldset>
