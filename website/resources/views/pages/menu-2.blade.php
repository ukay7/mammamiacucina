@extends('layouts.theme')
@section('title', 'Bakery | Menu 2')
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
                  <li class="current menu-item-has-children"><a href="#">Pages</a>
                        <ul class="sub-menu">
                          <li class="current "><a href="{{ route('theme.menu-1') }}">Menu 1</a></li>
                          <li class="current "><a href="{{ route('theme.menu-2') }}">Menu 2</a></li>
                          <li class="current "><a href="{{ route('theme.order-form') }}">Order Form</a></li>
                          <li class="current "><a href="{{ route('theme.checkout') }}">Checkout</a></li>
                          <li class="current "><a href="{{ route('theme.cart') }}">Cart</a></li>
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
                  <li class="menu-item-has-children"><a href="#">Contact</a>
                        <ul class="sub-menu">
                          <li><a href="{{ route('theme.contact') }}">Contact 1</a></li>
                          <li><a href="{{ route('theme.contact-2') }}">Contact 2</a></li>
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
      <div class="ps-section--hero"><img src="{{ asset('assets/images/hero/03.jpg') }}" alt="">
        <div class="ps-section__content text-center">
          <h3 class="ps-section__title">Menu</h3>
          <div class="ps-breadcrumb">
            <ol class="breadcrumb">
              <li><a href="{{ route('theme.index') }}">Home</a></li>
              <li class="active">Menu</li>
            </ol>
          </div>
        </div>
      </div>
      <section class="ps-section ps-section--offer pt-80 pb-80">
        <div class="container">
          <div class="ps-section__header text-center mb-60">
            <h4 class="ps-section__top">Making People Happy</h4>
            <h3 class="ps-section__title ps-section__title--full">OFFER THIS WEEK</h3>
          </div>
          <div class="ps-section__content">
            <div class="masonry-wrapper" data-col-md="4" data-col-sm="2" data-col-xs="1" data-gap="30" data-radio="100%">
              <div class="ps-masonry">
                <div class="grid-sizer"></div>
                <div class="grid-item high wide">
                  <div class="grid-item__content-wrapper">
                    <div class="ps-offer"><img src="{{ asset('assets/images/offer/banner-4.jpg') }}" alt=""><a class="ps-offer__overlay" href="#"></a></div>
                  </div>
                </div>
                <div class="grid-item">
                  <div class="grid-item__content-wrapper">
                    <div class="ps-offer"><img src="{{ asset('assets/images/offer/banner-2.jpg') }}" alt=""><a class="ps-offer__overlay" href="#"></a></div>
                  </div>
                </div>
                <div class="grid-item high">
                  <div class="grid-item__content-wrapper">
                    <div class="ps-offer"><img src="{{ asset('assets/images/offer/banner-3.jpg') }}" alt=""><a class="ps-offer__overlay" href="#"></a></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
                  <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                    <div class="ps-product--list mt-60">
                      <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cr-1.jpg') }}" alt=""></div>
                      <div class="ps-product__content">
                        <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">Amazin’ Glazin’</a></h4>
                        <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                        <p class="ps-product__price">
                          <del>£25.00</del>£15.00
                        </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                      </div>
                    </div>
                    <div class="ps-product--list mt-60">
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
                    <div class="ps-product--list mt-60">
                      <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cr-3.jpg') }}" alt=""></div>
                      <div class="ps-product__content">
                        <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">Amazin’ Glazin’</a></h4>
                        <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                        <p class="ps-product__price">
                          <del>£25.00</del>£15.00
                        </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                      </div>
                    </div>
                    <div class="ps-product--list mt-60">
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
        </div>
      </section>
      <div class="ps-section ps-section--about ps-section--pattern pt-80 pb-0">
        <div class="container">
          <div class="row">
                <div class="col-lg-7 col-md-7 col-sm-12 col-xs-12 ">
                  <div class="ps-section__header text-center">
                    <div class="ps-section__top">Our Chef</div>
                    <h3 class="ps-section__title">Mr. Regan Vanilla</h3>
                  </div>
                  <div class="ps-section__content">
                    <p>“Soufflé danish gummi bears tart. Pie wafer icing. Gummies jelly beans powder. Chocolate bar pudding macaroon candy canes chocolate apple pie chocolate cake. Sweet caramels sesame snaps halvah bear claw wafer. Sweet roll soufflé muffin topping muffin brownie…”</p>
                    <div class="ps-about__sign text-center mb-30 mt-40"><img src="{{ asset('assets/images/about/img-signature.png') }}" alt=""></div>
                    <p class="ps-about-sign">CEO - Vanila Bakery Shop</p>
                  </div>
                </div>
                <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12 "><img src="{{ asset('assets/images/about/about-intro-2.png') }}" alt="">
                </div>
          </div>
        </div>
      </div>
      <section class="ps-section pt-80 pb-40">
        <div class="container">
              <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                <div class="ps-section__header mb-50">
                  <h3 class="ps-section__title ps-section__title--left">BREAD</h3>
                </div>
                <div class="ps-section__content">
                  <div class="ps-product--list">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-14.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">Amazin’ Glazin’</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                  <div class="ps-product--list">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-13.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">The Crusty Croissant</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                  <div class="ps-product--list">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-18.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">The Rolling Pin</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                <div class="ps-section__header mb-50">
                  <h3 class="ps-section__title ps-section__title--left">COOKIES</h3>
                </div>
                <div class="ps-section__content">
                  <div class="ps-product--list">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-1.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">Amazin’ Glazin’</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                  <div class="ps-product--list">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-15.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">The Crusty Croissant</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                  <div class="ps-product--list">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-3.jpg') }}" alt=""></div>
                    <div class="ps-product__content">
                      <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">The Rolling Pin</a></h4>
                      <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                      <p class="ps-product__price">
                        <del>£25.00</del>£15.00
                      </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                    </div>
                  </div>
                </div>
              </div>
        </div>
      </section>
      <section class="ps-section ps-section--map">
        <div id="contact-map" data-address="New York, NY" data-title="BAKERY LOCATION!" data-zoom="17"></div>
        <div class="ps-delivery">
          <div class="ps-delivery__header">
            <h3>Contact Us</h3>
            <p>Our Company is the best, meet the creative team that never sleeps. Say something to us we will answer to you.</p>
          </div>
          <div class="ps-delivery__content">
            <form class="ps-delivery__form" action="{{ route('theme.product-listing') }}" method="get">
              <div class="form-group">
                <label>Name<span>*</span></label>
                <input class="form-control" type="text">
              </div>
              <div class="form-group">
                <label>Email<span>*</span></label>
                <input class="form-control" type="email">
              </div>
              <div class="form-group">
                <label>Phone Number<span>*</span></label>
                <input class="form-control" type="text">
              </div>
              <div class="form-group">
                <label>Your message<span>*</span></label>
                <textarea class="form-control"></textarea>
              </div>
              <div class="form-group text-center">
                <button class="ps-btn">Send Message<i class="fa fa-angle-right"></i></button>
              </div>
            </form>
          </div>
        </div>
      </section>
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
      <div class="modal-popup mfp-with-anim mfp-hide" id="quickview-modal" tabindex="-1">
        <button class="modal-close"><i class="fa fa-remove"></i></button>
        <div class="ps-product-modal ps-product--detail clearfix">
              <div class="col-lg-5 col-md-5 col-sm-12 col-xs-12 ">
                <div class="ps-product__thumbnail">
                  <div class="quickview--main" data-owl-auto="true" data-owl-loop="false" data-owl-speed="10000" data-owl-gap="0" data-owl-nav="false" data-owl-dots="false" data-owl-animate-in="" data-owl-animate-out="" data-owl-item="1" data-owl-item-xs="1" data-owl-item-sm="1" data-owl-item-md="1" data-owl-item-lg="1" data-owl-nav-left="&lt;i class=&quot;fa fa-angle-left&quot;&gt;&lt;/i&gt;" data-owl-nav-right="&lt;i class=&quot;fa fa-angle-right&quot;&gt;&lt;/i&gt;">
                    <div class="ps-product__image"><img src="{{ asset('assets/images/cake/img-cake-12.jpg') }}" alt=""></div>
                    <div class="ps-product__image"><img src="{{ asset('assets/images/cake/img-cake-11.jpg') }}" alt=""></div>
                    <div class="ps-product__image"><img src="{{ asset('assets/images/cake/img-cake-10.jpg') }}" alt=""></div>
                    <div class="ps-product__image"><img src="{{ asset('assets/images/cake/img-cake-6.jpg') }}" alt=""></div>
                    <div class="ps-product__image"><img src="{{ asset('assets/images/cake/img-cake-5.jpg') }}" alt=""></div>
                  </div>
                  <div class="quickview--thumbnail" data-owl-auto="true" data-owl-loop="false" data-owl-speed="10000" data-owl-gap="20" data-owl-nav="false" data-owl-dots="false" data-owl-animate-in="" data-owl-animate-out="" data-owl-item="4" data-owl-item-xs="2" data-owl-item-sm="3" data-owl-item-md="4" data-owl-item-lg="4" data-owl-nav-left="&lt;i class=&quot;fa fa-angle-left&quot;&gt;&lt;/i&gt;" data-owl-nav-right="&lt;i class=&quot;fa fa-angle-right&quot;&gt;&lt;/i&gt;"><img src="{{ asset('assets/images/cake/img-cake-12.jpg') }}" alt=""><img src="{{ asset('assets/images/cake/img-cake-11.jpg') }}" alt=""><img src="{{ asset('assets/images/cake/img-cake-10.jpg') }}" alt=""><img src="{{ asset('assets/images/cake/img-cake-6.jpg') }}" alt=""><img src="{{ asset('assets/images/cake/img-cake-5.jpg') }}" alt=""></div>
                </div>
              </div>
              <div class="col-lg-7 col-md-7 col-sm-12 col-xs-12 ">
                <header>
                  <h3 class="ps-product__name">Anytime Cakes</h3>
                  <select class="ps-rating">
                    <option value="1">1</option>
                    <option value="1">2</option>
                    <option value="1">3</option>
                    <option value="1">4</option>
                    <option value="5">5</option>
                  </select>
                  <p class="ps-product__price">£15.00 <del>£25.00</del></p>
                  <div class="ps-product__meta">
                    <p><span> Availability: </span> In stock</p>
                    <p class="category"><span>CATEGORIES: </span><a href="{{ route('theme.product-grid') }}">Cupcake</a>,<a href="{{ route('theme.product-grid') }}">organic</a>,<a href="{{ route('theme.product-grid') }}"> sugar</a>,<a href="{{ route('theme.product-grid') }}"> sweet</a>,<a href="{{ route('theme.product-grid') }}"> bio</a></p>
                  </div>
                  <div class="form-group ps-product__size">
                    <label>Size:</label>
                    <select class="ps-select" data-placeholder="Popupar product">
                      <option value="01">Choose a option</option>
                      <option value="01">Item 01</option>
                      <option value="02">Item 02</option>
                      <option value="03">Item 03</option>
                    </select>
                  </div>
                  <div class="ps-product__shop">
                    <div class="form-group--number">
                      <button class="minus"><span>-</span></button>
                      <input class="form-control" type="text" value="1">
                      <button class="plus"><span>+</span></button>
                    </div>
                    <ul class="ps-product__action">
                      <li><a href="#" data-tooltip="Add to wishlist"><i class="ps-icon--heart"></i></a></li>
                      <li><a href="#" data-tooltip="Compare"><i class="ps-icon--reload"></i></a></li>
                    </ul>
                  </div>
                </header>
                <footer><a class="ps-btn--fullwidth ps-btn ps-btn--sm" href="#">Purchase<i class="fa fa-angle-right"></i></a>
                  <p class="ps-product__sharing">Share with:<a href="#"><i class="fa fa-facebook"></i></a><a href="#"><i class="fa fa-google-plus"></i></a><a href="#"><i class="fa fa-twitter"></i></a></p>
                </footer>
              </div>
        </div>
      </div>
    </div>

@endsection
