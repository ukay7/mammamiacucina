<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductDna
{
    public function validate(Request $r, Product $product): array
    {
        $keys = [];
        $rules = ['editor_revision' => 'sometimes|integer|min:0', 'ingredient_documents' => 'sometimes|array|max:10', 'ingredient_documents.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp|max:20480', 'remove_documents' => 'sometimes|array|max:100', 'remove_documents.*' => ['integer', Rule::exists('product_documents', 'id')->where('product_id', $product->id ?? 0)]];
        foreach (config('product_dna') as $fields) {
            foreach ($fields as $key => $field) {
                $keys[] = $key;
                $rules['dna.'.$key] = match ($field[1]) {
                    'checkbox' => ['nullable', 'boolean'],'select' => ['nullable', Rule::in(array_keys($field[2]))],
                    'integer' => ['nullable', 'integer', 'min:1', 'max:1000000'],'number' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
                    'url' => ['nullable', 'url:http,https', 'max:2000'],'textarea' => ['nullable', 'string', 'max:30000'],default => ['nullable', 'string', 'max:255'],
                };
            }
        }
        $rules['dna'] = 'sometimes|array:'.implode(',', $keys);

        return $r->validate($rules);
    }

    public function saveDocuments(Request $r, Product $product, array &$stored, array &$deleted): void
    {
        foreach ($product->documents()->whereIn('id', $r->input('remove_documents', []))->get() as $doc) {
            $deleted[] = $doc->path;
            $doc->delete();
        }
        foreach ($r->file('ingredient_documents', []) as $file) {
            $path = $file->store('product-documents/'.$product->id, 'local');
            $stored[] = $path;
            $product->documents()->create(['path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType()]);
        }
    }
}
