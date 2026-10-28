<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Vehicle;
use App\Services\DocumentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $documents = $vehicle->documents()
            // Los documentos sin vencimiento van al final; el resto, del que vence antes al después.
            ->orderByRaw('expires_on IS NULL')
            ->orderBy('expires_on')
            ->orderBy('id')
            ->paginate(20);

        return DocumentResource::collection($documents)->response();
    }

    public function summary(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return response()->json([
            'data' => app(DocumentStatus::class)->summary($vehicle),
        ]);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);

        $document = $vehicle->documents()->create($request->validate($this->rules($request)));

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    public function show(Request $request, Document $document): DocumentResource
    {
        $this->authorize('view', $document);

        return new DocumentResource($document);
    }

    public function update(Request $request, Document $document): DocumentResource
    {
        $this->authorize('update', $document);

        $document->update($request->validate($this->rules($request)));

        return new DocumentResource($document);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $document->delete();

        return response()->json(['message' => 'Documento eliminado.']);
    }

    public function storeFile(Request $request, Document $document): DocumentResource
    {
        $this->authorize('update', $document);

        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,webp,pdf|max:10240',
        ]);

        // La colección es single file: subir otro reemplaza el anterior.
        $document->addMediaFromRequest('file')->toMediaCollection('file');

        return new DocumentResource($document->fresh());
    }

    public function destroyFile(Request $request, Document $document): DocumentResource
    {
        $this->authorize('update', $document);

        $document->clearMediaCollection('file');

        return new DocumentResource($document->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        return [
            'kind' => 'required|in:insurance,inspection,tax,license,other',
            'name' => 'required|string|max:150',
            'issuer' => 'nullable|string|max:150',
            'reference' => 'nullable|string|max:100',
            'issued_on' => 'nullable|date',
            'expires_on' => [
                'nullable',
                'date',
                // Solo se compara si también viene la emisión: un documento puede no tenerla.
                ...($request->filled('issued_on') ? ['after_or_equal:issued_on'] : []),
            ],
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
