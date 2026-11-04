@extends(\App\Storefront\Designs::view($design, 'layout'))

@section('title', $activeCollection->name.' · '.$store->name)

@section('content')
    @include('storefront.partials.showcase')
@endsection
