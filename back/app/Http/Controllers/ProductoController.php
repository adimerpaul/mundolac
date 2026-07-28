<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Productos');
        $query = Producto::query()->orderBy('nombre');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('codigo', 'like', "%{$search}%")
                ->orWhere('nombre', 'like', "%{$search}%")
                ->orWhere('categoria', 'like', "%{$search}%"));
        }
        if ($categoria = trim((string) $request->input('categoria'))) {
            $query->where('categoria', $categoria);
        }

        return response()->json($query->paginate(min(max((int) $request->input('per_page', 20), 1), 100)));
    }

    public function catalogos(Request $request)
    {
        $this->authorizeAction($request, 'Ver Productos');

        return response()->json([
            'categorias' => Producto::whereNotNull('categoria')->distinct()->orderBy('categoria')->pluck('categoria'),
            'unidades' => Producto::whereNotNull('unidad')->distinct()->orderBy('unidad')->pluck('unidad'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Productos');

        return response()->json(Producto::create($this->validatedData($request)), 201);
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $producto->update($this->validatedData($request, $producto));

        return response()->json($producto->fresh());
    }

    public function destroy(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Eliminar Productos');
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    private function validatedData(Request $request, ?Producto $producto = null): array
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('productos')->ignore($producto)],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'unidad' => ['required', 'string', 'max:20'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock_inicial' => ['required', 'integer', 'min:0'],
        ]);
        foreach (['codigo', 'nombre', 'categoria', 'unidad'] as $field) {
            $data[$field] = isset($data[$field]) && $data[$field] !== null
                ? mb_strtoupper(trim($data[$field])) : null;
        }

        return $data;
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
