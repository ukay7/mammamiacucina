<!DOCTYPE html>
<html lang="en">
<head>
@include('partials.head')
@stack('styles')

@include('partials.pwa')
@include('partials.theme-colors',['themeScope'=>'website'])
</head>
<body class="@yield('body-class', 'page-init')">
@yield('content')
@include('partials.scripts')
@stack('scripts')
</body>
</html>
