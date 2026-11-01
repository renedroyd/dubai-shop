<x-storefront.layout
    title="Dubai Shop | Compra con estilo. Vive la diferencia."
    description="Explora Dubai Shop: colecciones seleccionadas, inspiración y una experiencia de compra con estilo propio."
>
    <section class="hero">
        <div class="hero__ambient hero__ambient--one" aria-hidden="true"></div>
        <div class="hero__ambient hero__ambient--two" aria-hidden="true"></div>
        <div class="hero__inner page-container">
            <div class="hero__content">
                <span class="eyebrow eyebrow--light"><span></span> EL ARTE DE ELEGIR BIEN</span>
                <h1>Compra con estilo.<br><em>Vive la diferencia.</em></h1>
                <p class="hero__description">Descubre una selección pensada para quienes valoran los buenos detalles. Encuentra inspiración, calidad y ese toque especial en cada elección.</p>
                <div class="hero__actions">
                    <a href="#colecciones" class="button button--gold">Explorar colecciones <x-storefront.icon name="arrow-up-right" :size="17" /></a>
                    <a href="#experiencia" class="text-link">Conoce Dubai Shop <x-storefront.icon name="arrow-right" :size="17" /></a>
                </div>
                <div class="hero__note">
                    <span class="hero__note-line"></span>
                    <span>UNA EXPERIENCIA CREADA CON INTENCIÓN</span>
                </div>
            </div>

            <div class="hero-art" aria-label="Composición visual de la identidad Dubai Shop">
                <div class="hero-art__orbit hero-art__orbit--outer"></div>
                <div class="hero-art__orbit hero-art__orbit--inner"></div>
                <div class="hero-art__medallion">
                    <span class="hero-art__medallion-small">DUBAI</span>
                    <span class="hero-art__medallion-large">D.</span>
                    <span class="hero-art__medallion-bottom">SHOP • EST. 2026</span>
                </div>
                <div class="hero-art__object hero-art__object--box">
                    <span class="hero-art__box-ribbon"></span>
                    <span class="hero-art__box-lid"></span>
                </div>
                <div class="hero-art__object hero-art__object--bottle">
                    <span class="hero-art__bottle-cap"></span>
                    <span class="hero-art__bottle-label">DUBAI<br><small>ESSENCE</small></span>
                </div>
                <div class="hero-art__floating hero-art__floating--top">
                    <span>✦</span> CURATED FOR YOU
                </div>
                <div class="hero-art__floating hero-art__floating--bottom">
                    <span>01</span><i></i> SELECTED DETAILS
                </div>
                <div class="hero-art__vertical">THE DUBAI EDITION</div>
            </div>
        </div>
        <div class="hero__bottom page-container">
            <span>DESCUBRE · ELIGE · DISFRUTA</span>
            <a href="#colecciones" aria-label="Desplazarse a colecciones"><x-storefront.icon name="arrow-down" :size="18" /></a>
            <span>01 / 03</span>
        </div>
    </section>

    <section class="intro-strip" id="experiencia">
        <div class="page-container intro-strip__inner">
            <span class="intro-strip__ornament">✳</span>
            <p>No se trata solo de comprar.<br><em>Se trata de encontrar algo que conecte contigo.</em></p>
            <span class="intro-strip__caption">DUBAI SHOP · MÁS QUE UNA TIENDA</span>
        </div>
    </section>

    <section class="collections-section section-space" id="colecciones">
        <div class="page-container">
            <div class="collections-section__heading">
                <x-storefront.section-heading
                    eyebrow="EXPLORA A TU MANERA"
                    title="Un mundo de posibilidades."
                    description="Encuentra inspiración en nuestras colecciones y descubre aquello que encaja con tu estilo."
                />
                <a href="#footer" class="subtle-link">Ver todas las categorías <x-storefront.icon name="arrow-up-right" :size="16" /></a>
            </div>

            <div class="category-grid">
                <x-storefront.category-card number="01" title="Tecnología" description="Ideas que conectan contigo." tone="tech" initial="T" />
                <x-storefront.category-card number="02" title="Hogar & estilo" description="Detalles para cada espacio." tone="home" initial="H" />
                <x-storefront.category-card number="03" title="Belleza" description="Tu esencia, en primer plano." tone="beauty" initial="B" />
                <x-storefront.category-card number="04" title="Regalos" description="Momentos que merecen más." tone="gifts" initial="G" />
            </div>
        </div>
    </section>

    <section class="editorial-section">
        <div class="page-container editorial-section__inner">
            <div class="editorial-art" aria-hidden="true">
                <div class="editorial-art__frame">
                    <span class="editorial-art__top">DUBAI SHOP</span>
                    <span class="editorial-art__monogram">D</span>
                    <span class="editorial-art__bottom">THE ART OF EVERYDAY</span>
                </div>
                <span class="editorial-art__seal">✦<br><small>DS</small></span>
            </div>
            <div class="editorial-copy">
                <span class="eyebrow eyebrow--light"><span></span> NUESTRA FILOSOFÍA</span>
                <h2>La diferencia<br>está en <em>los detalles.</em></h2>
                <p>Creemos que cada elección cuenta. Por eso, Dubai Shop nace con una mirada sencilla: acercarte productos e ideas que aporten algo especial a tu día a día.</p>
                <a href="#beneficios" class="button button--outline">Descubre nuestra esencia <x-storefront.icon name="arrow-up-right" :size="17" /></a>
            </div>
        </div>
    </section>

    <section class="benefits-section section-space" id="beneficios">
        <div class="page-container">
            <x-storefront.section-heading
                eyebrow="UNA COMPRA CON MÁS VALOR"
                title="Pensado para ti, en cada paso."
                description="Queremos que explorar, elegir y comprar se sienta sencillo y especial."
                align="center"
            />
            <div class="benefits-grid">
                <x-storefront.benefit-card icon="sparkles" title="Selección especial" description="Una experiencia visual cuidada y colecciones pensadas para inspirarte." />
                <x-storefront.benefit-card icon="shield-check" title="Compra con confianza" description="Información clara y una experiencia diseñada para acompañarte." />
                <x-storefront.benefit-card icon="heart" title="Atención cercana" description="Un espacio creado para que encuentres lo que realmente buscas." />
                <x-storefront.benefit-card icon="truck" title="A tu ritmo" description="Seguimos preparando una experiencia de compra cómoda de principio a fin." />
            </div>
        </div>
    </section>

    <section class="closing-banner">
        <div class="page-container closing-banner__inner">
            <div>
                <span class="eyebrow eyebrow--light"><span></span> TU PRÓXIMO DESCUBRIMIENTO</span>
                <h2>Hay algo especial<br><em>esperando por ti.</em></h2>
            </div>
            <a href="#colecciones" class="button button--gold">Explorar Dubai Shop <x-storefront.icon name="arrow-up-right" :size="17" /></a>
            <span class="closing-banner__watermark" aria-hidden="true">D.</span>
        </div>
    </section>
</x-storefront.layout>
