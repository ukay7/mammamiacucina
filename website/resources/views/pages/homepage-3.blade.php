@extends('layouts.theme')
@section('title', 'Bakery | Homepage 3')
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
                  <li class="current menu-item-has-children"><a href="{{ route('theme.index') }}">Home</a>
                        <ul class="sub-menu">
                          <li><a href="#">Hot Demo</a></li>
                          <li class="end-block"><a href="#">Trending</a></li>
                          <li class="current "><a href="{{ route('theme.index') }}">HOMEPAGE 1</a></li>
                          <li class="current "><a href="{{ route('theme.homepage-2') }}">HOMEPAGE 2</a></li>
                          <li class="current "><a href="{{ route('theme.homepage-3') }}">HOMEPAGE 3</a></li>
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
      <div class="ps-banner--home-2">
        <div class="rev_slider_wrapper fullscreen-container" id="revolution-slider-1" data-alias="concept121" data-source="gallery" style="background-color:#000000;padding:0px;">
          <div class="rev_slider fullscreenbanner" id="rev_slider_1059_1" style="display:none;" data-version="5.4.1">
            <ul class="ps-banner ps-banner--2">
              <li data-index="rs-2972" data-transition="slidingoverlayhorizontal" data-slotamount="default" data-hideafterloop="0" data-hideslideonmobile="off" data-easein="default" data-easeout="default" data-masterspeed="default" data-thumb="{{ asset('assets/images/banner/img-slider-3.jpg') }}" data-rotate="0" data-saveperformance="off" data-title="Web Show" data-param1="" data-param2="" data-param3="" data-param4="" data-param5="" data-param6="" data-param7="" data-param8="" data-param9="" data-param10="" data-description=""><img class="rev-slidebg" src="{{ asset('assets/images/banner/img-slider-3.jpg') }}" alt="" data-bgposition="center center" data-bgfit="cover" data-bgrepeat="no-repeat" data-bgparallax="5" data-no-retina>
                <div class="tp-caption ps-banner__caption" id="layer01" data-x="['center','center','center','center']" data-hoffset="['0','0','0','0']" data-y="['middle','middle','middle','middle']" data-voffset="['-100','-80','-120','-120']" data-width="['none','none','none','400']" data-whitespace="['nowrap','nowrap','nowrap','normal']" data-type="text" data-responsive_offset="on" data-frames="[{&quot;from&quot;:&quot;z:0;rX:0;rY:0;rZ:0;sX:0.9;sY:0.9;skX:0;skY:0;opacity:0;&quot;,&quot;speed&quot;:1500,&quot;to&quot;:&quot;o:1;&quot;,&quot;delay&quot;:1700,&quot;ease&quot;:&quot;Power3.easeInOut&quot;},{&quot;delay&quot;:&quot;wait&quot;,&quot;speed&quot;:1000,&quot;to&quot;:&quot;x:left(R);&quot;,&quot;ease&quot;:&quot;Power3.easeIn&quot;}]" style="z-index: 7; white-space: nowrap;text-transform:left;">Baked fresh for you</div>
                <div class="tp-caption ps-banner__description" id="layer02" data-x="['center','center','center','center']" data-hoffset="['0','0','0','0']" data-y="['middle','middle','middle','middle']" data-voffset="['0','0','0','0']" data-type="text" data-responsive_offset="on" data-textAlign="['center','center','center','center']" data-frames="[{&quot;from&quot;:&quot;z:0;rX:0;rY:0;rZ:0;sX:0.9;sY:0.9;skX:0;skY:0;opacity:0;&quot;,&quot;speed&quot;:1500,&quot;to&quot;:&quot;o:1;&quot;,&quot;delay&quot;:1500,&quot;ease&quot;:&quot;Power3.easeInOut&quot;},{&quot;delay&quot;:&quot;wait&quot;,&quot;speed&quot;:1000,&quot;to&quot;:&quot;x:left(R);&quot;,&quot;ease&quot;:&quot;Power3.easeIn&quot;}]">Carrot cake macaroon topping jelly-o dessert cake. Tiramisu gummies wafer brownie tiramisu cake. <br/> Icing powder candy canes cotton candy pie liquorice.</div><a class="tp-caption ps-btn ps-btn--lg" href="#" id="layer03" data-x="['center','center','center','center']" data-hoffset="['0','0','0','0']" data-y="['middle','middle','middle','middle']" data-voffset="['80','70','70','70']" data-type="text" data-responsive_offset="on" data-textAlign="['center','center','center','center']" data-frames="[{&quot;from&quot;:&quot;z:0;rX:0;rY:0;rZ:0;sX:0.9;sY:0.9;skX:0;skY:0;opacity:0;&quot;,&quot;speed&quot;:1500,&quot;to&quot;:&quot;o:1;&quot;,&quot;delay&quot;:1500,&quot;ease&quot;:&quot;Power3.easeInOut&quot;},{&quot;delay&quot;:&quot;wait&quot;,&quot;speed&quot;:1000,&quot;to&quot;:&quot;x:left(R);&quot;,&quot;ease&quot;:&quot;Power3.easeIn&quot;}]">TRY OUR CUPCAKES <i class="fa fa-angle-right"></i></a>
              </li>
              <li data-index="rs-2973" data-transition="slidingoverlayhorizontal" data-slotamount="default" data-hideafterloop="0" data-hideslideonmobile="off" data-easein="default" data-easeout="default" data-masterspeed="default" data-thumb="{{ asset('assets/images/banner/img-slider-4.jpg') }}" data-rotate="0" data-saveperformance="off" data-title="Web Show" data-param1="" data-param2="" data-param3="" data-param4="" data-param5="" data-param6="" data-param7="" data-param8="" data-param9="" data-param10="" data-description=""><img class="rev-slidebg" src="{{ asset('assets/images/banner/img-slider-4.jpg') }}" alt="" data-bgposition="center center" data-bgfit="cover" data-bgrepeat="no-repeat" data-bgparallax="5" data-no-retina>
                <div class="tp-caption ps-banner__caption" id="layer04" data-x="['center','center','center','center']" data-hoffset="['0','0','0','0']" data-y="['middle','middle','middle','middle']" data-voffset="['-100','-80','-120','-120']" data-width="['none','none','none','400']" data-whitespace="['nowrap','nowrap','nowrap','normal']" data-type="text" data-responsive_offset="on" data-frames="[{&quot;from&quot;:&quot;z:0;rX:0;rY:0;rZ:0;sX:0.9;sY:0.9;skX:0;skY:0;opacity:0;&quot;,&quot;speed&quot;:1500,&quot;to&quot;:&quot;o:1;&quot;,&quot;delay&quot;:1700,&quot;ease&quot;:&quot;Power3.easeInOut&quot;},{&quot;delay&quot;:&quot;wait&quot;,&quot;speed&quot;:1000,&quot;to&quot;:&quot;x:left(R);&quot;,&quot;ease&quot;:&quot;Power3.easeIn&quot;}]" style="z-index: 7; white-space: nowrap;text-transform:left;">Baking your ideas to life</div>
                <div class="tp-caption ps-banner__description" id="layer05" data-x="['center','center','center','center']" data-hoffset="['0','0','0','0']" data-y="['middle','middle','middle','middle']" data-voffset="['0','0','0','0']" data-type="text" data-responsive_offset="on" data-textAlign="['center','center','center','center']" data-frames="[{&quot;from&quot;:&quot;z:0;rX:0;rY:0;rZ:0;sX:0.9;sY:0.9;skX:0;skY:0;opacity:0;&quot;,&quot;speed&quot;:1500,&quot;to&quot;:&quot;o:1;&quot;,&quot;delay&quot;:1500,&quot;ease&quot;:&quot;Power3.easeInOut&quot;},{&quot;delay&quot;:&quot;wait&quot;,&quot;speed&quot;:1000,&quot;to&quot;:&quot;x:left(R);&quot;,&quot;ease&quot;:&quot;Power3.easeIn&quot;}]">Carrot cake macaroon topping jelly-o dessert cake. Tiramisu gummies wafer brownie tiramisu cake. <br/> Icing powder candy canes cotton candy pie liquorice.</div><a class="tp-caption ps-btn ps-btn--lg" href="#" id="layer06" data-x="['center','center','center','center']" data-hoffset="['0','0','0','0']" data-y="['middle','middle','middle','middle']" data-voffset="['80','70','70','70']" data-type="text" data-responsive_offset="on" data-textAlign="['center','center','center','center']" data-frames="[{&quot;from&quot;:&quot;z:0;rX:0;rY:0;rZ:0;sX:0.9;sY:0.9;skX:0;skY:0;opacity:0;&quot;,&quot;speed&quot;:1500,&quot;to&quot;:&quot;o:1;&quot;,&quot;delay&quot;:1500,&quot;ease&quot;:&quot;Power3.easeInOut&quot;},{&quot;delay&quot;:&quot;wait&quot;,&quot;speed&quot;:1000,&quot;to&quot;:&quot;x:left(R);&quot;,&quot;ease&quot;:&quot;Power3.easeIn&quot;}]">TRY OUR CUPCAKES <i class="fa fa-angle-right"></i></a>
              </li>
              <li data-index="rs-2972" data-transition="slidingoverlayhorizontal" data-slotamount="default" data-hideafterloop="0" data-hideslideonmobile="off" data-easein="default" data-easeout="default" data-masterspeed="default" data-thumb="{{ asset('assets/images/banner/img-slider-4.jpg') }}" data-rotate="0" data-saveperformance="off" data-title="Web Show" data-param1="" data-param2="" data-param3="" data-param4="" data-param5="" data-param6="" data-param7="" data-param8="" data-param9="" data-param10="" data-description=""><img class="rev-slidebg" src="{{ asset('assets/images/banner/img-slider-4.jpg') }}" alt="" data-bgposition="center center" data-bgfit="cover" data-bgrepeat="no-repeat" data-bgparallax="5" data-no-retina>
                <div class="tp-caption ps-banner__caption" id="layer07" data-x="['center','center','center','center']" data-hoffset="['0','0','0','0']" data-y="['middle','middle','middle','middle']" data-voffset="['-100','-80','-120','-120']" data-width="['none','none','none','400']" data-whitespace="['nowrap','nowrap','nowrap','normal']" data-type="text" data-responsive_offset="on" data-frames="[{&quot;from&quot;:&quot;z:0;rX:0;rY:0;rZ:0;sX:0.9;sY:0.9;skX:0;skY:0;opacity:0;&quot;,&quot;speed&quot;:1500,&quot;to&quot;:&quot;o:1;&quot;,&quot;delay&quot;:1700,&quot;ease&quot;:&quot;Power3.easeInOut&quot;},{&quot;delay&quot;:&quot;wait&quot;,&quot;speed&quot;:1000,&quot;to&quot;:&quot;x:left(R);&quot;,&quot;ease&quot;:&quot;Power3.easeIn&quot;}]" style="z-index: 7; white-space: nowrap;text-transform:left;">Art of Bake</div>
                <div class="tp-caption ps-banner__description" id="layer08" data-x="['center','center','center','center']" data-hoffset="['0','0','0','0']" data-y="['middle','middle','middle','middle']" data-voffset="['0','0','0','0']" data-type="text" data-responsive_offset="on" data-textAlign="['center','center','center','center']" data-frames="[{&quot;from&quot;:&quot;z:0;rX:0;rY:0;rZ:0;sX:0.9;sY:0.9;skX:0;skY:0;opacity:0;&quot;,&quot;speed&quot;:1500,&quot;to&quot;:&quot;o:1;&quot;,&quot;delay&quot;:1500,&quot;ease&quot;:&quot;Power3.easeInOut&quot;},{&quot;delay&quot;:&quot;wait&quot;,&quot;speed&quot;:1000,&quot;to&quot;:&quot;x:left(R);&quot;,&quot;ease&quot;:&quot;Power3.easeIn&quot;}]">Carrot cake macaroon topping jelly-o dessert cake. Tiramisu gummies wafer brownie tiramisu cake. <br/> Icing powder candy canes cotton candy pie liquorice.</div><a class="tp-caption ps-btn ps-btn--lg" href="#" id="layer09" data-x="['center','center','center','center']" data-hoffset="['0','0','0','0']" data-y="['middle','middle','middle','middle']" data-voffset="['80','70','70','70']" data-type="text" data-responsive_offset="on" data-textAlign="['center','center','center','center']" data-frames="[{&quot;from&quot;:&quot;z:0;rX:0;rY:0;rZ:0;sX:0.9;sY:0.9;skX:0;skY:0;opacity:0;&quot;,&quot;speed&quot;:1500,&quot;to&quot;:&quot;o:1;&quot;,&quot;delay&quot;:1500,&quot;ease&quot;:&quot;Power3.easeInOut&quot;},{&quot;delay&quot;:&quot;wait&quot;,&quot;speed&quot;:1000,&quot;to&quot;:&quot;x:left(R);&quot;,&quot;ease&quot;:&quot;Power3.easeIn&quot;}]">TRY OUR CUPCAKES <i class="fa fa-angle-right"></i></a>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <section class="ps-section ps-section--offer pt-80 pb-100">
        <div class="container">
          <div class="ps-section__header text-center mb-100">
            <h4 class="ps-section__top">Making People Happy</h4>
            <h3 class="ps-section__title ps-section__title--full">Best Cupcakes</h3>
          </div>
          <div class="ps-section__content">
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-product">
                    <div class="ps-badge"><span>-50%</span></div>
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-13.jpg') }}" alt="">
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
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-product">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-14.jpg') }}" alt="">
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
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-product">
                    <div class="ps-badge"><span>-50%</span></div>
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-21.jpg') }}" alt="">
                      <ul class="ps-product__action">
                        <li><a class="popup-modal" href="#quickview-modal" data-effect="mfp-zoom-out" data-tooltip="View"><i class="ps-icon--search"></i></a></li>
                        <li><a href="#" data-tooltip="Add to wishlist"><i class="ps-icon--heart"></i></a></li>
                        <li><a href="#" data-tooltip="Compare"><i class="ps-icon--reload"></i></a></li>
                        <li><a href="#" data-tooltip="Add to cart"><i class="ps-icon--shopping-cart"></i></a></li>
                      </ul>
                    </div>
                    <div class="ps-product__content"><a class="ps-product__title" href="{{ route('theme.product-detail') }}">Cupcake Queen 2</a>
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
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-product">
                    <div class="ps-badge ps-badge--new"><span>New</span></div>
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-15.jpg') }}" alt="">
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
                </div>
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-product">
                    <div class="ps-badge"><span>-50%</span></div>
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-16.jpg') }}" alt="">
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
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-product">
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-17.jpg') }}" alt="">
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
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-product">
                    <div class="ps-badge"><span>-50%</span></div>
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-18.jpg') }}" alt="">
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
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12 ">
                  <div class="ps-product">
                    <div class="ps-badge ps-badge--new"><span>New</span></div>
                    <div class="ps-product__thumbnail"><a class="ps-product__overlay" href="{{ route('theme.product-detail') }}"></a><img src="{{ asset('assets/images/cake/img-cake-20.jpg') }}" alt="">
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
      <div class="ps-section ps-section--pattern pt-80 pb-80">
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
                      <ul class="ps-countdown__time" data-time="April 21, 2018 15:37:25">
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
      <div class="ps-section pt-80 pb-40">
        <div class="container">
          <div class="ps-section__header mb-50 text-center">
            <div class="ps-section__top">With Bread All Sorrows Are Less.</div>
            <h3 class="ps-section__title ps-section__title--full">Special Bakery</h3>
          </div>
          <div class="ps-section__content">
            <div class="ps-product-special ps-product-special--2">
              <div class="row">
                    <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12 ">
                      <div class="slick ps-cake-list">
                        <div class="slick-item"><a class="ps-cake" href="javascript:;"><img src="{{ asset('assets/images/special/img-cr-1.jpg') }}" alt=""></a></div>
                        <div class="slick-item"><a class="ps-cake" href="javascript:;"><img src="{{ asset('assets/images/special/img-cr-2.jpg') }}" alt=""></a></div>
                        <div class="slick-item"><a class="ps-cake" href="javascript:;"><img src="{{ asset('assets/images/special/img-cr-3.jpg') }}" alt=""></a></div>
                        <div class="slick-item"><a class="ps-cake" href="javascript:;"><img src="{{ asset('assets/images/special/img-cr-4.jpg') }}" alt=""></a></div>
                        <div class="slick-item"><a class="ps-cake" href="javascript:;"><img src="{{ asset('assets/images/special/img-cr-5.jpg') }}" alt=""></a></div>
                      </div>
                    </div>
                    <div class="col-lg-9 col-md-9 col-sm-12 col-xs-12 ">
                      <div class="slick ps-cake-detail">
                        <div class="ps-cake--detail ps-cake--detail--2">
                          <div class="ps-cake__thumbnail"><img src="{{ asset('assets/images/special/small01.jpg') }}" alt="">
                            <div class="ps-badge ps-badge--large"><span>HOT</span></div>
                          </div>
                          <div class="ps-cake__content">
                            <h3>Vanilla cupcake with red sugar flowers</h3>
                            <p>INGREDIENTS:</p>
                            <p>_ 45 grams plain flour.</p>
                            <p>_ 1 teaspoon cocoa powder.</p>
                            <p>_ 1 egg. _ 10 grams melted butter.</p>
                            <p>_ 100 millilitres milk.</p>
                            <p>PREPARATION:</p>
                            <p>Pie gummi bears jujubes cake lemon drops gummi bears croissant macaroon pie. Fruitcake tootsie roll chocolate cake. Carrot cake cake bear claw jujubes topping cake apple pie. Jujubes…</p>
                            <p class="ps-cake__total mb-30">Only Today:<span>£5.99</span></p><a class="ps-btn ps-btn--sm" href="#">Order now<i class="fa fa-angle-right"></i></a>
                          </div>
                        </div>
                        <div class="ps-cake--detail ps-cake--detail--2">
                          <div class="ps-cake__thumbnail"><img src="{{ asset('assets/images/special/small02.jpg') }}" alt="">
                          </div>
                          <div class="ps-cake__content">
                            <h3>Vanilla cupcake with red sugar flowers</h3>
                            <p>INGREDIENTS:</p>
                            <p>_ 45 grams plain flour.</p>
                            <p>_ 1 teaspoon cocoa powder.</p>
                            <p>_ 1 egg. _ 10 grams melted butter.</p>
                            <p>_ 100 millilitres milk.</p>
                            <p>PREPARATION:</p>
                            <p>Pie gummi bears jujubes cake lemon drops gummi bears croissant macaroon pie. Fruitcake tootsie roll chocolate cake. Carrot cake cake bear claw jujubes topping cake apple pie. Jujubes…</p>
                            <p class="ps-cake__total mb-30">Only Today:<span>£5.99</span></p><a class="ps-btn ps-btn--sm" href="#">Order now<i class="fa fa-angle-right"></i></a>
                          </div>
                        </div>
                        <div class="ps-cake--detail ps-cake--detail--2">
                          <div class="ps-cake__thumbnail"><img src="{{ asset('assets/images/special/small03.jpg') }}" alt="">
                            <div class="ps-badge ps-badge--large"><span>HOT</span></div>
                          </div>
                          <div class="ps-cake__content">
                            <h3>Vanilla cupcake with red sugar flowers</h3>
                            <p>INGREDIENTS:</p>
                            <p>_ 45 grams plain flour.</p>
                            <p>_ 1 teaspoon cocoa powder.</p>
                            <p>_ 1 egg. _ 10 grams melted butter.</p>
                            <p>_ 100 millilitres milk.</p>
                            <p>PREPARATION:</p>
                            <p>Pie gummi bears jujubes cake lemon drops gummi bears croissant macaroon pie. Fruitcake tootsie roll chocolate cake. Carrot cake cake bear claw jujubes topping cake apple pie. Jujubes…</p>
                            <p class="ps-cake__total mb-30">Only Today:<span>£5.99</span></p><a class="ps-btn ps-btn--sm" href="#">Order now<i class="fa fa-angle-right"></i></a>
                          </div>
                        </div>
                        <div class="ps-cake--detail ps-cake--detail--2">
                          <div class="ps-cake__thumbnail"><img src="{{ asset('assets/images/special/small04.jpg') }}" alt="">
                          </div>
                          <div class="ps-cake__content">
                            <h3>Vanilla cupcake with red sugar flowers</h3>
                            <p>INGREDIENTS:</p>
                            <p>_ 45 grams plain flour.</p>
                            <p>_ 1 teaspoon cocoa powder.</p>
                            <p>_ 1 egg. _ 10 grams melted butter.</p>
                            <p>_ 100 millilitres milk.</p>
                            <p>PREPARATION:</p>
                            <p>Pie gummi bears jujubes cake lemon drops gummi bears croissant macaroon pie. Fruitcake tootsie roll chocolate cake. Carrot cake cake bear claw jujubes topping cake apple pie. Jujubes…</p>
                            <p class="ps-cake__total mb-30">Only Today:<span>£5.99</span></p><a class="ps-btn ps-btn--sm" href="#">Order now<i class="fa fa-angle-right"></i></a>
                          </div>
                        </div>
                        <div class="ps-cake--detail ps-cake--detail--2">
                          <div class="ps-cake__thumbnail"><img src="{{ asset('assets/images/special/small05.jpg') }}" alt="">
                            <div class="ps-badge ps-badge--large"><span>HOT</span></div>
                          </div>
                          <div class="ps-cake__content">
                            <h3>Vanilla cupcake with red sugar flowers</h3>
                            <p>INGREDIENTS:</p>
                            <p>_ 45 grams plain flour.</p>
                            <p>_ 1 teaspoon cocoa powder.</p>
                            <p>_ 1 egg. _ 10 grams melted butter.</p>
                            <p>_ 100 millilitres milk.</p>
                            <p>PREPARATION:</p>
                            <p>Pie gummi bears jujubes cake lemon drops gummi bears croissant macaroon pie. Fruitcake tootsie roll chocolate cake. Carrot cake cake bear claw jujubes topping cake apple pie. Jujubes…</p>
                            <p class="ps-cake__total mb-30">Only Today:<span>£5.99</span></p><a class="ps-btn ps-btn--sm" href="#">Order now<i class="fa fa-angle-right"></i></a>
                          </div>
                        </div>
                      </div>
                    </div>
              </div>
            </div>
          </div>
        </div>
      </div>
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
      <div class="ps-section ps-section--partner">
        <div class="container">
          <div class="owl-slider" data-owl-auto="true" data-owl-loop="true" data-owl-speed="10000" data-owl-gap="40" data-owl-nav="false" data-owl-dots="false" data-owl-animate-in="" data-owl-animate-out="" data-owl-item="6" data-owl-item-xs="3" data-owl-item-sm="4" data-owl-item-md="5" data-owl-item-lg="6" data-owl-nav-left="&lt;i class=&quot;fa fa-angle-left&quot;&gt;&lt;/i&gt;" data-owl-nav-right="&lt;i class=&quot;fa fa-angle-right&quot;&gt;&lt;/i&gt;"><a href="#"><img src="{{ asset('assets/images/partner/1.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/2.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/3.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/4.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/5.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/6.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/7.png') }}" alt=""></a><a href="#"><img src="{{ asset('assets/images/partner/8.png') }}" alt=""></a>
          </div>
        </div>
      </div>
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
