@extends('layouts.mmc-page', ['pageTitle' => 'Contact Us'])
@section('page-content')
<div class="mmc-two-col ps-contact"><section><p class="mmc-eyebrow">LET’S TALK</p><h2>We’d Love to Hear from You</h2><p>Planning a celebration or looking for your next Italian favourite? Tell us what you have in mind.</p><img class="mmc-feature-photo" src="{{ asset('assets/images/mmc/category-cannoli-hd.png') }}" alt="Italian cannoli and cornetti"><h3>Opening Hours</h3>@include('partials.site-hours')
@include('partials.site-contact')</section>
<form class="mmc-panel mmc-form ps-contact__form" method="post" action="{{ route('contact.store') }}">@csrf<h2>Send Us a Message</h2>
@if(session('contact_success'))<p role="status" class="alert alert-success">{{ session('contact_success') }}</p>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<label for="contact-name">Your name</label><input class="form-control" id="contact-name" name="name" value="{{ old('name') }}" maxlength="120" required autocomplete="name"><label for="contact-email">Email address</label><input class="form-control" id="contact-email" name="email" value="{{ old('email') }}" type="email" maxlength="255" required autocomplete="email"><label for="contact-phone">Phone (optional)</label><input class="form-control" id="contact-phone" name="phone" value="{{ old('phone') }}" type="tel" maxlength="60" autocomplete="tel"><label for="contact-message">Your message</label><textarea class="form-control" id="contact-message" name="message" rows="5" maxlength="5000" required>{{ old('message') }}</textarea><button class="mmc-button" type="submit">Send Message</button></form></div>
@endsection
