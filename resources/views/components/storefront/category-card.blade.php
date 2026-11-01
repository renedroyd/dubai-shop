@props([
    'number',
    'title',
    'description',
    'tone' => 'gold',
    'initial',
    'href' => '#colecciones',
])

<a href="{{ $href }}" @class(['category-card', 'category-card--'.$tone])>
    <div class="category-card__top">
        <span class="category-card__number">{{ $number }}</span>
        <span class="category-card__arrow"><x-storefront.icon name="arrow-up-right" :size="18" /></span>
    </div>
    <div class="category-card__art" aria-hidden="true">
        <span>{{ $initial }}</span>
        <i></i>
    </div>
    <div class="category-card__copy">
        <h3>{{ $title }}</h3>
        <p>{{ $description }}</p>
    </div>
</a>
