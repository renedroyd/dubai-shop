<x-storefront.layout
    :title="$page->meta_title ?: $page->title"
    :description="$page->meta_description ?: strip_tags($page->content)"
>
    <article class="page-container section-space" style="max-width: 900px">
        <header class="section-heading">
            <span class="eyebrow">{{ strtoupper($page->title) }}</span>
            <h1>{{ $page->title }}</h1>
        </header>
        <div class="prose prose-invert mt-8">{!! nl2br(e($page->content)) !!}</div>
    </article>
</x-storefront.layout>
