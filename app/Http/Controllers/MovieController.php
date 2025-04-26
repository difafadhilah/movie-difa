<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MovieController extends Controller
{
    public function index()
    {
        $movies = Movie::latest()
            ->when(request('search'), function ($query) {
                $search = request('search');
                $query->where('judul', 'like', "%$search%")
                      ->orWhere('sinopsis', 'like', "%$search%");
            })
            ->paginate(6)
            ->withQueryString();

        return view('homepage', compact('movies'));
    }

    public function detail($id)
    {
        $movie = Movie::findOrFail($id);
        return view('detail', compact('movie'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('input', compact('categories'));
    }

    public function store(Request $request)
    {
        $this->validateMovie($request);

        $fileName = $this->handleUpload($request);

        Movie::create($request->only([
            'id', 'judul', 'category_id', 'sinopsis', 'tahun', 'pemain'
        ]) + ['foto_sampul' => $fileName]);

        return redirect('/')->with('success', 'Data berhasil disimpan');
    }

    public function data()
    {
        $movies = Movie::latest()->paginate(10);
        return view('data-movies', compact('movies'));
    }

    public function form_edit($id)
    {
        $movie = Movie::findOrFail($id);
        $categories = Category::all();
        return view('form-edit', compact('movie', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $this->validateMovie($request, $isUpdate = true);

        $movie = Movie::findOrFail($id);

        $data = $request->only([
            'judul', 'sinopsis', 'category_id', 'tahun', 'pemain'
        ]);

        if ($request->hasFile('foto_sampul')) {
            $this->deleteOldImage($movie->foto_sampul);
            $data['foto_sampul'] = $this->handleUpload($request);
        }

        $movie->update($data);

        return redirect('/movies/data')->with('success', 'Data berhasil diperbarui');
    }

    public function delete($id)
    {
        $movie = Movie::findOrFail($id);
        $this->deleteOldImage($movie->foto_sampul);
        $movie->delete();

        return redirect('/movies/data')->with('success', 'Data berhasil dihapus');
    }

    /*** PRIVATE METHODS ***/

    private function validateMovie(Request $request, $isUpdate = false)
    {
        $rules = [
            'judul' => 'required|string|max:255',
            'category_id' => 'required|integer',
            'sinopsis' => 'required|string',
            'tahun' => 'required|integer',
            'pemain' => 'required|string',
            'foto_sampul' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ];

        if (!$isUpdate) {
            $rules = array_merge($rules, [
                'id' => ['required', 'string', 'max:255', Rule::unique('movies', 'id')],
                'foto_sampul' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);
        }

        $request->validate($rules);
    }

    private function handleUpload(Request $request)
    {
        $randomName = Str::uuid()->toString();
        $extension = $request->file('foto_sampul')->getClientOriginalExtension();
        $fileName = "{$randomName}.{$extension}";

        $request->file('foto_sampul')->move(public_path('images'), $fileName);

        return $fileName;
    }

    private function deleteOldImage($filename)
    {
        $filePath = public_path('images/' . $filename);
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }
}
