@extends('layouts.app')

@section('content')
    <section class="person-hero">
        <div class="person-avatar">
            @if($person['image'])
                <img src="{{ $person['image'] }}" alt="{{ $person['name'] }}">
            @else
                <span>{{ mb_substr($person['name'], 0, 1) }}</span>
            @endif
        </div>
        <div>
            <p class="eyebrow">Sanatçı profili</p>
            <h1>{{ $person['name'] }}</h1>
            <p>{{ $credits->count() }} kayıtlı çalışma nozu.me arşivinde görünüyor.</p>
        </div>
    </section>

    <x-section-title title="Seslendirdiği ve katkı verdiği işler" />
    <div class="credit-list">
        @foreach($credits as $credit)
            <article class="credit-card">
                <a class="credit-cover" href="{{ route('media.show', ['type' => $credit['media']->type, 'media' => $credit['media']]) }}">
                    @if($credit['media']->cover_image)
                        <img src="{{ $credit['media']->cover_image }}" alt="{{ $credit['media']->title }}">
                    @endif
                </a>
                <div>
                    <span>{{ $credit['kind'] }}</span>
                    <h2><a href="{{ route('media.show', ['type' => $credit['media']->type, 'media' => $credit['media']]) }}">{{ $credit['media']->title }}</a></h2>
                    @if($credit['role'])
                        <p>{{ $credit['role'] }}</p>
                    @endif
                </div>
                @if($credit['image'])
                    <img class="credit-role-image" src="{{ $credit['image'] }}" alt="">
                @endif
            </article>
        @endforeach
    </div>
@endsection
