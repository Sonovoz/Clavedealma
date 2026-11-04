@extends('storefront.designs.farruquito.layout')

@section('title', $activeCollection->name.' · '.$store->name)

@section('content')
    <div class="sf-portada-corta"></div>
    @include('storefront.partials.showcase', ['id' => 'coleccion'])
@endsection
