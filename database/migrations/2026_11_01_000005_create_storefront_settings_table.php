<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('group', 80)->default('general')->index();
            $table->string('label');
            $table->longText('value')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $defaults = [
            ['key' => 'site_name', 'group' => 'general', 'label' => 'Nombre de la tienda', 'value' => 'Dubai Shop', 'sort_order' => 1],
            ['key' => 'announcement_text', 'group' => 'general', 'label' => 'Barra de anuncio', 'value' => 'Una selección especial. Una experiencia diferente.', 'sort_order' => 2],
            ['key' => 'footer_description', 'group' => 'general', 'label' => 'Descripción del pie de página', 'value' => 'Una experiencia de compra creada alrededor de los detalles que hacen la diferencia.', 'sort_order' => 3],
            ['key' => 'home.hero_eyebrow', 'group' => 'inicio', 'label' => 'Hero — texto superior', 'value' => 'EL ARTE DE ELEGIR BIEN', 'sort_order' => 10],
            ['key' => 'home.hero_title_line_1', 'group' => 'inicio', 'label' => 'Hero — título principal', 'value' => 'Compra con estilo.', 'sort_order' => 11],
            ['key' => 'home.hero_title_line_2', 'group' => 'inicio', 'label' => 'Hero — título destacado', 'value' => 'Vive la diferencia.', 'sort_order' => 12],
            ['key' => 'home.hero_description', 'group' => 'inicio', 'label' => 'Hero — descripción', 'value' => 'Descubre una selección pensada para quienes valoran los buenos detalles. Encuentra inspiración, calidad y ese toque especial en cada elección.', 'sort_order' => 13],
            ['key' => 'home.hero_primary_cta', 'group' => 'inicio', 'label' => 'Hero — botón principal', 'value' => 'Explorar colecciones', 'sort_order' => 14],
            ['key' => 'home.hero_secondary_cta', 'group' => 'inicio', 'label' => 'Hero — botón secundario', 'value' => 'Conoce Dubai Shop', 'sort_order' => 15],
            ['key' => 'home.hero_note', 'group' => 'inicio', 'label' => 'Hero — nota inferior', 'value' => 'UNA EXPERIENCIA CREADA CON INTENCIÓN', 'sort_order' => 16],
            ['key' => 'home.intro_text', 'group' => 'inicio', 'label' => 'Introducción — texto', 'value' => 'No se trata solo de comprar.', 'sort_order' => 20],
            ['key' => 'home.intro_emphasis', 'group' => 'inicio', 'label' => 'Introducción — texto destacado', 'value' => 'Se trata de encontrar algo que conecte contigo.', 'sort_order' => 21],
            ['key' => 'home.intro_caption', 'group' => 'inicio', 'label' => 'Introducción — firma', 'value' => 'DUBAI SHOP · MÁS QUE UNA TIENDA', 'sort_order' => 22],
            ['key' => 'home.collections_eyebrow', 'group' => 'inicio', 'label' => 'Colecciones — texto superior', 'value' => 'EXPLORA A TU MANERA', 'sort_order' => 30],
            ['key' => 'home.collections_title', 'group' => 'inicio', 'label' => 'Colecciones — título', 'value' => 'Un mundo de posibilidades.', 'sort_order' => 31],
            ['key' => 'home.collections_description', 'group' => 'inicio', 'label' => 'Colecciones — descripción', 'value' => 'Encuentra inspiración en nuestras colecciones y descubre aquello que encaja con tu estilo.', 'sort_order' => 32],
            ['key' => 'home.philosophy_eyebrow', 'group' => 'inicio', 'label' => 'Filosofía — texto superior', 'value' => 'NUESTRA FILOSOFÍA', 'sort_order' => 40],
            ['key' => 'home.philosophy_title', 'group' => 'inicio', 'label' => 'Filosofía — título', 'value' => 'La diferencia está en los detalles.', 'sort_order' => 41],
            ['key' => 'home.philosophy_description', 'group' => 'inicio', 'label' => 'Filosofía — descripción', 'value' => 'Creemos que cada elección cuenta. Por eso, Dubai Shop nace con una mirada sencilla: acercarte productos e ideas que aporten algo especial a tu día a día.', 'sort_order' => 42],
            ['key' => 'home.benefits_eyebrow', 'group' => 'inicio', 'label' => 'Beneficios — texto superior', 'value' => 'UNA COMPRA CON MÁS VALOR', 'sort_order' => 50],
            ['key' => 'home.benefits_title', 'group' => 'inicio', 'label' => 'Beneficios — título', 'value' => 'Pensado para ti, en cada paso.', 'sort_order' => 51],
            ['key' => 'home.benefits_description', 'group' => 'inicio', 'label' => 'Beneficios — descripción', 'value' => 'Queremos que explorar, elegir y comprar se sienta sencillo y especial.', 'sort_order' => 52],
            ['key' => 'home.benefit_1_title', 'group' => 'beneficios', 'label' => 'Beneficio 1 — título', 'value' => 'Selección especial', 'sort_order' => 61],
            ['key' => 'home.benefit_1_description', 'group' => 'beneficios', 'label' => 'Beneficio 1 — descripción', 'value' => 'Una experiencia visual cuidada y colecciones pensadas para inspirarte.', 'sort_order' => 62],
            ['key' => 'home.benefit_2_title', 'group' => 'beneficios', 'label' => 'Beneficio 2 — título', 'value' => 'Compra con confianza', 'sort_order' => 63],
            ['key' => 'home.benefit_2_description', 'group' => 'beneficios', 'label' => 'Beneficio 2 — descripción', 'value' => 'Información clara y una experiencia diseñada para acompañarte.', 'sort_order' => 64],
            ['key' => 'home.benefit_3_title', 'group' => 'beneficios', 'label' => 'Beneficio 3 — título', 'value' => 'Atención cercana', 'sort_order' => 65],
            ['key' => 'home.benefit_3_description', 'group' => 'beneficios', 'label' => 'Beneficio 3 — descripción', 'value' => 'Un espacio creado para que encuentres lo que realmente buscas.', 'sort_order' => 66],
            ['key' => 'home.benefit_4_title', 'group' => 'beneficios', 'label' => 'Beneficio 4 — título', 'value' => 'A tu ritmo', 'sort_order' => 67],
            ['key' => 'home.benefit_4_description', 'group' => 'beneficios', 'label' => 'Beneficio 4 — descripción', 'value' => 'Seguimos preparando una experiencia de compra cómoda de principio a fin.', 'sort_order' => 68],
            ['key' => 'home.closing_eyebrow', 'group' => 'inicio', 'label' => 'Cierre — texto superior', 'value' => 'TU PRÓXIMO DESCUBRIMIENTO', 'sort_order' => 70],
            ['key' => 'home.closing_title', 'group' => 'inicio', 'label' => 'Cierre — título', 'value' => 'Hay algo especial', 'sort_order' => 71],
            ['key' => 'home.closing_emphasis', 'group' => 'inicio', 'label' => 'Cierre — texto destacado', 'value' => 'esperando por ti.', 'sort_order' => 72],
            ['key' => 'home.closing_cta', 'group' => 'inicio', 'label' => 'Cierre — botón', 'value' => 'Explorar Dubai Shop', 'sort_order' => 73],
            ['key' => 'seo.home_title', 'group' => 'seo', 'label' => 'SEO — título de inicio', 'value' => 'Dubai Shop | Compra con estilo. Vive la diferencia.', 'sort_order' => 80],
            ['key' => 'seo.home_description', 'group' => 'seo', 'label' => 'SEO — descripción de inicio', 'value' => 'Explora Dubai Shop: colecciones seleccionadas, inspiración y una experiencia de compra con estilo propio.', 'sort_order' => 81],
        ];

        $now = now();
        DB::table('storefront_settings')->insert(array_map(fn (array $item): array => [
            ...$item, 'is_public' => true, 'created_at' => $now, 'updated_at' => $now,
        ], $defaults));
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_settings');
    }
};
