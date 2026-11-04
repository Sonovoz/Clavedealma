@extends('storefront.designs.farruquito.layout')

@section('title', $item['name'].' · '.$store->name)

@section('content')
    @include('storefront.partials.product')
@endsection
