<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Clientes');
        $query = Cliente::latest();
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$search}%")
                ->orWhere('nit', 'like', "%{$search}%")->orWhere('celular', 'like', "%{$search}%")
                ->orWhere('telefono', 'like', "%{$search}%")->orWhere('direccion', 'like', "%{$search}%"));
        }
        if ($request->filled('activo')) $query->where('activo', $request->boolean('activo'));
        $perPage = (int) $request->input('per_page', 20);
        return response()->json($perPage === 0 ? ['data' => $query->get()] : $query->paginate(min(max($perPage, 1), 100)));
    }

    public function show(Request $request, Cliente $cliente)
    {
        $this->authorizeAction($request, 'Ver Clientes');
        return response()->json($cliente);
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Gestionar Clientes');
        return response()->json(Cliente::create($this->validateData($request)), 201);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $this->authorizeAction($request, 'Gestionar Clientes');
        $cliente->update($this->validateData($request));
        return response()->json($cliente->fresh());
    }

    public function destroy(Request $request, Cliente $cliente)
    {
        $this->authorizeAction($request, 'Gestionar Clientes');
        $cliente->delete();
        return response()->json(['message' => 'Cliente eliminado']);
    }

    public function uploadPhoto(Request $request, Cliente $cliente)
    {
        $this->authorizeAction($request, 'Gestionar Clientes');
        $request->validate(['foto' => ['required', 'image', 'max:8192']]);
        $image = imagecreatefromstring(file_get_contents($request->file('foto')->getPathname()));
        abort_unless($image, 422, 'No se pudo procesar la fotografía');
        $width = imagesx($image); $height = imagesy($image); $scale = min(1, 1000 / max($width, $height));
        $output = imagecreatetruecolor((int) ($width * $scale), (int) ($height * $scale));
        imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), $width, $height);
        $directory = public_path('images/clientes');
        if (! is_dir($directory)) mkdir($directory, 0755, true);
        $filename = 'clientes/cliente_'.$cliente->id.'_'.time().'.webp';
        imagewebp($output, public_path('images/'.$filename), 88);
        imagedestroy($image); imagedestroy($output);
        if ($cliente->foto && is_file(public_path('images/'.$cliente->foto))) unlink(public_path('images/'.$cliente->foto));
        $cliente->update(['foto' => $filename]);
        return response()->json($cliente->fresh());
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:180'], 'nit' => ['nullable', 'string', 'max:40'],
            'telefono' => ['nullable', 'string', 'max:50'], 'celular' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'], 'direccion' => ['nullable', 'string', 'max:255'],
            'zona' => ['nullable', 'string', 'max:100'], 'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'], 'referencia' => ['nullable', 'string', 'max:1000'],
            'observacion' => ['nullable', 'string', 'max:2000'], 'activo' => ['required', 'boolean'],
        ]);
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
