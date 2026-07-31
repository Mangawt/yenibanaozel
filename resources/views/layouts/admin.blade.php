<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Nozu CMS')</title>
    <link rel="preload" href="{{ asset('vendor/manrope/manrope-1.ttf') }}" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="{{ asset('vendor/manrope/manrope.css') }}?v={{ @filemtime(public_path('vendor/manrope/manrope.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}?v={{ @filemtime(public_path('vendor/fontawesome/css/all.min.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('admin.css') }}?v={{ @filemtime(public_path('admin.css')) ?: time() }}">
</head>
<body>
    <main class="admin-page">
        @if(session('status'))
            <div class="notice success">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="notice error">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
