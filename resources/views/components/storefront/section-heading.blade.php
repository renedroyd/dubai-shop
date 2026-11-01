@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'left',
])

<div @class(['section-heading', 'section-heading--center' => $align === 'center'])>
    @if ($eyebrow)
        <span class="eyebrow"><span></span>{{ $eyebrow }}</span>
    @endif
    <h2>{{ $title }}</h2>
    @if ($description)
        <p>{{ $description }}</p>
    @endif
</div>
