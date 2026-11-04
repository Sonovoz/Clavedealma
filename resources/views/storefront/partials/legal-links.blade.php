{{-- Enlaces a los textos legales de la tienda (pasarela común: igual en todos los diseños). --}}
<nav class="sf-legal-links" aria-label="Información legal">
    @foreach (\App\Platform\Legal\LegalPage::cases() as $legalPage)
        <a href="{{ \App\Storefront\Urls::legal($store, $legalPage->value) }}">{{ $legalPage->label() }}</a>@if (! $loop->last) · @endif
    @endforeach
</nav>
