@extends(\App\Storefront\Designs::view($design, 'layout'))

@section('content')
    <section class="base-hero">
        <div class="sf-wrap">
            <h1 class="sf-display">{{ $store->name }}</h1>
            @if ($store->tagline)
                <p>{{ $store->tagline }}</p>
            @endif
        </div>
    </section>
    @include('storefront.partials.showcase')
@endsection
