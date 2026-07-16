@extends('layouts.app')

@section('content')
    <section class="directory-hero">
        <p class="eyebrow">{{ '@'.$user->username }}</p>
        <h1>{{ $title }}</h1>
    </section>

    <div class="directory-grid">
        @forelse($people as $person)
            <a class="directory-card" href="{{ route('profile.show', $person->username) }}">
                <div class="directory-avatar">
                    @if($person->avatar_path)
                        <img src="{{ asset('storage/'.$person->avatar_path) }}" alt="{{ $person->username }}">
                    @else
                        <span>{{ mb_substr($person->username ?: 'N', 0, 1) }}</span>
                    @endif
                </div>
                <strong>{{ '@'.$person->username }}</strong>
            </a>
        @empty
            <p class="muted">Henüz kayıt yok.</p>
        @endforelse
    </div>

    {{ $people->links() }}
@endsection
