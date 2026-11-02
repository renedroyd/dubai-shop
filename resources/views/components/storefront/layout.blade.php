@props([
    'title' => 'Clásico Dubai | Compra con estilo',
    'description' => 'Descubre una selección especial de productos en Clásico Dubai.',
    'canonical' => null,
    'ogImage' => null,
    'ogType' => 'website',
    'robots' => 'index,follow',
])

@php($storeSettings = app(\App\Services\Storefront\StorefrontSettings::class)->all())
@php($storeName = $storeSettings['site_name'] ?? config('app.name', 'Clásico Dubai'))
@php($canonicalUrl = $canonical ?: request()->url())
@php($headerMenu = \App\Models\StorefrontMenuItem::query()->where('location', 'header')->whereNull('parent_id')->where('is_active', true)->with('children')->orderBy('sort_order')->get())
@php($footerMenu = \App\Models\StorefrontMenuItem::query()->where('location', 'footer')->whereNull('parent_id')->where('is_active', true)->with('children')->orderBy('sort_order')->get())

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B0B0B">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage) }}">
        <meta name="twitter:image" content="{{ str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage) }}">
    @endif
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="storefront-body">
    <a class="skip-link" href="#main-content">Saltar al contenido</a>

    <div class="announcement-bar">
        <span class="announcement-bar__sparkle" aria-hidden="true">✦</span>
        <p>{{ $storeSettings['announcement_text'] ?? 'Una selección especial. Una experiencia diferente.' }}</p>
        <span class="announcement-bar__sparkle" aria-hidden="true">✦</span>
    </div>

    <header class="site-header">
        <div class="site-header__inner">
            <a href="{{ url('/') }}" class="brand" aria-label="{{ $storeName }}, inicio">
                <img class="brand__logo" src="{{ asset('images/brand/clasico-dubai.svg') }}" alt="{{ $storeName }}">
            </a>

            <nav class="desktop-nav" aria-label="Navegación principal">
                @if ($headerMenu->isNotEmpty())
                    @foreach ($headerMenu as $item)
                        <a href="{{ $item->url }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
                        @foreach ($item->children as $child)
                            <a href="{{ $child->url }}" style="font-size:.85em;padding-left:1rem" @if ($child->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $child->label }}</a>
                        @endforeach
                    @endforeach
                @else
                    <a href="{{ route('products.index') }}">Productos</a>
                    <a href="{{ route('home') }}#colecciones">Colecciones</a>
                    <a href="{{ route('home') }}#experiencia">Nuestra esencia</a>
                    <a href="{{ route('home') }}#beneficios">Por qué elegirnos</a>
                @endif
            </nav>

            <div class="site-header__actions">
                @auth
                    <a class="header-link" href="{{ route('customer.dashboard') }}">Mi cuenta</a>
                @else
                    <a class="header-link" href="{{ route('customer.login') }}">Ingresar</a>
                @endauth
                <a class="header-link" href="{{ route('cart.index') }}">Carrito</a>
                <a class="header-link" href="{{ route('products.index') }}"><x-storefront.icon name="search" :size="18" /><span>Explorar</span></a>
                <a class="header-cta" href="{{ route('products.index') }}">Ver productos <x-storefront.icon name="arrow-up-right" :size="16" /></a>
            </div>

            <details class="mobile-menu">
                <summary aria-label="Abrir menú de navegación"><x-storefront.icon name="menu" :size="22" /></summary>
                <nav aria-label="Navegación móvil">
                    @if ($headerMenu->isNotEmpty())
                        @foreach ($headerMenu as $item)
                            <a href="{{ $item->url }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
                            @foreach ($item->children as $child)
                                <a href="{{ $child->url }}" style="padding-left:1rem" @if ($child->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $child->label }}</a>
                            @endforeach
                        @endforeach
                    @else
                        <a href="{{ route('products.index') }}">Productos</a>
                        <a href="{{ route('cart.index') }}">Carrito</a>
                        <a href="{{ route('home') }}#colecciones">Colecciones</a>
                        <a href="{{ route('home') }}#experiencia">Nuestra esencia</a>
                        <a href="{{ route('home') }}#beneficios">Por qué elegirnos</a>
                    @endif
                </nav>
            </details>
        </div>
    </header>

    <main id="main-content">{{ $slot }}</main>

    <footer class="site-footer" id="footer">
        <div class="site-footer__top">
            <div class="site-footer__brand">
                <a href="{{ url('/') }}" class="brand brand--footer" aria-label="{{ $storeName }}, inicio">
                    <span class="brand__mark">D<span>.</span></span>
                    <span class="brand__wordmark">{{ strtoupper($storeName) }}</span>
                </a>
                <p>{{ $storeSettings['footer_description'] ?? 'Una experiencia de compra creada alrededor de los detalles que hacen la diferencia.' }}</p>
            </div>

            <div class="site-footer__column">
                <span class="footer-heading">Explora</span>
                @if ($footerMenu->isNotEmpty())
                    @foreach ($footerMenu as $item)
                        <a href="{{ $item->url }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
                    @endforeach
                @else
                    <a href="{{ route('products.index') }}">Productos</a>
                    <a href="{{ route('home') }}#colecciones">Colecciones</a>
                @endif
            </div>

            <div class="site-footer__column">
                <span class="footer-heading">{{ $storeName }}</span>
                <a href="{{ route('home') }}">Inicio</a>
                @foreach ($footerMenu->take(4) as $item)
                    <a href="{{ $item->url }}" @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
                @endforeach
            </div>

            <div class="site-footer__signature">
                <span>ESTILO EN CADA DETALLE</span>
                <span class="site-footer__signature-mark">D.</span>
            </div>
        </div>
        <div class="site-footer__bottom">
            <span>© {{ date('Y') }} {{ $storeName }}. Todos los derechos reservados.</span>
            <span>Diseñado para descubrir algo especial.</span>
        </div>
    </footer>
</body>
</html>
