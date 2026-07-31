@props([
    'src' => null,
    'alt' => '',
    'loading' => 'lazy',
    'decoding' => 'async',
    'width' => null,
    'height' => null,
    'sizes' => '100vw',
    'widths' => [],
])

@php
    $srcset = \App\Support\ResponsiveImage::srcset($src, $widths);
@endphp

@if ($src)
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        loading="{{ $loading }}"
        decoding="{{ $decoding }}"
        @if ($width) width="{{ $width }}" @endif
        @if ($height) height="{{ $height }}" @endif
        @if ($srcset)
            srcset="{{ $srcset }}"
            sizes="{{ $sizes }}"
        @endif
        {{ $attributes }}
    >
@endif
