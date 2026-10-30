<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? null;

        $clients = Client::query()
            ->withCount([
                'sites',
                'sites as down_sites_count' => fn ($query) => $query->where('status', 'down'),
            ])
            ->when($q, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('contact_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('clients.index', ['clients' => $clients, 'q' => $q, 'hasAnyClient' => $clients->total() > 0 || Client::exists()]);
    }

    public function create(): View
    {
        $this->authorize('clients.manage');

        return view('clients.form', ['client' => new Client]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('clients.manage');

        $client = Client::create($this->validated($request));
        AuditLog::record('client.created', $client);

        return redirect()->route('clients.show', $client)->with('status', 'Client ajouté.');
    }

    public function show(Client $client): View
    {
        return view('clients.show', [
            'client' => $client,
            'sites' => $client->sites()->orderBy('name')->get(),
        ]);
    }

    public function edit(Client $client): View
    {
        $this->authorize('clients.manage');

        return view('clients.form', ['client' => $client]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('clients.manage');

        $client->update($this->validated($request, $client));
        AuditLog::record('client.updated', $client);

        return redirect()->route('clients.show', $client)->with('status', 'Client mis à jour.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('clients.manage');

        $sites = $client->sites()->count();
        AuditLog::record('client.deleted', $client, ['name' => $client->name, 'sites' => $sites]);
        $client->delete();

        return redirect()->route('clients.index')->with('status', $sites > 0
            ? "Client supprimé avec ses {$sites} site(s)."
            : 'Client supprimé.');
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120',
                Rule::unique('clients')->where('agency_id', app(TenantContext::class)->id())->ignore($client)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ().-]{6,32}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.unique' => 'Un client porte déjà ce nom dans votre agence.',
            'phone.regex' => 'Le numéro de téléphone est invalide.',
        ], ['name' => 'nom du client', 'contact_name' => 'nom du contact']);
    }
}
