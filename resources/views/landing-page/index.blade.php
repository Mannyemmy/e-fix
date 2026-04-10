@extends('landing-page.layouts.default')
@section('content')

<!-- Banner -->
<div class="padding-top-bottom-90 bg-light">
    <div class="container-fluid">
        @if (env('UI_LOCAL_MODE', false) && empty($sectionData['section_1']))
        <div class="row justify-content-center text-center mb-5">
            <div class="col-xl-8 col-lg-10">
                <div class="iq-title-box mb-0">
                    <h1 class="text-capitalize">UI Local Mode</h1>
                    <p class="iq-title-desc text-body mt-3 mb-0">
                        Database-backed homepage sections are currently bypassed, so this local scaffold is shown for UI work.
                        Edit this view and related styles freely, then switch off UI local mode when you reconnect the live DB.
                    </p>
                </div>
            </div>
        </div>
        @endif
       <div class="row align-items-center">
          <div class="col-xl-6">
             <div class="me-0 pe-0 me-xl-5 pe-xl-5">
               @if ($sectionData && isset($sectionData['section_1']) && $sectionData['section_1']['section_1'] == 1)
                <div class="iq-title-box mb-5">
                    <div class="iq-title-box">
                        <h2 class="text-capitalize line-count-3">
                            {{ $sectionData['section_1']['title']}}
                            <!-- Your Instant Connection to Right -->
                            <span class="highlighted-text">
                            <span class="highlighted-text-swipe"></span>
                            <span class="highlighted-image">
                                <svg xmlns="http://www.w3.org/2000/svg" width="254" height="11" viewBox="0 0 254 11" fill="none">
                                <path
                                    d="M2 9C3.11607 8.76081 129.232 -2.95948 252 4.4554"
                                    stroke="currentColor"
                                    stroke-width="4"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                </svg>
                            </span>
                            </span>
                        </h2>
                        <p class="iq-title-desc line-count-3 text-body mt-3 mb-0">
                            {{ $sectionData['section_1']['description']}}
                        </p>
                    </div>
                </div>
                <location-search :user_id="{{json_encode($auth_user_id)}}" :postjobservice="{{$postjobservice}}"></location-search>
               @endif

             </div>
          </div>

          <div class="col-xl-6 px-xl-0 mt-xl-0 mt-5 d-flex align-items-center justify-content-center">
            <div class="hero-static-image">
              <img src="{{ asset('landing-images/service/service.webp') }}"
                   alt="Professional handyman at work"
                   class="img-fluid hero-img-static">
            </div>
          </div>
       </div>
    </div>
</div>

<!-- Categories -->
@if ($sectionData && isset($sectionData['section_2']) && $sectionData['section_2']['section_2'] == 1)
<div class="section-padding">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="iq-title-box mb-0">
                <h3 class="text-capitalize line-count-1">{{ $sectionData['section_2']['title'] }}
                    <div class="highlighted-text">
                        <span class="highlighted-text-swipe"></span>
                        <span class="highlighted-image">
                            <svg xmlns="http://www.w3.org/2000/svg" width="155" height="12" viewBox="0 0 155 12"
                                fill="none">
                                <path d="M2.5 9.5C3.16964 9.26081 78.8393 -2.45948 152.5 4.9554" stroke="currentColor"
                                    stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                </h3>
            </div>
            <a href="{{ route('category.list') }}"
                class="btn btn-link p-0 text-capitalize flex-shrink-0 font-size-14">{{__('messages.view_all')}}</a>
        </div>
        <category-section></category-section>
    </div>
</div>
@endif


<!-- Service -->
 <div class="section-padding bg-light our-service">
    <div class="container">
        @if ($sectionData && isset($sectionData['section_3']) && $sectionData['section_3']['section_3'] == 1)
            <div>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="iq-title-box mb-0">
                    <h3 class="text-capitalize line-count-1">{{ $sectionData['section_3']['title'] }}
                    <div class="highlighted-text">
                        <span class="highlighted-text-swipe"></span>
                        <span class="highlighted-image">
                            <svg xmlns="http://www.w3.org/2000/svg" width="155" height="12" viewBox="0 0 155 12"
                                fill="none">
                                <path d="M2.5 9.5C3.16964 9.26081 78.8393 -2.45948 152.5 4.9554" stroke="currentColor"
                                stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                    </h3>
                </div>
                <a href="{{ route('service.list') }}" class="btn btn-link p-0 flex-shrink-0">{{__('messages.view_all')}}</a>
            </div>

            <service-slider-section :user_id="{{json_encode($auth_user_id)}}" :favourite="{{json_encode($favourite)}}" :type="'ac'"/>
            </div>
        @endif

        @if ($sectionData && isset($sectionData['section_4']) && $sectionData['section_4']['section_4'] == 1)
            <div class="mt-5">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="iq-title-box mb-0">
                    <h3 class="text-capitalize line-count-1">{{ $sectionData['section_4']['title'] }}
                        <div class="highlighted-text"><div class="swiper-pagination"></div>
                            <span class="highlighted-text-swipe"></span>
                            <span class="highlighted-image">
                                <svg xmlns="http://www.w3.org/2000/svg" width="155" height="12" viewBox="0 0 155 12"
                                fill="none">
                                <path d="M2.5 9.5C3.16964 9.26081 78.8393 -2.45948 152.5 4.9554" stroke="currentColor"
                                    stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                    </h3>
                    </div>
                    <a href="{{ route('service.list') }}" class="btn btn-link p-0 flex-shrink-0">{{__('messages.view_all')}}</a>
                </div>
                <service-slider-section :user_id="{{json_encode($auth_user_id)}}" :favourite="{{json_encode($favourite)}}" :type="'cleaning'"/>
            </div>
        @endif
    </div>
</div>

@if($auth_user_id)
<!-- Recently Viewed Service -->
    @if ($sectionData && isset($sectionData['section_8']) && $sectionData['section_8']['section_8'] == 1)
    @php
        $recentlyViewed = session()->get('recently_viewed:' . $auth_user_id, []);
        session(['recently_viewed:' . $auth_user_id => $recentlyViewed]);
    @endphp
        @if (!empty($recentlyViewed))
            <div class="section-padding">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-2 col-md-none"></div>
                        <div class="col-lg-8 col-md-12">
                            <div class="iq-title-box text-center center">
                                <h3 class="text-capitalize line-count-1">{{ $sectionData['title'] }}
                                <span class="highlighted-text">
                                    <span class="highlighted-text-swipe"></span>
                                    <span class="highlighted-image">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="130" height="11" viewBox="0 0 130 11"
                                            fill="none">
                                            <path d="M2 9C2.5625 8.76081 66.125 -2.95948 128 4.4554" stroke="currentColor"
                                            stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </span>
                                </h3>
                                <p class="iq-title-desc line-count-3 text-body mt-3 mb-0">{{ $sectionData['section_8']['description'] }}</p>

                            </div>
                        </div>
                        <div class="col-lg-2 col-md-none"></div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                        <service-slider-section :user_id="{{json_encode($auth_user_id)}}" :favourite="{{json_encode($favourite)}}" :type="'recently_view'"/>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
@endif

<!-- Provider -->

@if ($sectionData && isset($sectionData['section_5']) && $sectionData['section_5']['section_5'] == 1)
<div class="bg-primary-subtle overflow-hidden">
    <div class="container provider-section position-relative">
        @php
            $images = Spatie\MediaLibrary\MediaCollections\Models\Media::where('collection_name', 'section5_attachment')->get();
            $section5Email = $sectionData['section_5']['email'] ?? 'info@efix.ng';
            $section5Phone = $sectionData['section_5']['contact_number'] ?? '07043658330';

            if ($section5Email === 'info@examples.com') {
                $section5Email = 'info@efix.ng';
            }

            if ($section5Phone === '+1(000)-235-7894') {
                $section5Phone = '07043658330';
            }
        @endphp

        @if(isset($images[0]))
            <img src="{{ $images[0]->getUrl() }}" alt="service" class="img-fluid position-absolute provider provider-1">
        @else
            <img src="{{asset("landing-images/service/1.webp")}}" alt="service" class="img-fluid position-absolute provider provider-1">
        @endif

        @if(isset($images[1]))
            <img src="{{ $images[1]->getUrl() }}" alt="service" class="img-fluid position-absolute provider provider-6">
        @else
            <img src="{{asset("landing-images/service/2.webp")}}" alt="service" class="img-fluid position-absolute provider provider-6">
        @endif

        <div class="row align-items-center">
            <div class="col-md-2"></div>
                <div class="col-lg-8 col-md-12">
                    <div class="iq-title-box mb-5 text-center px-3">
                            <h2 class="text-capitalize line-count-2">{{ $sectionData['section_5']['title'] }}</h2>
                            <p class="iq-title-desc line-count-3 text-body mt-3 mb-0">{{ $sectionData['section_5']['description'] }}</p>
                    </div>
                    <div class="text-center d-flex justify-content-center align-items-center pt-3 flex-column flex-md-row px-3">
                            <a class="bg-primary py-3 px-5 fw-bolder text-white rounded-3 letter-spacing-64"
                                href="mailto:{{ $section5Email }}">{{ $section5Email }}</a>
                            <span class="px-3">Or</span>
                            <a href="tel:{{ $section5Phone }}">
                                <h6 class="text-decoration-underline">{{ $section5Phone }}</h6>
                            </a>
                    </div>
                </div>
            <div class="col-md-2"></div>
        </div>

        @if(isset($images[2]))
        <img src="{{ $images[2]->getUrl() }}" alt="service" class="img-fluid position-absolute provider provider-5">
        @else
        <img src="{{asset("landing-images/service/5.webp")}}" alt="service" class="img-fluid position-absolute provider provider-5">
        @endif

        @if(isset($images[3]))
        <img src="{{ $images[3]->getUrl() }}" alt="service" class="img-fluid position-absolute provider provider-3">
        @else
        <img src="{{asset("landing-images/service/3.webp")}}" alt="service" class="img-fluid position-absolute provider provider-3">
        @endif

        @if(isset($images[4]))
        <img src="{{ $images[4]->getUrl() }}" alt="service" class="img-fluid position-absolute provider provider-4">
        @else
        <img src="{{asset("landing-images/service/4.webp")}}" alt="service" class="img-fluid position-absolute provider provider-4">
        @endif
    </div>
</div>
@endif

@if ($sectionData && isset($sectionData['section_9']) && $sectionData['section_9']['section_9'] == 1)
<div class="section-padding bg-light px-0">
    <div class="container-fluid px-xxl-3">
       <div class="row">
            <div class="col-12">
                <div class="iq-title-box text-center center mb-2">
                    <h3 class="text-capitalize line-count-1">{{ $sectionData['section_9']['title'] }}
                    <span class="highlighted-text">
                        <!-- <span class="highlighted-text-swipe">our trusted clients</span> -->
                        <span class="highlighted-image">
                            <svg xmlns="http://www.w3.org/2000/svg" width="130" height="11" viewBox="0 0 130 11"
                                fill="none">
                                <path d="M2 9C2.5625 8.76081 66.125 -2.95948 128 4.4554" stroke="currentColor"
                                stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </span>
                    </h3>
                </div>


                <div class="text-center mb-5">
                    <div class="d-inline-flex align-items-center flex-sm-row flex-column bg-body py-3 px-5 rounded-5 gap-2">
                        <div class="vertical-center lh-1">
                            <rating-component :readonly="true" :showrating="false" :ratingvalue="{{$totalRating}}" />
                            {{-- {{>components/widgets/filter-rating rating="4"}} --}}
                        </div>
                        @if (isset($sectionData['section_9']['overall_rating']) && $sectionData['section_9']['overall_rating'] == 'on')
                        <h5>{{ round($totalRating,1) }}</h5>
                        <h6>{{__('landingpage.overall_rating')}}</h6>
                        @endif
                    </div>
                    <h6 class="mt-4"> {{ $sectionData['section_9']['description'] }}</h6>
                </div>
            </div>
            <div class="col-12">
                <testimonial-section/>
            </div>
       </div>
    </div>
</div>
@endif

@if ($sectionData && isset($sectionData['section_6']) && $sectionData['section_6']['section_6'] == 1)
<div class="section-padding">
    <div class="container">
       <div class="row align-items-center">
          <div class="col-12">
             <div class="px-5 bg-primary rounded-3 position-relative overflow-hidden">
                <div class="position-absolute top-0 end-0">
                   <img src="{{asset('landing-images/general/pattern-bg-1.webp' )}}" alt="pattern" class="img-fluid">
                </div>
                <div class="px-xl-5">
                   <div class="px-xl-3">
                      <div class="row align-items-center">
                         <div class="col-lg-6 position-relative my-5">
                            <div class="iq-title-box">
                               <h2 class="text-capitalize text-white line-count-2">{{ $sectionData['section_6']['title'] }}</h2>
                               <p class="mt-3 mb-0 text-white line-count-3">{{ $sectionData['section_6']['description'] }}
                               </p>
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                              @php
                              $mediaGooglePay = Spatie\MediaLibrary\MediaCollections\Models\Media::where('collection_name', 'google_play')->first();
                              $mediaMainImage = Spatie\MediaLibrary\MediaCollections\Models\Media::where('collection_name','main_image')->first();
                                        $playStoreUrl = 'https://firebasestorage.googleapis.com/v0/b/e-fix-b5230.firebasestorage.app/o/E-Fix.apk?alt=media&token=8fbb5b33-c81d-404f-8d7d-5b9631c98724';
                                        $handymanAppUrl = 'https://firebasestorage.googleapis.com/v0/b/e-fix-b5230.firebasestorage.app/o/app-release.apk?alt=media&token=31efa1b8-f7fc-4dc4-84c9-b4932b755737';
                              @endphp
                                         <a href="{{ $playStoreUrl }}" download class="app-link" title="Download User App">
                                 @if($mediaGooglePay)
                                 <img src="{{ url('storage/' . $mediaGooglePay->id . '/' . $mediaGooglePay->file_name) }}" alt="googleplay" class="img-fluid">
                                 @else
                                  <img src="{{asset('landing-images/general/googleplay.webp')}}" alt="googleplay"
                                     class="img-fluid">
                                 @endif
                               </a>
                                         <a href="{{ $handymanAppUrl }}" download class="btn btn-light btn-sm fw-bold px-3 py-2" title="Download Handyman App">
                                             Download Handyman App
                                         </a>
                            </div>
                         </div>
                         <div class="col-lg-6 mt-lg-0 mt-5 position-relative align-self-end text-center">
                           @if($mediaMainImage)
                           <img src="{{ url('storage/' . $mediaMainImage->id . '/' . $mediaMainImage->file_name) }}" alt="phone" class="img-fluid">
                            @else
                            <img src="{{asset('landing-images/general/phone.webp')}}" alt="phone" class="img-fluid">
                            @endif
                         </div>
                      </div>
                   </div>
                </div>
             </div>
          </div>
       </div>
    </div>
 </div>
@endif

@if ($sectionData && isset($sectionData['section_7']) && $sectionData['section_7']['section_7'] == 1)
 <div class="section-padding pt-0" style="background: var(--efix-dark);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-5">
                <div class="iq-title-box mb-0">
                    <h3 class="text-capitalize line-count-2 text-white">{{ $sectionData['section_7']['title'] }}
                    <span class="highlighted-text">
                        <span class="highlighted-text-swipe"></span>
                        <span class="highlighted-image">
                            <svg xmlns="http://www.w3.org/2000/svg" width="164" height="12" viewBox="0 0 164 12"
                                fill="none">
                                <path d="M2 9.5C2.71429 9.26081 83.4286 -2.45948 162 4.9554" stroke="currentColor"
                                stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </span>
                    </h3>
                </div>
            </div>
            <div class="col-lg-7 mt-lg-0 mt-3">
                <p class="m-0 line-count-3 text-white opacity-75">{{ $sectionData['section_7']['description'] }}</p>
            </div>
        </div>
        @php
                $mediaVimage = Spatie\MediaLibrary\MediaCollections\Models\Media::where('collection_name', 'vimage')->first();
        @endphp

        <div class="row align-items-center mt-5 pt-lg-5">
            <div class="col-lg-6 pe-xl-5 position-relative">
                @if($mediaVimage)
                    <img src="{{ url('storage/' . $mediaVimage->id . '/' . $mediaVimage->file_name) }}" alt="video-popup" class="img-fluid w-100 rounded">
                @else
                    <img src="{{ asset('landing-images/general/popup.webp') }}" alt="video-popup" class="img-fluid w-100 rounded">
                @endif
                    @include('landing-page.components.widgets.video-popup', ['videoLinkUrl' => $sectionData['section_7']['url']])

            </div>
            <div class="col-lg-6 mt-lg-0 mt-5 ps-xl-5">
                @if(isset($sectionData['section_7']['subtitle']) && isset($sectionData['section_7']['subdescription']))
                    @for($i = 0; $i < min(count($sectionData['section_7']['subtitle']), count($sectionData['section_7']['subdescription'])); $i++)
                        <div class="mb-4 pb-4 border-bottom">
                            @include('landing-page.components.widgets.icon-box', [
                                'iconboxNumber' => $i + 1,
                                'iconboxTitle' => $sectionData['section_7']['subtitle'][$i],
                                'iconboxDescription' => $sectionData['section_7']['subdescription'][$i]
                            ])
                        </div>
                    @endfor
                @endif
            </div>
        </div>
    </div>
 </div>
@endif


@endsection
@section('bottom_script')
<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script>
   document.addEventListener("DOMContentLoaded", function () {
      var $sliders = jQuery(document).find('.iq-team-slider');
      if ($sliders.length > 0) {
         $sliders.each(function () {
            let slider = jQuery(this);
            var navNext = (slider.data('navnext')) ? "#" + slider.data('navnext') : "";
            var navPrev = (slider.data('navprev')) ? "#" + slider.data('navprev') : "";
            var pagination = (slider.data('pagination')) ? "#" + slider.data('pagination') : "";
            var sliderAutoplay = slider.data('autoplay');
            if (sliderAutoplay) {
            sliderAutoplay = {
               delay: slider.data('autoplay')
            };
            } else {
            sliderAutoplay = false;
            }
            var iqonicPagination = {
            el: pagination,
            clickable: true,
            dynamicBullets: true,
            };
            var swSpace = {
            1200: 30,
            1500: 30
            };
            var breakpoint = {
            0: {
               slidesPerView: 1,
               centeredSlides: false,
               virtualTranslate: false
            },
            576: {
               slidesPerView: 1,
               centeredSlides: false,
               virtualTranslate: false
            },
            768: {
               slidesPerView: 2,
               centeredSlides: false,
               virtualTranslate: false
            },
            1200: {
               slidesPerView: 3,
               spaceBetween: swSpace["1200"],
            },
            1500: {
               slidesPerView: 3,
               spaceBetween: swSpace["1500"],
            },
            };
            var sw_config = {
            loop: true,
            speed: 1000,
            loopedSlides: 3,
            spaceBetween: 30,
            slidesPerView: 3,
            centeredSlides: false,
            autoplay: true,
            virtualTranslate: false,
            navigation: {
               nextEl: navNext,
               prevEl: navPrev
            },
            on: {
               slideChangeTransitionStart: function () {
                  var currentElement = jQuery(this.el);
                  var lastBullet = currentElement.find(".swiper-pagination-bullet:last");
                  if (this.slides.length - (this.loopedSlides + 1) === this.activeIndex) {
                  lastBullet.addClass("js_prefix-disable-bullate");
                  } else {
                  lastBullet.removeClass("js_prefix-disable-bullate");
                  }
                  if (jQuery(window).width() > 1199) {
                  var innerTranslate = -(160 + swSpace[this.currentBreakpoint]) * (this.activeIndex);
                  currentElement.find(".swiper-wrapper").css({
                     "transform": "translate3d(" + innerTranslate + "px, 0, 0)"
                  });
                  currentElement.find('.swiper-slide:not(.swiper-slide-active)').css({
                     width: "160px"
                  });
                  currentElement.find('.swiper-slide.swiper-slide-active').css({
                     width: "476px"
                  });
                  }
               },
               resize: function () {
                  var currentElement = jQuery(this.el);
                  if (jQuery(window).width() > 1199) {
                  if (currentElement.data("loop")) {
                     var innerTranslate = -(160 + swSpace[this.currentBreakpoint]) * this.loopedSlides;
                     currentElement.find(".swiper-wrapper").css({
                        "transform": "translate3d(" + innerTranslate + "px, 0, 0)"
                     });
                  }
                  currentElement.find('.swiper-slide:not(.swiper-slide-active)').css({
                     width: "160px"
                  });
                  currentElement.find('.swiper-slide.swiper-slide-active').css({
                     width: "476px"
                  });
                  }
               },
               init: function () {
                  var currentElement = jQuery(this.el);
                  currentElement.find('.swiper-slide').css({
                  'max-width': 'auto'
                  });
               }
            },
            pagination: (slider.data('pagination')) ? iqonicPagination : "",
            breakpoints: breakpoint,
            };
            var swiper = new Swiper(slider[0], sw_config);
         });
         jQuery(document).trigger('after_slider_init');
      }
   });
</script>
@endsection
