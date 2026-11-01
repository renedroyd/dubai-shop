@props([
    'title' => 'Dubai Shop | Compra con estilo',
    'description' => 'Descubre una selección especial de productos en Dubai Shop.',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0B0B0B">
    <meta name="description" content="{{ $description }}">
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
        <p>Una selección especial. Una experiencia diferente.</p>
        <span class="announcement-bar__sparkle" aria-hidden="true">✦</span>
    </div>

    <header class="site-header">
        <div class="site-header__inner">
            <a href="{{ url('/') }}" class="brand" aria-label="Dubai Shop, inicio">
                <span class="brand__mark">D<span>.</span></span>
                <span class="brand__wordmark">DUBAI <span>SHOP</span></span>
            </a>

            <nav class="desktop-nav" aria-label="Navegación principal">
                <a href="{{ route('products.index') }}">Productos</a>
                <a href="#colecciones">Colecciones</a>
                <a href="#experiencia">Nuestra esencia</a>
                <a href="#beneficios">Por qué elegirnos</a>
            </nav>

            <div class="site-header__actions">
                <a class="header-link" href="#colecciones">
                    <x-storefront.icon name="search" :size="18" />
                    <span>Explorar</span>
                </a>
                <a class="header-cta" href="#colecciones">
                    Ver productos <x-storefront.icon name="arrow-up-right" :size="16" />
                </a>
            </div>

            <details class="mobile-menu">
                <summary aria-label="Abrir menú de navegación">
                    <x-storefront.icon name="menu" :size="22" />
                </summary>
                <nav aria-label="Navegación móvil">
                    <a href="{{ route('products.index') }}">Productos</a>
                    <a href="#colecciones">Colecciones</a>
                    <a href="#experiencia">Nuestra esencia</a>
                    <a href="#beneficios">Por qué elegirnos</a>
                    <a href="#colecciones" class="mobile-menu__cta">Explorar tienda</a>
                </nav>
            </details>
        </div>
    </header>

    <main id="main-content">
        {{ $slot }}
    </main>

    <footer class="site-footer" id="footer">
        <div class="site-footer__top">
            <div class="site-footer__brand">
                <a href="{{ url('/') }}" class="brand brand--footer" aria-label="Dubai Shop, inicio">
                    <span class="brand__mark">D<span>.</span></span>
                    <span class="brand__wordmark">DUBAI <span>SHOP</span></span>
                </a>
                <p>Una experiencia de compra creada alrededor de los detalles que hacen la diferencia.</p>
            </div>

            <div class="site-footer__column">
                <span class="footer-heading">Explora</span>
                <a href="#colecciones">Colecciones</a>
                <a href="#experiencia">Nuestra esencia</a>
                <a href="#beneficios">Ventajas</a>
            </div>

            <div class="site-footer__column">
                <span class="footer-heading">Dubai Shop</span>
                <a href="#main-content">Inicio</a>
                <a href="#footer">Contacto</a>
                <a href="#footer">Ayuda</a>
            </div>

            <div class="site-footer__signature">
                <span>ESTILO EN CADA DETALLE</span>
                <span class="site-footer__signature-mark">D.</span>
            </div>
        </div>
        <div class="site-footer__bottom">
            <span>© {{ date('Y') }} Dubai Shop. Todos los derechos reservados.</span>
            <span>Diseñado para descubrir algo especial.</span>
        </div>
    </footer>
</body>
</html>
