<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Nozu CMS')</title>
    <link rel="stylesheet" href="{{ asset('admin.css') }}">
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
