@extends('layouts.mmc-page', ['pageTitle' => 'About Us'])
@section('page-content')
<section class="mmc-two-col ps-section--about">
<div><p class="mmc-eyebrow">OUR TRADITION</p><h2>A Little Italy,<br>A Lot of Love</h2><div class="mmc-gold-rule">♥</div><p>Some of the best moments happen around the table. At Mamma Mia Cucina, we celebrate those moments with Italian favourites, from delicate pastries and creamy cannoli to cakes made for sharing.</p><p>Whether you’re gathering for a celebration or enjoying a quiet coffee, our collection brings a little sweetness to the occasion.</p><a class="mmc-button" href="{{ route('theme.product-grid') }}">Explore Our Collection</a></div>
<img class="mmc-feature-photo" src="{{ asset('assets/images/mmc/category-cakes-hd.png') }}" alt="Traditional Italian cassata cake">
</section>
<section class="mmc-values"><article><span>01</span><h3>Italian Favourites</h3><p>Discover the cakes, pastries and cannoli that make every gathering feel special.</p></article><article><span>02</span><h3>Made for Sharing</h3><p>From the first slice to the last bite, find something everyone can enjoy.</p></article><article><span>03</span><h3>Moments to Savour</h3><p>A celebration, a coffee break, or a little treat just because.</p></article></section>
<section class="mmc-callout"><p class="mmc-eyebrow">SOMETHING SWEET AWAITS</p><h2>Bring everyone together.</h2><a class="mmc-button" href="{{ route('theme.contact') }}">Talk to Us</a></section>
@endsection
