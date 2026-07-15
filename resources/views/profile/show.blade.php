@extends('layouts.app')

@section('content')
    <section class="profile-public">
        <div class="profile-avatar large">
            @if($user->avatar_path)
                <img src="{{ asset('storage/'.$user->avatar_path) }}" alt="{{ $user->name }}">
            @else
                <span>{{ mb_substr($user->name, 0, 1) }}</span>
            @endif
        </div>
        <div>
            <p class="eyebrow">nozu.me profili</p>
            <h1>{{ $user->name }}</h1>
            <p class="muted">{{ '@'.$user->username }}</p>
            @if($user->bio)
                <p>{{ $user->bio }}</p>
            @endif
        </div>
    </section>
@endsection
