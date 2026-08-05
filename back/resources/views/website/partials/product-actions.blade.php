<div class="product-bottom">
    <span class="price">Bs {{ number_format($producto->precio_venta, 2, ',', '.') }}</span>
    <button class="add" data-add="{{ $producto->id }}" @disabled($producto->stock_inicial <= 0)>{{ $producto->stock_inicial > 0 ? 'Añadir' : 'Agotado' }}</button>
</div>
