@extends(\App\Storefront\Designs::view($design, 'layout'))

@section('title', $item['name'].' · '.$store->name)

@section('content')
    @include('storefront.partials.product')
@endsection
