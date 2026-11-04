<div>
    @if ($visible && $stores !== [])
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;font-weight:600;white-space:nowrap">
            <span style="opacity:.7">Tienda:</span>
            <select wire:model.live="storeId" aria-label="Tienda en la que trabajas"
                style="font-weight:700;border-radius:.5rem;padding:.35rem 2rem .35rem .6rem;border:1px solid rgba(127,127,127,.35);background-color:transparent;color:inherit">
                @foreach ($stores as $id => $name)
                    <option value="{{ $id }}" style="color:#111">{{ $name }}</option>
                @endforeach
            </select>
        </label>
    @endif
</div>
