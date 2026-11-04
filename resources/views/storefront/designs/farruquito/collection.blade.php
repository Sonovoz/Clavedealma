@extends('storefront.designs.farruquito.layout')


@section('content')
    <div class="sf-portada-corta"></div>
    @include('storefront.partials.showcase', ['id' => 'coleccion'])
@endsection
