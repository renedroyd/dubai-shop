<form method="POST" action="{{ route('locale.update', ['locale' => app()->getLocale() === 'es' ? 'en' : 'es']) }}" class="flex items-center gap-2">
    @csrf
    <input type="hidden" name="scope" value="admin">
    <label for="admin-language" class="sr-only">{{ __('Idioma del panel') }}</label>
    <select id="admin-language" aria-label="{{ __('Idioma del panel') }}"
        onchange="this.form.action=this.form.action.substring(0,this.form.action.lastIndexOf('/')+1)+this.value; this.form.submit()"
        class="fi-input-wrp block w-28 rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
        @foreach (config('locales.available') as $code => $label)
            <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $label }}</option>
        @endforeach
    </select>
</form>
