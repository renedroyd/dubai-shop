<div
    class="fi-sidebar-collapsed-brand"
    x-show="! $store.sidebar.isOpen"
    x-cloak
>
    <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" aria-label="{{ __('Ir al inicio del panel Clásico Dubai') }}">
        <img src="{{ asset('images/brand/clasico-dubai-mark.svg') }}" alt="Clásico Dubai">
    </a>
</div>
