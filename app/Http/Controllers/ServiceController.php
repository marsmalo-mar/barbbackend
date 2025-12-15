<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    /** Get upload directory path */
    private function getUploadPath(): string
    {
        // Get the barbershop directory (parent of backend)
        $barbershopDir = dirname(base_path());
        $uploadDir = $barbershopDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        return $uploadDir;
    }

    /** Store image and return relative path. */
    private function saveImage(\Illuminate\Http\UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = 'service_'.(int)(microtime(true) * 1000).'.'.$ext;
        $uploadPath = $this->getUploadPath();
        $file->move($uploadPath, $name);
        return 'uploads/' . $name;
    }

    // GET /api/services/{id}
    public function show($id)
    {
        $item = Service::find($id);
        if (!$item) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $item->image_path = 'storage/' . $item->image_path;
        return response()->json($item);
    }

    // GET /api/services
    public function index(Request $req)
    {
        $search = trim($req->query('search', ''));
        $q = Service::query();

        if ($search !== '') {
            $q->where(function ($x) use ($search) {
                $x->where('name', 'LIKE', "%$search%")
                  ->orWhere('description', 'LIKE', "%$search%")
                  ->orWhere('price', 'LIKE', "%$search%");
            });
        }

        $items = $q->orderBy('id', 'asc')->get()->map(function ($c) {
            $c->image_path = 'storage/' . $c->image_path;
            return $c;
        });

        return response()->json($items);
    }

    // POST /api/services
    public function store(Request $req)
    {
        $data = $req->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:1',
            'image'       => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:3072',
        ]);

        $filename = 'uploads/default.png';
        if ($req->hasFile('image')) {
            $filename = $this->saveImage($req->file('image'));
        }

        $item = Service::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'],
            'duration'    => $data['duration'],
            'image_path'  => $filename,
        ]);

        $item->image_path = 'storage/' . $item->image_path;
        return response()->json(['item' => $item], 201);
    }

    // PUT /api/services/{id}
    public function update(Request $req, $id)
    {
        $item = Service::find($id);
        if (!$item) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $data = $req->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:1',
            'image'       => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:3072',
        ]);

        if ($req->hasFile('image')) {
            $newName = $this->saveImage($req->file('image'));
            if ($item->image_path && $item->image_path !== 'uploads/default.png') {
                $oldPath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $item->image_path;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            $item->image_path = $newName;
        }

        $item->name        = $data['name'];
        $item->description = $data['description'] ?? null;
        $item->price       = $data['price'];
        $item->duration    = $data['duration'];
        $item->save();

        $item->image_path = 'storage/' . $item->image_path;
        return response()->json(['item' => $item]);
    }

    // DELETE /api/services/{id}
    public function destroy($id)
    {
        $item = Service::find($id);
        if (!$item) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if ($item->image_path && $item->image_path !== 'uploads/default.png') {
            $filePath = dirname(base_path()) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $item->image_path;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $item->delete();
        return response()->json(['message' => 'Deleted']);
    }
}

