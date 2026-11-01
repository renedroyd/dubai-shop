@props([
    'icon',
    'title',
    'description',
])

<article class="benefit-card">
    <div class="benefit-card__icon">
        <x-storefront.icon :name="$icon" :size="22" />
    </div>
    <div>
        <h3>{{ $title }}</h3>
        <p>{{ $description }}</p>
    </div>
</article>
