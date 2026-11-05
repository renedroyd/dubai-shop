<?php

namespace App\Support\Storefront;

class BannerPromptGenerator
{
    /**
     * @param array{campaign_type?: string, visual_style?: string, format?: string, brief?: string} $data
     */
    public function generate(array $data): string
    {
        $campaignTypes = [
            'promotion' => 'promoción comercial',
            'combos' => 'combos y paquetes de productos',
            'products' => 'presentación de productos',
            'remittances' => 'servicio internacional de remesas hacia Cuba',
            'seasonal' => 'campaña de temporada o fecha especial',
            'brand' => 'imagen institucional y reconocimiento de marca',
        ];

        $styles = [
            'luxury' => 'editorial de lujo, elegante y sofisticado',
            'commercial' => 'fotografía publicitaria fotorrealista de alta gama',
            'minimal' => 'minimalista, limpio y contemporáneo',
            'cinematic' => 'cinematográfico, con iluminación dramática y profundidad',
        ];

        $formats = [
            'wide' => 'panorámico 2.4:1, composición horizontal, resolución objetivo 1920 × 800 px',
            'landscape' => 'horizontal 16:9, resolución objetivo 1920 × 1080 px',
            'square' => 'cuadrado 1:1, resolución objetivo 1200 × 1200 px',
            'portrait' => 'vertical 4:5, resolución objetivo 1200 × 1500 px',
        ];

        $type = $campaignTypes[$data['campaign_type'] ?? ''] ?? $campaignTypes['promotion'];
        $style = $styles[$data['visual_style'] ?? ''] ?? $styles['luxury'];
        $format = $formats[$data['format'] ?? ''] ?? $formats['wide'];
        $brief = trim((string) ($data['brief'] ?? ''));

        return implode("\n\n", [
            'Crea una imagen publicitaria profesional para un banner de la tienda online Clásico Dubai.',
            "Objetivo de la campaña: {$type}.",
            "Dirección artística: {$style}.",
            "Formato: {$format}.",
            'Identidad visual: estética premium con negro obsidiana como base, detalles dorados champagne sutiles, iluminación cuidada, materiales y texturas realistas, composición elegante y alto contraste.',
            'Composición para la tienda: deja una zona visual relativamente despejada en el lado izquierdo para colocar posteriormente título, descripción y botón HTML sobre la imagen. Coloca el elemento visual principal hacia el centro-derecha sin recortarlo.',
            'La imagen debe funcionar como fondo de banner web en escritorio y adaptarse razonablemente a recortes móviles.',
            'No incluyas palabras, letras, números, tipografía, logotipos, marcas de agua, botones ni elementos de interfaz. No inventes marcas ni etiquetas de productos. La imagen debe ser limpia: los textos comerciales se añadirán después desde el panel administrativo.',
            "Descripción específica solicitada: {$brief}",
            'Entrega únicamente la imagen final, con acabado publicitario de alta calidad, detalles nítidos, iluminación coherente y sin marcos.',
        ]);
    }
}
