@props([
    'src',
    'alt' => '',
    'class' => null,
    'sizes' => '(max-width: 640px) 45vw, 180px',
    'loading' => 'lazy',
    'fetchpriority' => null,
    'decoding' => 'async',
    'widths' => [],
])

@php
    $srcset = \App\Support\ResponsiveImage::srcset($src, $widths);
@endphp

<img
    src="{{ $src }}"
    alt="{{ $alt }}"
    @if($class) class="{{ $class }}" @endif
    @if($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
    loading="{{ $loading }}"
    decoding="{{ $decoding }}"
    @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
    {{ $attributes }}
>
