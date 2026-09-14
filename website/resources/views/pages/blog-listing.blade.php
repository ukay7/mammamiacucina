@extends('layouts.theme')
@section('title', 'Bakery | Blog Listing')
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
                  <li class="current menu-item-has-children"><a href="{{ route('theme.blog-listing') }}">Blogs</a>
                        <ul class="sub-menu">
                          <li class="current menu-item-has-children"><a href="{{ route('theme.blog-detail') }}">Blog Detail</a>
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
      <div class="ps-section--page-reverse">
        <div class="container">
          <div class="row">
                <div class="col-lg-9 col-md-9 col-sm-12 col-xs-12 ">
                  <div class="pt-80 pb-80">
                    <div class="ps-blog-listing">
                      <div class="ps-post">
                        <div class="ps-post__thumbnail"><a class="ps-post__overlay" href="{{ route('theme.blog-detail') }}"></a><img src="{{ asset('assets/images/blog/img-blog-1.jpg') }}" alt=""></div>
                        <div class="ps-post__header"><a class="ps-post__title" href="{{ route('theme.blog-detail') }}">Barbecue Party Tips For A Truly Amazing Event</a>
                          <div class="ps-post__meta"><span><i class="fa fa-calendar-check-o"></i>November 25, 2017</span><span><i class="fa fa-comment-o"></i>24 Comments</span><span class="tags"><i class="fa fa-tags"></i><a href="{{ route('theme.blog-listing') }}">Travel</a><a href="{{ route('theme.blog-listing') }}">Summer</a><a href="{{ route('theme.blog-listing') }}">Women</a></span></div>
                        </div>
                        <div class="ps-post__content">
                          <p>No matter how far along you are in your sophistication as an amateur astronomer, there is always one fundamental moment that we all go back to. That is that very first moment that we went out where you could really see the cosmos well and you took in the night sky.</p>
                        </div>
                        <footer class="ps-post__footer"><a class="ps-btn ps-btn--sm ps-post__morelink" href="{{ route('theme.blog-detail') }}">Read more</a>
                          <div class="ps-post__action"><a class="like" href="#">Like<i class="fa fa-heart"></i><span><i>2</i></span></a><a class="facebook" href="#"><i class="fa fa-facebook"></i>Share</a><a class="twitter" href="#"><i class="fa fa-twitter"></i>Tweet</a></div>
                        </footer>
                      </div>
                    </div>
                    <div class="ps-blog-listing">
                      <div class="ps-post">
                        <div class="ps-post__thumbnail"><a class="ps-post__overlay" href="{{ route('theme.blog-detail') }}"></a><img src="{{ asset('assets/images/blog/img-blog-2.jpg') }}" alt=""></div>
                        <div class="ps-post__header"><a class="ps-post__title" href="{{ route('theme.blog-detail') }}">Barbecue Party Tips For A Truly Amazing Event</a>
                          <div class="ps-post__meta"><span><i class="fa fa-calendar-check-o"></i>November 25, 2017</span><span><i class="fa fa-comment-o"></i>24 Comments</span><span class="tags"><i class="fa fa-tags"></i><a href="{{ route('theme.blog-listing') }}">Travel</a><a href="{{ route('theme.blog-listing') }}">Summer</a><a href="{{ route('theme.blog-listing') }}">Women</a></span></div>
                        </div>
                        <div class="ps-post__content">
                          <p>No matter how far along you are in your sophistication as an amateur astronomer, there is always one fundamental moment that we all go back to. That is that very first moment that we went out where you could really see the cosmos well and you took in the night sky.</p>
                        </div>
                        <footer class="ps-post__footer"><a class="ps-btn ps-btn--sm ps-post__morelink" href="{{ route('theme.blog-detail') }}">Read more</a>
                          <div class="ps-post__action"><a class="like" href="#">Like<i class="fa fa-heart"></i><span><i>2</i></span></a><a class="facebook" href="#"><i class="fa fa-facebook"></i>Share</a><a class="twitter" href="#"><i class="fa fa-twitter"></i>Tweet</a></div>
                        </footer>
                      </div>
                    </div>
                    <div class="ps-blog-listing">
                      <div class="ps-post">
                        <div class="ps-post__thumbnail"><a class="ps-post__overlay" href="{{ route('theme.blog-detail') }}"></a><img src="{{ asset('assets/images/blog/img-blog-3.jpg') }}" alt=""></div>
                        <div class="ps-post__header"><a class="ps-post__title" href="{{ route('theme.blog-detail') }}">Barbecue Party Tips For A Truly Amazing Event</a>
                          <div class="ps-post__meta"><span><i class="fa fa-calendar-check-o"></i>November 25, 2017</span><span><i class="fa fa-comment-o"></i>24 Comments</span><span class="tags"><i class="fa fa-tags"></i><a href="{{ route('theme.blog-listing') }}">Travel</a><a href="{{ route('theme.blog-listing') }}">Summer</a><a href="{{ route('theme.blog-listing') }}">Women</a></span></div>
                        </div>
                        <div class="ps-post__content">
                          <p>No matter how far along you are in your sophistication as an amateur astronomer, there is always one fundamental moment that we all go back to. That is that very first moment that we went out where you could really see the cosmos well and you took in the night sky.</p>
                        </div>
                        <footer class="ps-post__footer"><a class="ps-btn ps-btn--sm ps-post__morelink" href="{{ route('theme.blog-detail') }}">Read more</a>
                          <div class="ps-post__action"><a class="like" href="#">Like<i class="fa fa-heart"></i><span><i>2</i></span></a><a class="facebook" href="#"><i class="fa fa-facebook"></i>Share</a><a class="twitter" href="#"><i class="fa fa-twitter"></i>Tweet</a></div>
                        </footer>
                      </div>
                    </div>
                    <div class="ps-blog-listing">
                      <div class="ps-post">
                        <div class="ps-post__thumbnail"><a class="ps-post__overlay" href="{{ route('theme.blog-detail') }}"></a><img src="{{ asset('assets/images/blog/img-blog-4.jpg') }}" alt=""></div>
                        <div class="ps-post__header"><a class="ps-post__title" href="{{ route('theme.blog-detail') }}">Barbecue Party Tips For A Truly Amazing Event</a>
                          <div class="ps-post__meta"><span><i class="fa fa-calendar-check-o"></i>November 25, 2017</span><span><i class="fa fa-comment-o"></i>24 Comments</span><span class="tags"><i class="fa fa-tags"></i><a href="{{ route('theme.blog-listing') }}">Travel</a><a href="{{ route('theme.blog-listing') }}">Summer</a><a href="{{ route('theme.blog-listing') }}">Women</a></span></div>
                        </div>
                        <div class="ps-post__content">
                          <p>No matter how far along you are in your sophistication as an amateur astronomer, there is always one fundamental moment that we all go back to. That is that very first moment that we went out where you could really see the cosmos well and you took in the night sky.</p>
                        </div>
                        <footer class="ps-post__footer"><a class="ps-btn ps-btn--sm ps-post__morelink" href="{{ route('theme.blog-detail') }}">Read more</a>
                          <div class="ps-post__action"><a class="like" href="#">Like<i class="fa fa-heart"></i><span><i>2</i></span></a><a class="facebook" href="#"><i class="fa fa-facebook"></i>Share</a><a class="twitter" href="#"><i class="fa fa-twitter"></i>Tweet</a></div>
                        </footer>
                      </div>
                    </div>
                    <div class="ps-blog-listing">
                      <div class="ps-post">
                        <div class="ps-post__thumbnail"><a class="ps-post__overlay" href="{{ route('theme.blog-detail') }}"></a><img src="{{ asset('assets/images/blog/img-blog-5.jpg') }}" alt=""></div>
                        <div class="ps-post__header"><a class="ps-post__title" href="{{ route('theme.blog-detail') }}">Barbecue Party Tips For A Truly Amazing Event</a>
                          <div class="ps-post__meta"><span><i class="fa fa-calendar-check-o"></i>November 25, 2017</span><span><i class="fa fa-comment-o"></i>24 Comments</span><span class="tags"><i class="fa fa-tags"></i><a href="{{ route('theme.blog-listing') }}">Travel</a><a href="{{ route('theme.blog-listing') }}">Summer</a><a href="{{ route('theme.blog-listing') }}">Women</a></span></div>
                        </div>
                        <div class="ps-post__content">
                          <p>No matter how far along you are in your sophistication as an amateur astronomer, there is always one fundamental moment that we all go back to. That is that very first moment that we went out where you could really see the cosmos well and you took in the night sky.</p>
                        </div>
                        <footer class="ps-post__footer"><a class="ps-btn ps-btn--sm ps-post__morelink" href="{{ route('theme.blog-detail') }}">Read more</a>
                          <div class="ps-post__action"><a class="like" href="#">Like<i class="fa fa-heart"></i><span><i>2</i></span></a><a class="facebook" href="#"><i class="fa fa-facebook"></i>Share</a><a class="twitter" href="#"><i class="fa fa-twitter"></i>Tweet</a></div>
                        </footer>
                      </div>
                    </div>
                        <div class="ps-pagination">
                          <ul class="pagination">
                            <li><a href="#"><i class="fa fa-arrow-left"></i></a></li>
                            <li class="active"><a href="#">1</a></li>
                            <li><a href="#">2</a></li>
                            <li><a href="#">3</a></li>
                            <li><a href="#">4</a></li>
                            <li><a href="#"><i class="fa fa-arrow-right"></i></a></li>
                          </ul>
                        </div>
                  </div>
                </div>
                <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 ">
                  <div class="ps-sidebar">
                    <aside class="ps-widget ps-widget--sidebar ps-widget--search">
                      <form method="get" action="{{ route('theme.product-grid') }}">
                        <input class="form-control" type="text" placeholder="Type here bakery name...">
                        <button type="submit"><i class="ps-icon--search"></i></button>
                      </form>
                    </aside>
                    <aside class="ps-widget ps-widget--sidebar ps-widget--category">
                      <div class="ps-widget__header">
                        <h3 class="ps-widget__title">Category</h3>
                      </div>
                      <div class="ps-widget__content">
                        <ul class="ps-list--arrow">
                          <li class="current"><a href="{{ route('theme.product-listing') }}"><span class="circle"></span>All bakery (321)</a></li>
                          <li><a href="{{ route('theme.product-listing') }}"><span class="circle"></span>Amazin’ Glazin’</a></li>
                          <li><a href="{{ route('theme.product-listing') }}"><span class="circle"></span>The Crusty Croissant</a></li>
                          <li><a href="{{ route('theme.product-listing') }}"><span class="circle"></span>The Rolling Pin</a></li>
                          <li><a href="{{ route('theme.product-listing') }}"><span class="circle"></span>Skippity Scones</a></li>
                          <li><a href="{{ route('theme.product-listing') }}"><span class="circle"></span>Mad Batter</a></li>
                          <li><a href="{{ route('theme.product-listing') }}"><span class="circle"></span>Confection Connection</a></li>
                        </ul>
                      </div>
                    </aside>
                    <aside class="ps-widget ps-widget--sidebar ps-widget--ads">
                      <div class="ps-widget__header">
                        <h3 class="ps-widget__title">Ads Banner</h3>
                      </div>
                      <div class="ps-widget__content"><img src="{{ asset('assets/images/widget/banner2x.png') }}" alt=""></div>
                    </aside>
                    <aside class="ps-widget ps-widget--sidebar ps-widget--recent-post">
                      <div class="ps-widget__header">
                        <h3 class="ps-widget__title">Recent Post</h3>
                      </div>
                      <div class="ps-widget__content">
                        <div class="ps-post ps-post--sidebar">
                          <div class="ps-post__thumbnail"><a class="ps-post__overlay" href="#"></a><img src="{{ asset('assets/images/blog/thumbnail1.jpg') }}" alt=""></div>
                          <div class="ps-post__content"><a href="#" data-number-line="2">Micenas Placerat Nibh Loreming Fentum</a><span>SEP 29, 2015</span></div>
                        </div>
                        <div class="ps-post ps-post--sidebar">
                          <div class="ps-post__thumbnail"><a class="ps-post__overlay" href="#"></a><img src="{{ asset('assets/images/blog/thumbnail2.jpg') }}" alt=""></div>
                          <div class="ps-post__content"><a href="#" data-number-line="2">Micenas Placerat Nibh Loreming Fentum</a><span>SEP 29, 2015</span></div>
                        </div>
                        <div class="ps-post ps-post--sidebar">
                          <div class="ps-post__thumbnail"><a class="ps-post__overlay" href="#"></a><img src="{{ asset('assets/images/blog/thumbnail3.jpg') }}" alt=""></div>
                          <div class="ps-post__content"><a href="#" data-number-line="2">Micenas Placerat Nibh Loreming Fentum</a><span>SEP 29, 2015</span></div>
                        </div>
                      </div>
                    </aside>
                    <aside class="ps-widget ps-widget--sidebar ps-widget--tags">
                      <div class="ps-widget__header">
                        <h3 class="ps-widget__title">TAGS</h3>
                      </div>
                      <div class="ps-widget__content">
                            <ul class="ps-tags">
                              <li><a href="#">Cupcake</a></li>
                              <li><a href="#">vanila</a></li>
                              <li><a href="#">sugar flower</a></li>
                              <li><a href="#">vanila</a></li>
                              <li><a href="#">coconut</a></li>
                              <li><a href="#">vanila</a></li>
                            </ul>
                      </div>
                    </aside>
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
