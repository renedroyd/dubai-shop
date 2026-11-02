<x-storefront.layout
    :title="$settings['seo.home_title'] ?? 'Clásico Dubai | Compra con estilo'"
    :description="$settings['seo.home_description'] ?? 'Descubre Clásico Dubai.'"
>
    @if ($banners->isNotEmpty())
        <section class="page-container" aria-label="Campañas destacadas" style="display:grid;gap:1rem;padding-top:1.5rem">
            @foreach ($banners as $banner)
                <article style="position:relative;overflow:hidden;border:1px solid rgba(198,161,91,.3);background:#111">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($banner->image_path) }}" alt="{{ $banner->image_alt ?: $banner->title }}" style="display:block;width:100%;max-height:420px;object-fit:cover">
                    @if ($banner->eyebrow || $banner->description || $banner->link_url)
                        <div style="padding:1.25rem 1.5rem">
                            @if ($banner->eyebrow)<span class="eyebrow">{{ $banner->eyebrow }}</span>@endif
                            <h2>{{ $banner->title }}</h2>
                            @if ($banner->description)<p>{{ $banner->description }}</p>@endif
                            @if ($banner->link_url)<a class="button button--gold" href="{{ $banner->link_url }}">{{ $banner->link_label ?: 'Descubrir' }} <x-storefront.icon name="arrow-up-right" :size="16" /></a>@endif
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    <section class="hero">
        <div class="hero__ambient hero__ambient--one" aria-hidden="true"></div>
        <div class="hero__ambient hero__ambient--two" aria-hidden="true"></div>
        <div class="hero__inner page-container">
            <div class="hero__content">
                <span class="eyebrow eyebrow--light"><span></span> {{ $settings['home.hero_eyebrow'] ?? 'EL ARTE DE ELEGIR BIEN' }}</span>
                <h1>{{ $settings['home.hero_title_line_1'] ?? 'Compra con estilo.' }}<br><em>{{ $settings['home.hero_title_line_2'] ?? 'Vive la diferencia.' }}</em></h1>
                <p class="hero__description">{{ $settings['home.hero_description'] ?? '' }}</p>
                <div class="hero__actions">
                    <a href="{{ route('products.index') }}" class="button button--gold">{{ $settings['home.hero_primary_cta'] ?? 'Explorar productos' }} <x-storefront.icon name="arrow-up-right" :size="17" /></a>
                    <a href="#experiencia" class="text-link">{{ $settings['home.hero_secondary_cta'] ?? 'Conoce nuestra tienda' }} <x-storefront.icon name="arrow-right" :size="17" /></a>
                </div>
                <div class="hero__note"><span class="hero__note-line"></span><span>{{ $settings['home.hero_note'] ?? '' }}</span></div>
            </div>
            <div class="hero-art" aria-label="Composición visual de la identidad de la tienda">
                <div class="hero-art__orbit hero-art__orbit--outer"></div><div class="hero-art__orbit hero-art__orbit--inner"></div>
                <div class="hero-art__medallion"><span class="hero-art__medallion-small">{{ $settings['site_name'] ?? 'DUBAI' }}</span><span class="hero-art__medallion-large">D.</span><span class="hero-art__medallion-bottom">SHOP • EST. 2026</span></div>
                <div class="hero-art__object hero-art__object--box"><span class="hero-art__box-ribbon"></span><span class="hero-art__box-lid"></span></div>
                <div class="hero-art__object hero-art__object--bottle"><span class="hero-art__bottle-cap"></span><span class="hero-art__bottle-label">DUBAI<br><small>ESSENCE</small></span></div>
                <div class="hero-art__floating hero-art__floating--top"><span>✦</span> CURATED FOR YOU</div>
                <div class="hero-art__floating hero-art__floating--bottom"><span>01</span><i></i> SELECTED DETAILS</div>
                <div class="hero-art__vertical">THE DUBAI EDITION</div>
            </div>
        </div>
        <div class="hero__bottom page-container"><span>DESCUBRE · ELIGE · DISFRUTA</span><a href="#colecciones" aria-label="Desplazarse a colecciones"><x-storefront.icon name="arrow-down" :size="18" /></a><span>01 / 03</span></div>
    </section>

    <section class="intro-strip" id="experiencia"><div class="page-container intro-strip__inner"><span class="intro-strip__ornament">✳</span><p>{{ $settings['home.intro_text'] ?? 'No se trata solo de comprar.' }}<br><em>{{ $settings['home.intro_emphasis'] ?? 'Se trata de encontrar algo que conecte contigo.' }}</em></p><span class="intro-strip__caption">{{ $settings['home.intro_caption'] ?? ($settings['site_name'] ?? 'DUBAI SHOP') }}</span></div></section>

    <section class="collections-section section-space" id="colecciones">
        <div class="page-container">
            <div class="collections-section__heading">
                <x-storefront.section-heading :eyebrow="$settings['home.collections_eyebrow'] ?? 'EXPLORA A TU MANERA'" :title="$settings['home.collections_title'] ?? 'Un mundo de posibilidades.'" :description="$settings['home.collections_description'] ?? ''" />
                <a href="{{ route('products.index') }}" class="subtle-link">Ver todas las categorías <x-storefront.icon name="arrow-up-right" :size="16" /></a>
            </div>
            <div class="category-grid">
                @forelse ($categories as $category)
                    <x-storefront.category-card :number="str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)" :title="$category->name" :description="$category->description ?: 'Descubre nuestra selección.'" :tone="['tech', 'home', 'beauty', 'gifts'][$loop->index] ?? 'gold'" :initial="mb_substr($category->name, 0, 1)" :href="route('products.index', ['category' => $category->slug])" />
                @empty
                    <div class="catalog-empty"><h2>Estamos preparando nuestras colecciones</h2><p>Pronto encontrarás nuevas categorías para explorar.</p><a class="button button--gold" href="{{ route('products.index') }}">Ver productos</a></div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="editorial-section"><div class="page-container editorial-section__inner"><div class="editorial-art" aria-hidden="true"><div class="editorial-art__frame"><span class="editorial-art__top">{{ strtoupper($settings['site_name'] ?? 'DUBAI SHOP') }}</span><span class="editorial-art__monogram">D</span><span class="editorial-art__bottom">THE ART OF EVERYDAY</span></div><span class="editorial-art__seal">✦<br><small>DS</small></span></div><div class="editorial-copy"><span class="eyebrow eyebrow--light"><span></span> {{ $settings['home.philosophy_eyebrow'] ?? 'NUESTRA FILOSOFÍA' }}</span><h2>{{ $settings['home.philosophy_title'] ?? 'La diferencia está en los detalles.' }}</h2><p>{{ $settings['home.philosophy_description'] ?? '' }}</p><a href="#beneficios" class="button button--outline">Descubre nuestra esencia <x-storefront.icon name="arrow-up-right" :size="17" /></a></div></div></section>

    <section class="benefits-section section-space"><div class="page-container"><x-storefront.section-heading :eyebrow="$settings['home.benefits_eyebrow'] ?? 'UNA COMPRA CON MÁS VALOR'" :title="$settings['home.benefits_title'] ?? 'Pensado para ti, en cada paso.'" :description="$settings['home.benefits_description'] ?? ''" align="center" /><div class="benefits-grid">
        @php($benefitIcons = ['sparkles', 'shield-check', 'heart', 'truck'])
        @for ($i = 1; $i <= 4; $i++)<x-storefront.benefit-card :icon="$benefitIcons[$i - 1]" :title="$settings['home.benefit_'.$i.'_title'] ?? 'Beneficio'" :description="$settings['home.benefit_'.$i.'_description'] ?? ''" />@endfor
    </div></div></section>

    <section class="closing-banner"><div class="page-container closing-banner__inner"><div><span class="eyebrow eyebrow--light"><span></span> {{ $settings['home.closing_eyebrow'] ?? 'TU PRÓXIMO DESCUBRIMIENTO' }}</span><h2>{{ $settings['home.closing_title'] ?? 'Hay algo especial' }}<br><em>{{ $settings['home.closing_emphasis'] ?? 'esperando por ti.' }}</em></h2></div><a href="{{ route('products.index') }}" class="button button--gold">{{ $settings['home.closing_cta'] ?? 'Explorar tienda' }} <x-storefront.icon name="arrow-up-right" :size="17" /></a><span class="closing-banner__watermark" aria-hidden="true">D.</span></div></section>
</x-storefront.layout>
