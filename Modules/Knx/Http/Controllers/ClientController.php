<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\ClientDetailResource;
use Modules\Knx\Http\Resources\ClientResource;
use Modules\Knx\Models\KnxClient;

/**
 * Clientes (§4.7).
 */
class ClientController extends Controller
{
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $term = $validated['q'] ?? null;

        $clients = KnxClient::query()
            ->when($term !== null && trim($term) !== '', function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('contact', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->orderBy('id')
            ->get();

        return ClientResource::list($clients, $request);
    }

    public function show(string $id): ClientDetailResource
    {
        $client = KnxClient::query()
            ->with(['projects' => fn ($query) => $query->with(['client', 'lead'])->orderBy('id')])
            ->findOrFail($id);

        return ClientDetailResource::make($client);
    }
}
