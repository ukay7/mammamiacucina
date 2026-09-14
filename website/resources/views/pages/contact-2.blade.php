@extends('layouts.theme')
@section('title', 'Bakery | Contact 2')
@section('body-class', 'page-init')
@section('content')

    <div class="ps-searchbox">
      <div class="ps-searchbox__remove"><i class="fa fa-remove"></i></div>
      <div class="container">
        <header>
          <p>Enter your keywords:</p>
          <form method="get" action="{{ route('theme.product-grid') }}">
            <input class="form-control" type="text" placeholder="">
            <button><i class="ps-icon--search"></i></button>
          </form>
        </header>
        <div class="ps-searchbox__result">
          <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                  <div class="ps-product--list ps-product--list-light mt-60">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cr-1.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">Amazin’ Glazin’</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                  <div class="ps-product--list ps-product--list-light mt-60">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cr-2.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">The Crusty Croissant</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                  <div class="ps-product--list ps-product--list-light mt-60">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cr-3.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">Amazin’ Glazin’</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                  <div class="ps-product--list ps-product--list-light mt-60">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cr-4.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">The Crusty Croissant</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                </div>
          </div>
        </div>
        <footer class="text-center"><a class="ps-searchbox__morelink" href="{{ route('theme.product-grid') }}">VIEW ALL RESULT</a></footer>
      </div>
    </div>
    <div class="header--sidebar"></div>
    <header class="header header--2" data-responsive="1199">
      <div class="header__top">
        <div class="container">
          <div class="row">
                <div class="col-lg-8 col-md-7 col-sm-12 col-xs-12 ">
                  <p>460 West 34th Street, 15th floor, New York - Hotline: 804-377-3580 - 804-399-3580</p>
                </div>
                <div class="col-lg-4 col-md-5 col-sm-12 col-xs-12 "><a class="ps-search-btn" href="#"><i class="ps-icon--search"></i></a>
                  <div class="btn-group ps-dropdown"><a class="dropdown-toggle" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">USD<i class="fa fa-angle-down"></i></a>
                    <ul class="dropdown-menu">
                      <li><a href="#">USD</a></li>
                      <li><a href="#">SGD</a></li>
                      <li><a href="#">ERO</a></li>
                      <li><a href="#">JPN</a></li>
                    </ul>
                  </div>
                  <div class="btn-group ps-dropdown"><a class="dropdown-toggle" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Language<i class="fa fa-angle-down"></i></a>
                    <ul class="dropdown-menu">
                      <li><a href="#">English</a></li>
                      <li><a href="#">Japanese</a></li>
                      <li><a href="#">Chinese</a></li>
                    </ul>
                  </div>
                </div>
          </div>
        </div>
      </div>
      <nav class="navigation">
        <div class="container">
          <div class="menu-toggle"><span></span></div>
          <div class="navigation__left">
                <ul class="menu menu--left">
                  <li class="menu-item-has-children"><a href="{{ route('theme.index') }}">Home</a>
                        <ul class="sub-menu">
                          <li><a href="#">Hot Demo</a></li>
                          <li class="end-block"><a href="#">Trending</a></li>
                          <li><a href="{{ route('theme.index') }}">HOMEPAGE 1</a></li>
                          <li><a href="{{ route('theme.homepage-2') }}">HOMEPAGE 2</a></li>
                          <li><a href="{{ route('theme.homepage-3') }}">HOMEPAGE 3</a></li>
                          <li class="try-theme"><a href="#">Try Theme Now</a></li>
                        </ul>
                  </li>
                  <li><a href="{{ route('theme.about') }}">About</a></li>
                  <li class="menu-item-has-children"><a href="{{ route('theme.product-grid') }}">Products</a>
                        <ul class="sub-menu">
                          <li><a href="{{ route('theme.product-listing') }}">Product Listing</a></li>
                          <li><a href="{{ route('theme.product-grid') }}">Product Grid</a></li>
                          <li><a href="{{ route('theme.product-detail') }}">Product Detail</a></li>
                        </ul>
                  </li>
                  <li class="menu-item-has-children"><a href="#">Pages</a>
                        <ul class="sub-menu">
                          <li><a href="{{ route('theme.menu-1') }}">Menu 1</a></li>
                          <li><a href="{{ route('theme.menu-2') }}">Menu 2</a></li>
                          <li><a href="{{ route('theme.order-form') }}">Order Form</a></li>
                          <li><a href="{{ route('theme.checkout') }}">Checkout</a></li>
                          <li><a href="{{ route('theme.cart') }}">Cart</a></li>
                          <li><a href="{{ route('theme.404') }}">404 Page</a></li>
                        </ul>
                  </li>
                </ul>
          </div><a class="ps-logo" href="{{ route('theme.index') }}"><img src="{{ asset('assets/images/logo-2.png') }}" alt=""></a>
          <div class="navigation__right">
                <ul class="menu menu--right">
                  <li><a href="#">Gallery</a></li>
                  <li class="menu-item-has-children"><a href="{{ route('theme.blog-listing') }}">Blogs</a>
                        <ul class="sub-menu">
                          <li class="menu-item-has-children"><a href="{{ route('theme.blog-detail') }}">Blog Detail</a>
                                <ul class="sub-menu">
                                  <li><a href="#">Sample Menu #1</a></li>
                                  <li><a href="#">Sample Menu #2</a></li>
                                </ul>
                          </li>
                        </ul>
                  </li>
                  <li class="current menu-item-has-children"><a href="#">Contact</a>
                        <ul class="sub-menu">
                          <li class="current "><a href="{{ route('theme.contact') }}">Contact 1</a></li>
                          <li class="current "><a href="{{ route('theme.contact-2') }}">Contact 2</a></li>
                        </ul>
                  </li>
                </ul>
                <div class="ps-cart"><a class="ps-cart__toggle" href="#"><span><i>20</i></span><i class="ps-icon--shopping-cart"></i></a>
                  <div class="ps-cart__listing">
                    <div class="ps-cart__content">
                      <div class="ps-cart-item"><a class="ps-cart-item__close" href="#"></a>
                        <div class="ps-cart-item__thumbnail"><a href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-1.jpg') }}" alt=""></div>
                        <div class="ps-cart-item__content"><a class="ps-cart-item__title" href="{{ route('theme.product-detail') }}">Amazin’ Glazin’</a>
                          <p><span>Quantity:<i>12</i></span><span>Total:<i>£176</i></span></p>
                        </div>
                      </div>
                      <div class="ps-cart-item"><a class="ps-cart-item__close" href="#"></a>
                        <div class="ps-cart-item__thumbnail"><a href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-2.jpg') }}" alt=""></div>
                        <div class="ps-cart-item__content"><a class="ps-cart-item__title" href="{{ route('theme.product-detail') }}">The Crusty Croissant</a>
                          <p><span>Quantity:<i>12</i></span><span>Total:<i>£176</i></span></p>
                        </div>
                      </div>
                      <div class="ps-cart-item"><a class="ps-cart-item__close" href="#"></a>
                        <div class="ps-cart-item__thumbnail"><a href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-3.jpg') }}" alt=""></div>
                        <div class="ps-cart-item__content"><a class="ps-cart-item__title" href="{{ route('theme.product-detail') }}">The Rolling Pin</a>
                          <p><span>Quantity:<i>12</i></span><span>Total:<i>£176</i></span></p>
                        </div>
                      </div>
                    </div>
                    <div class="ps-cart__total">
                      <p>Number of items:<span>36</span></p>
                      <p>Item Total:<span>£528.00</span></p>
                    </div>
                    <div class="ps-cart__footer"><a class="ps-btn ps-btn--view-bag" href="{{ route('theme.cart') }}">View bag</a></div>
                  </div>
                </div>
          </div>
        </div>
      </nav>
    </header>
    <div id="back2top"><i class="fa fa-angle-up"></i></div>
    <div class="loader"></div>
    <div class="page-wrap">
      <!--section-->
      <div class="ps-section--hero"><img src="{{ asset('assets/images/hero/01.jpg') }}" alt="">
        <div class="ps-section__content text-center">
          <h3 class="ps-section__title">OUR BAKERY</h3>
          <div class="ps-breadcrumb">
            <ol class="breadcrumb">
              <li><a href="{{ route('theme.index') }}">Home</a></li>
              <li class="active">About Us</li>
            </ol>
          </div>
        </div>
      </div>
      <div class="ps-section pt-80 pb-80">
        <div class="container">
          <div class="ps-contact ps-contact--2">
            <div class="mb-60" id="contact-map" data-address="New York, NY" data-title="BAKERY LOCATION!" data-zoom="17"></div>
            <div class="row">
                  <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                    <div class="ps-contact__info">
                      <div class="ps-contact__block">
                        <h4>OFFICE AT AMERICA</h4>
                        <p><strong>BASEMENT COMPANY, NEW YORK</strong></p>
                        <p><i class="fa fa-envelope-o"></i>enquiry@bakery.com</p>
                        <p><i class="fa fa-phone"></i>+1 650-253-0000</p>
                            <ul class="ps-contact__social">
                              <li><a href="#"><i class="fa fa-facebook"></i></a></li>
                              <li><a href="#"><i class="fa fa-twitter"></i></a></li>
                              <li><a href="#"><i class="fa fa-rss"></i></a></li>
                            </ul>
                      </div>
                      <div class="ps-contact__block">
                        <h4>OFFICE AT PARIS</h4>
                        <p><strong>189/32 BASEMENT COMPANY, PARIS, FRANCE</strong></p>
                        <p><i class="fa fa-envelope-o"></i>enquiry@bakery.com</p>
                        <p><i class="fa fa-phone"></i>+1 650-253-0000</p>
                            <ul class="ps-contact__social">
                              <li><a href="#"><i class="fa fa-facebook"></i></a></li>
                              <li><a href="#"><i class="fa fa-twitter"></i></a></li>
                              <li><a href="#"><i class="fa fa-rss"></i></a></li>
                            </ul>
                      </div>
                      <div class="ps-contact__block">
                        <h4>OFFICE AT VIETNAM</h4>
                        <p><strong>189/32 BASEMENT COMPANY, PARIS, FRANCE</strong></p>
                        <p><i class="fa fa-envelope-o"></i>enquiry@bakery.com</p>
                        <p><i class="fa fa-phone"></i>+1 650-253-0000</p>
                            <ul class="ps-contact__social">
                              <li><a href="#"><i class="fa fa-facebook"></i></a></li>
                              <li><a href="#"><i class="fa fa-twitter"></i></a></li>
                              <li><a href="#"><i class="fa fa-rss"></i></a></li>
                            </ul>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                    <div class="ps-contact__form">
                      <div class="form-group">
                        <input class="form-control" type="text" placeholder="First Name">
                      </div>
                      <div class="form-group">
                        <input class="form-control" type="email" placeholder="E-mail">
                      </div>
                      <div class="form-group">
                        <input class="form-control" type="text" placeholder="Telephone">
                      </div>
                      <div class="form-group">
                        <select class="ps-select" data-placeholder="Popupar product">
                          <option value="01">Popular products</option>
                          <option value="01">Item 01</option>
                          <option value="02">Item 02</option>
                          <option value="03">Item 03</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <textarea class="form-control" rows="5" placeholder="Text your message here..."></textarea>
                      </div>
                      <div class="form-group text-center mt-30">
                        <button class="ps-btn ps-btn--sm ps-contact__submit">Submit</button>
                      </div>
                    </div>
                  </div>
            </div>
          </div>
        </div>
      </div>
      <section class="ps-section ps-section--subscribe pt-80 pb-80">
        <div class="container">
          <div class="ps-subscribe">
            <div class="row">
                  <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12 ">
                    <h4>ABOUT US</h4>
                    <p>Te pri oblique ullamcorper, magna persequeris has eu. Mei prompta dolores examad debet suavitate. Pri te vocibus electram. Eu eleifend rationibus vis, at.</p>
                    <p class="text-uppercase ps-subscribe__highlight">240 CENTRAL PARK, LONDON, OR 10019</p>
                  </div>
                  <div class="col-lg-2 col-md-2 col-sm-12 col-xs-12 "><a class="ps-subscribe__logo" href="{{ route('theme.index') }}"><img src="{{ asset('assets/images/logo-1.png') }}" alt=""></a>
                  </div>
                  <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12 ">
                    <h4>SUBSCRIBE EMAIL</h4>
                    <p>Give us your email, and we shall send regular updates for new stuff and events.</p>
                    <form class="ps-subscribe__form" method="get" action="#" data-static-preview="true">
                      <input class="form-control" type="text" placeholder="Type your email...">
                      <button class="ps-btn ps-btn--sm">Subscribe</button>
                    </form>
                  </div>
            </div>
          </div>
        </div>
      </section>
      <!--footer-->
      <footer class="ps-footer">
        <div class="container">
          <div class="row">
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-widget ps-widget--footer ps-widget--payment">
                    <div class="ps-widget__header">
                      <h3 class="ps-widget__title">PAYMENTS ACCEPTED</h3>
                    </div>
                    <div class="ps-widget__content">
                      <ul>
                        <li><a href="#"><img src="{{ asset('assets/images/payment/1.png') }}" alt=""></a></li>
                        <li><a href="#"><img src="{{ asset('assets/images/payment/2.png') }}" alt=""></a></li>
                        <li><a href="#"><img src="{{ asset('assets/images/payment/3.png') }}" alt=""></a></li>
                        <li><a href="#"><img src="{{ asset('assets/images/payment/4.png') }}" alt=""></a></li>
                        <li><a href="#"><img src="{{ asset('assets/images/payment/5.png') }}" alt=""></a></li>
                      </ul>
                    </div>
                  </div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-widget ps-widget--footer ps-widget--worktime">
                    <div class="ps-widget__header">
                      <h3 class="ps-widget__title">WORK TIME</h3>
                    </div>
                    <div class="ps-widget__content">
                      <p><strong>Monday - Friday</strong> 8:00 am - 8:30 pm</p>
                      <p><strong>Satuday - Sunday</strong>10:00 am - 16:30 pm</p>
                    </div>
                  </div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-widget ps-widget--footer ps-widget--order">
                    <div class="ps-widget__header">
                      <h3 class="ps-widget__title">ORDERS AND RETURNS</h3>
                    </div>
                    <div class="ps-widget__content">
                          <ul class="ps-list--line">
                            <li><a href="#">Order</a></li>
                            <li><a href="#">Shipping</a></li>
                            <li><a href="#">Policy Return Policy</a></li>
                            <li><a href="#">Payments</a></li>
                          </ul>
                    </div>
                  </div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-widget ps-widget--footer ps-widget--connect">
                    <div class="ps-widget__header">
                      <h3 class="ps-widget__title">CONNECT US</h3>
                    </div>
                    <div class="ps-widget__content">
                          <ul class="ps-widget__social">
                            <li><a href="#"><i class="fa fa-facebook"></i></a></li>
                            <li><a href="#"><i class="fa fa-google"></i></a></li>
                            <li><a href="#"><i class="fa fa-twitter"></i></a></li>
                            <li><a href="#"><i class="fa fa-instagram"></i></a></li>
                          </ul><a href="#"><img src="{{ asset('assets/images/app.jpg') }}" alt=""></a>
                      <p>@2017 Design by<a href="#"> Alena Studio</a>.</p>
                    </div>
                  </div>
                </div>
          </div>
        </div>
      </footer>
    </div>

@endsection
