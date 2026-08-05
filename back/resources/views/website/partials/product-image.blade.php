<div class="product-img">
    @if($producto->foto)
        <img src="{{ asset('images/'.$producto->foto) }}" alt="{{ $producto->nombre }}" loading="lazy">
    @else
        <span class="no-image">📦</span>
    @endif
    <span class="tag">{{ $tag }}</span>
</div>
