@extends('layouts.theme')
@section('title', 'Mamma Mia Cucina | Authentic Italian Cakes & Pastries')
@section('body-class', 'page-init mmc-home')
@section('content')

    @include('partials.mmc-hero')
    @include('partials.mmc-tradition')
    @include('partials.mmc-categories')
    @include('partials.mmc-most-loved')
    @include('partials.mmc-new-arrivals')
    @include('partials.mmc-baking')
    <div id="back2top"><i class="fa fa-angle-up"></i></div>
    <div class="page-wrap">
      @if (config('homepage.show_legacy_sections', false))
      <div class="ps-section pt-80 pb-40">
        <div class="container">
          <div class="ps-countdown">
            <div class="row">
                  <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 "><img src="{{ asset('assets/images/counter.png') }}" alt="">
                  </div>
                  <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                    <header class="text-center">
                      <div class="ps-section__top">Hot Deal Today</div>
                      <h4>Vanilla cupcake with red sugar flowers</h4>
                      <p>Only: <span> £1.55 </span></p>
                      <ul class="ps-countdown__time" data-time="Dec 30, 2019 15:37:25">
                        <!--li <span class="days"></span><p>Days</p>-->
                        <!--li.divider :-->
                        <li><span class="hours"></span><p>Hours</p></li>
                        <li class="divider">:</li>
                        <li><span class="minutes"></span><p>minutes</p></li>
                        <li class="divider">:</li>
                        <li><span class="seconds"></span><p>Seconds</p></li>
                      </ul><a class="ps-btn" href="#">Order Now<i class="fa fa-angle-right"></i></a>
                    </header>
                  </div>
            </div>
          </div>
        </div>
      </div>
      @endif
      @if (config('homepage.show_legacy_sections', false))
      <section class="ps-section ps-section--best-seller pt-40 pb-100">
        <div class="container">
          <div class="ps-section__header text-center mb-50">
            <h4 class="ps-section__top">Sweet Cupcakes</h4>
            <h3 class="ps-section__title ps-section__title--full">BEST SELLER</h3>
          </div>
          <div class="ps-section__content">
            <div class="owl-slider owl-slider--best-seller" data-owl-auto="true" data-owl-loop="true" data-owl-speed="5000" data-owl-gap="30" data-owl-nav="true" data-owl-dots="false" data-owl-animate-in="" data-owl-animate-out="" data-owl-item="4" data-owl-item-xs="1" data-owl-item-sm="2" data-owl-item-md="3" data-owl-item-lg="4" data-owl-nav-left="&lt;i class=&quot;ps-icon--back&quot;&gt;&lt;/i&gt;" data-owl-nav-right="&lt;i class=&quot;ps-icon--next&quot;&gt;&lt;/i&gt;">
              <div class="ps-product">
                <div class="ps-product__thumbnail">
                  <div class="ps-badge"><span>-50%</span></div><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-7.jpg') }}" alt="">
                  <ul class="ps-product__action">
                    <li><a class="popup-modal" href="#quickview-modal" data-effect="mfp-zoom-out" data-tooltip="View"><i class="ps-icon--search"></i></a></li>
                    <li><a href="#" data-tooltip="Add to wishlist"><i class="ps-icon--heart"></i></a></li>
                    <li><a href="#" data-tooltip="Compare"><i class="ps-icon--reload"></i></a></li>
                    <li><a href="#" data-tooltip="Add to cart"><i class="ps-icon--shopping-cart"></i></a></li>
                  </ul>
                </div>
                <div class="ps-product__content"><a class="ps-product__title" href="{{ route('theme.product-detail') }}">Red sugar flower</a>
                  <div class="ps-product__category"><a class="ps-product__category" href="{{ route('theme.product-listing') }}">cupcake</a><a class="ps-product__category" href="{{ route('theme.product-listing') }}">sweet</a><a class="ps-product__category" href="{{ route('theme.product-listing') }}">bio</a>
                  </div>
                  <select class="ps-rating">
                    <option value="1">1</option>
                    <option value="1">2</option>
                    <option value="1">3</option>
                    <option value="1">4</option>
                    <option value="5">5</option>
                  </select>
                  <p class="ps-product__price">£5.00</p>
                </div>
              </div>
              <div class="ps-product">
                <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-2.jpg') }}" alt="">
                  <ul class="ps-product__action">
                    <li><a class="popup-modal" href="#quickview-modal" data-effect="mfp-zoom-out" data-tooltip="View"><i class="ps-icon--search"></i></a></li>
                    <li><a href="#" data-tooltip="Add to wishlist"><i class="ps-icon--heart"></i></a></li>
                    <li><a href="#" data-tooltip="Compare"><i class="ps-icon--reload"></i></a></li>
                    <li><a href="#" data-tooltip="Add to cart"><i class="ps-icon--shopping-cart"></i></a></li>
                  </ul>
                </div>
                <div class="ps-product__content"><a class="ps-product__title" href="{{ route('theme.product-detail') }}">Cupcake Queen</a>
                  <div class="ps-product__category"><a class="ps-product__category" href="{{ route('theme.product-listing') }}">cupcake</a><a class="ps-product__category" href="{{ route('theme.product-listing') }}">sweet</a><a class="ps-product__category" href="{{ route('theme.product-listing') }}">bio</a>
                  </div>
                  <select class="ps-rating">
                    <option value="1">1</option>
                    <option value="1">2</option>
                    <option value="1">3</option>
                    <option value="1">4</option>
                    <option value="5">5</option>
                  </select>
                  <p class="ps-product__price">£5.00</p>
                </div>
              </div>
              <div class="ps-product">
                <div class="ps-product__thumbnail">
                  <div class="ps-badge"><span>-50%</span></div><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-4.jpg') }}" alt="">
                  <ul class="ps-product__action">
                    <li><a class="popup-modal" href="#quickview-modal" data-effect="mfp-zoom-out" data-tooltip="View"><i class="ps-icon--search"></i></a></li>
                    <li><a href="#" data-tooltip="Add to wishlist"><i class="ps-icon--heart"></i></a></li>
                    <li><a href="#" data-tooltip="Compare"><i class="ps-icon--reload"></i></a></li>
                    <li><a href="#" data-tooltip="Add to cart"><i class="ps-icon--shopping-cart"></i></a></li>
                  </ul>
                </div>
                <div class="ps-product__content"><a class="ps-product__title" href="{{ route('theme.product-detail') }}">Cupcake Glory</a>
                  <div class="ps-product__category"><a class="ps-product__category" href="{{ route('theme.product-listing') }}">cupcake</a><a class="ps-product__category" href="{{ route('theme.product-listing') }}">sweet</a><a class="ps-product__category" href="{{ route('theme.product-listing') }}">bio</a>
                  </div>
                  <select class="ps-rating">
                    <option value="1">1</option>
                    <option value="1">2</option>
                    <option value="1">3</option>
                    <option value="1">4</option>
                    <option value="5">5</option>
                  </select>
                  <p class="ps-product__price">£5.00</p>
                </div>
              </div>
              <div class="ps-product">
                <div class="ps-product__thumbnail">
                  <div class="ps-badge ps-badge--new"><span>New</span></div><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-8.jpg') }}" alt="">
                  <ul class="ps-product__action">
                    <li><a class="popup-modal" href="#quickview-modal" data-effect="mfp-zoom-out" data-tooltip="View"><i class="ps-icon--search"></i></a></li>
                    <li><a href="#" data-tooltip="Add to wishlist"><i class="ps-icon--heart"></i></a></li>
                    <li><a href="#" data-tooltip="Compare"><i class="ps-icon--reload"></i></a></li>
                    <li><a href="#" data-tooltip="Add to cart"><i class="ps-icon--shopping-cart"></i></a></li>
                  </ul>
                </div>
                <div class="ps-product__content"><a class="ps-product__title" href="{{ route('theme.product-detail') }}">Sweet Cakes</a>
                  <div class="ps-product__category"><a class="ps-product__category" href="{{ route('theme.product-listing') }}">cupcake</a><a class="ps-product__category" href="{{ route('theme.product-listing') }}">sweet</a><a class="ps-product__category" href="{{ route('theme.product-listing') }}">bio</a>
                  </div>
                  <select class="ps-rating">
                    <option value="1">1</option>
                    <option value="1">2</option>
                    <option value="1">3</option>
                    <option value="1">4</option>
                    <option value="5">5</option>
                  </select>
                  <p class="ps-product__price">£5.00</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
      @endif
      @if (config('homepage.show_legacy_sections', false))
      <section class="ps-section ps-section--offer pt-80 pb-40">
        <div class="container">
          <div class="ps-section__header text-center mb-100">
            <h4 class="ps-section__top">Making People Happy</h4>
            <h3 class="ps-section__title ps-section__title--full">OFFER THIS WEEK</h3>
          </div>
          <div class="ps-section__content">
            <div class="masonry-wrapper" data-col-md="4" data-col-sm="2" data-col-xs="1" data-gap="30" data-radio="100%">
              <div class="ps-masonry">
                <div class="grid-sizer"></div>
                <div class="grid-item high wide">
                  <div class="grid-item__content-wrapper">
                    <div class="ps-offer"><img src="{{ asset('assets/images/offer/banner-1.jpg') }}" alt=""><a class="ps-offer__overlay" href="#"></a></div>
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
                <div class="grid-item wide">
                  <div class="grid-item__content-wrapper">
                    <div class="ps-offer"><img src="{{ asset('assets/images/offer/banner-4.jpg') }}" alt=""><a class="ps-offer__overlay" href="#"></a></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
      @endif
      @if (config('homepage.show_legacy_sections', false))
      <section class="ps-section ps-section--list-product pt-40 pb-80">
        <div class="container">
          <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                  <div class="ps-section__header">
                    <h3 class="ps-section__title ps-section__title--left">HOT CAKE</h3>
                  </div>
                  <div class="ps-section__content">
                    <div class="ps-product--list">
                      <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-12.jpg') }}" alt=""></div>
                      <div class="ps-product__content">
                        <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">Amazin’ Glazin’</a></h4>
                        <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                        <p class="ps-product__price">
                          <del>£25.00</del>£15.00
                        </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                      </div>
                    </div>
                    <div class="ps-product--list">
                      <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-3.jpg') }}" alt=""></div>
                      <div class="ps-product__content">
                        <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">The Crusty Croissant</a></h4>
                        <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                        <p class="ps-product__price">
                          <del>£25.00</del>£15.00
                        </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                      </div>
                    </div>
                    <div class="ps-product--list">
                      <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-7.jpg') }}" alt=""></div>
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
                  <div class="ps-section__header">
                    <h3 class="ps-section__title ps-section__title--left">New CAKE</h3>
                  </div>
                  <div class="ps-section__content">
                    <div class="ps-product--list">
                      <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-6.jpg') }}" alt=""></div>
                      <div class="ps-product__content">
                        <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">Anytime Cakes</a></h4>
                        <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                        <p class="ps-product__price">
                          <del>£25.00</del>£15.00
                        </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                      </div>
                    </div>
                    <div class="ps-product--list">
                      <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-8.jpg') }}" alt=""></div>
                      <div class="ps-product__content">
                        <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">Sugar Booger</a></h4>
                        <p>Lollipop dessert donut marzipan cookie bonbon sesame snaps chocolate.</p>
                        <p class="ps-product__price">
                          <del>£25.00</del>£15.00
                        </p><a class="ps-btn ps-btn--xs" href="{{ route('theme.cart') }}">Order now<i class="fa fa-angle-right"></i></a>
                      </div>
                    </div>
                    <div class="ps-product--list">
                      <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-11.jpg') }}" alt=""></div>
                      <div class="ps-product__content">
                        <h4 class="ps-product__title"><a href="{{ route('theme.product-detail') }}">The Mix-Up</a></h4>
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
      @endif
      @if (config('homepage.show_legacy_sections', false))
      <section class="ps-section ps-section--team ps-section--pattern pt-80 pb-80">
        <div class="container">
          <div class="row">
                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 ">
                  <div class="ps-section__header">
                    <h3 class="ps-section__title ps-section__title--left">OUR BAKER</h3>
                    <p>We all have those moments in our lives when we feel as if everything needs to be exactly rigt.</p>
                    <p>Dessert tiramisu tart donut macaroon. Gummi bears lollipop marzipan. Caramels gummi bears icing jelly beans cheesecake brownie topping candy sugaplum.</p>
                    <ul class="ps-list ps-list--dot">
                      <li>Caramels gummi bears</li>
                      <li>Caramels gummi bears</li>
                      <li>Caramels gummi bears</li>
                    </ul><a class="ps-btn ps-section__morelink" href="#">Read more<i class="fa fa-angle-right"></i></a>
                  </div>
                </div>
                <div class="col-lg-8 col-md-8 col-sm-12 col-xs-12 ">
                  <div class="ps-section__content">
                    <div class="row">
                          <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 ">
                            <article class="ps-people">
                              <div class="ps-people__thumbnail"><a class="ps-people__overlay" href="#"></a><img src="{{ asset('assets/images/team/team-1.jpg') }}" alt=""></div>
                              <div class="ps-people__content">
                                <h4>Christian Gregory</h4><span class="ps-people__position">CEO - Founder</span>
                                <p>Jelly topping halvah caramels sweet cake gummi bears toffee.</p>
                                    <ul class="ps-people__social">
                                      <li><a href="#"><i class="fa fa-facebook"></i></a></li>
                                      <li><a href="#"><i class="fa fa-google"></i></a></li>
                                      <li><a href="#"><i class="fa fa-twitter"></i></a></li>
                                    </ul>
                              </div>
                            </article>
                          </div>
                          <div class="col-lg-6 col-md-6 col-sm-6 col-xs-12 ">
                            <article class="ps-people">
                              <div class="ps-people__thumbnail"><a class="ps-people__overlay" href="#"></a><img src="{{ asset('assets/images/team/team-2.jpg') }}" alt=""></div>
                              <div class="ps-people__content">
                                <h4>Christian Gregory</h4><span class="ps-people__position">CEO - Founder</span>
                                <p>Jelly topping halvah caramels sweet cake gummi bears toffee.</p>
                                    <ul class="ps-people__social">
                                      <li><a href="#"><i class="fa fa-facebook"></i></a></li>
                                      <li><a href="#"><i class="fa fa-google"></i></a></li>
                                      <li><a href="#"><i class="fa fa-twitter"></i></a></li>
                                    </ul>
                              </div>
                            </article>
                          </div>
                    </div>
                  </div>
                </div>
          </div>
        </div>
      </section>
      @endif
      @if (config('homepage.show_legacy_sections', false))
      <section class="ps-section ps-section--news pt-100 pb-100">
        <div class="container">
          <div class="ps-section__header text-center">
            <h4 class="ps-section__top">Our Story</h4>
            <h3 class="ps-section__title ps-section__title--full">BLOG & NEWS</h3>
          </div>
          <div class="ps-section__content">
            <div class="row">
                  <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                    <div class="ps-new ps-new--large"><img src="{{ asset('assets/images/new/new-large.jpg') }}" alt="">
                      <div class="ps-new__container">
                        <header class="ps-new__header">
                          <p>by<a href="#"> Athony</a> / February 12, 2017</p><a class="ps-new__title" href="{{ route('theme.blog-detail') }}">Sweet Bakery by <br> Joni William</a>
                        </header>
                        <div class="ps-new__content">
                          <p data-number-line="2">Fond his say old meet cold find come <br> whom. The sir park sake bred.</p><a class="ps-btn ps-btn--sm" href="{{ route('theme.blog-detail') }}">Read more</a>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 ">
                    <div class="ps-new"><img src="{{ asset('assets/images/new/new-small-1.jpg') }}" alt="">
                      <div class="ps-new__container">
                        <header class="ps-new__header">
                          <p>by<a href="#"> Athony</a> / February 12, 2017</p><a class="ps-new__title" href="{{ route('theme.blog-detail') }}">Where I Learning Cook Cupcakes ?</a>
                        </header>
                        <div class="ps-new__content">
                          <p data-number-line="2">No comfort do written conduct at prevent manners on. Celebrated contrasted discretion him sympath</p><a class="ps-btn ps-btn--sm" href="{{ route('theme.blog-detail') }}">Read more</a>
                        </div>
                      </div>
                    </div>
                    <div class="ps-new"><img src="{{ asset('assets/images/new/new-small-2.jpg') }}" alt="">
                      <div class="ps-new__container">
                        <header class="ps-new__header">
                          <p>by<a href="#"> Athony</a> / February 12, 2017</p><a class="ps-new__title" href="{{ route('theme.blog-detail') }}">Where I Learning Cook Cupcakes ?</a>
                        </header>
                        <div class="ps-new__content">
                          <p data-number-line="2">No comfort do written conduct at prevent manners on. Celebrated contrasted discretion him sympath</p><a class="ps-btn ps-btn--sm" href="{{ route('theme.blog-detail') }}">Read more</a>
                        </div>
                      </div>
                    </div>
                  </div>
            </div>
          </div>
        </div>
      </section>
      @endif
      @if (config('homepage.show_legacy_sections', false))
      <div class="ps-section ps-section--partner">
        <div class="container">
          <div class="owl-slider" data-owl-auto="true" data-owl-loop="true" data-owl-speed="10000" data-owl-gap="40" data-owl-nav="false" data-owl-dots="false" data-owl-animate-in="" data-owl-animate-out="" data-owl-item="6" data-owl-item-xs="3" data-owl-item-sm="4" data-owl-item-md="5" data-owl-item-lg="6" data-owl-nav-left="&lt;i class=&quot;fa fa-angle-left&quot;&gt;&lt;/i&gt;" data-owl-nav-right="&lt;i class=&quot;fa fa-angle-right&quot;&gt;&lt;/i&gt;"><a href="#"><img src="{{ asset('assets/images/partner/1.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/2.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/3.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/4.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/5.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/6.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/7.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/8.png') }}" alt=""></a>
          </div>
        </div>
      </div>
      @endif
      @if (config('homepage.show_legacy_sections', false))
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
      @endif
      @include('partials.mmc-about-video')
      @include('partials.mmc-footer')
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
      <div class="mfp-with-anim modal-popup mfp-hide" id="modal--subscribe">
        <button class="modal-close"><i class="fa fa-remove"></i></button><img src="{{ asset('assets/images/img-demo-4.png') }}" alt="">
        <form action="#" method="get" data-static-preview="true">
          <h3>STAY UP-TO-DATE  WITH OUR NEWLETTER</h3>
          <p>Follow us & get <span> 20% OFF </span> coupon for first purchase !!!!!</p>
          <div class="form-group">
            <input class="form-control" type="text" placeholder="Type your email...">
            <button class="ps-btn ps-btn--sm">Subscribe</button>
          </div>
        </form>
      </div>
    </div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/mmc-about-video.css') }}?v={{ filemtime(public_path('assets/css/mmc-about-video.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/mmc-baking.css') }}?v={{ filemtime(public_path('assets/css/mmc-baking.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/mmc-new-arrivals.css') }}?v={{ filemtime(public_path('assets/css/mmc-new-arrivals.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/mmc-most-loved.css') }}?v={{ filemtime(public_path('assets/css/mmc-most-loved.css')) }}">

<link rel="stylesheet" href="{{ asset('assets/css/mmc-categories.css') }}?v={{ filemtime(public_path('assets/css/mmc-categories.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/mmc-tradition.css') }}?v={{ filemtime(public_path('assets/css/mmc-tradition.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/mmc-home.css') }}?v={{ filemtime(public_path('assets/css/mmc-home.css')) }}">
@endpush
@push('scripts')
@include('partials.mmc-shop-data')
<script src="{{ asset('assets/js/mmc-baking.js') }}?v={{ filemtime(public_path('assets/js/mmc-baking.js')) }}"></script>
<script src="{{ asset('assets/js/mmc-new-arrivals.js') }}?v={{ filemtime(public_path('assets/js/mmc-new-arrivals.js')) }}"></script>
<script src="{{ asset('assets/js/mmc-tradition.js') }}?v={{ filemtime(public_path('assets/js/mmc-tradition.js')) }}"></script>
<script src="{{ asset('assets/js/mmc-menu.js') }}?v={{ filemtime(public_path('assets/js/mmc-menu.js')) }}"></script>
<script src="{{ asset('assets/js/mmc-slider.js') }}?v={{ filemtime(public_path('assets/js/mmc-slider.js')) }}"></script>
@endpush











