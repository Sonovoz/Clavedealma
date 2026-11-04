@if ($preview || (isset($item) && $item['status'] !== \App\Modules\Catalog\Contracts\ProductStatus::Published))
    <div class="sf-preview" role="status">
        Vista previa para el equipo:
        @if ($preview) la tienda está «{{ $store->status->label() }}» y el público no la ve. @endif
        @if (isset($item) && $item['status'] !== \App\Modules\Catalog\Contracts\ProductStatus::Published) este artículo está en «{{ $item['status']->label() }}». @endif
        <a href="{{ url('/admin') }}">Volver al panel</a>
    </div>
@endif
