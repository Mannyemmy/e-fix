<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<title>{{env('APP_NAME')}} — On-Demand Home Services</title>
@if (env('UI_LOCAL_MODE', false))
<link rel="shortcut icon" class="favicon_preview" href="{{ asset('images/favicon.png') }}" />
@else
<link rel="shortcut icon" class="favicon_preview" href="{{ getSingleMedia(imageSession('get'),'favicon',null) }}" />
@endif
{{-- Swiper — local copy (replaced broken external innoquad.in link) --}}
<link rel="stylesheet" href="{{ asset('vendor/swiper/swiper-bundle.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/landing-page.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/landing-page-rtl.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/landing-page-custom.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/@fortawesome/fontawesome-free/css/all.min.css')}}">
{{-- Modern override layer — must be last --}}
<link rel="stylesheet" href="{{ asset('css/efix-modern.css') }}">
{{-- Google Fonts preconnect for performance --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<meta name="csrf-token" content="{{ csrf_token() }}">

<meta name="assert_url" content="{{ URL::to('') }}" />

<meta name="baseUrl" content="{{env('APP_URL')}}" />
@php
        $currentLang = app()->getLocale();
        $langFolderPath = resource_path("lang/$currentLang");
        $filePaths = \File::files($langFolderPath);
    @endphp

    @foreach ($filePaths as $filePath)
        @php
            $fileName = pathinfo($filePath, PATHINFO_FILENAME);
        @endphp
        <script>
            window.localMessagesUpdate = {
                ...window.localMessagesUpdate,
                "{{ $fileName }}": @json(require($filePath))
            };
        </script>
    @endforeach





