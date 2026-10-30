<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Enums\NotificationStatus;
use App\Models\AuditLog;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('notifications.view');

        $filters = $request->validate([
            'onglet' => ['nullable', Rule::in(['canaux', 'journal'])],
            'statut' => ['nullable', Rule::enum(NotificationStatus::class)],
        ]);

        $tab = $filters['onglet'] ?? 'canaux';

        return view('notifications.index', [
            'tab' => $tab,
            'filters' => $filters,
            'channels' => NotificationChannel::with(['preferences' => fn ($q) => $q->whereNull('site_id')])->orderBy('type')->orderBy('destination')->get(),
            'logs' => $tab === 'journal'
                ? NotificationLog::with('site:id,name')
                    ->when($filters['statut'] ?? null, fn ($q, $s) => $q->where('status', $s))
                    ->latest()->paginate(30)->withQueryString()
                : null,
            'whatsappEnabled' => (bool) config('uptime.whatsapp.enabled'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('notifications.manage');

        $data = $request->validate([
            'type' => ['required', Rule::enum(ChannelType::class)],
            'label' => ['nullable', 'string', 'max:100'],
            'destination' => ['required', 'string', 'max:191',
                Rule::when($request->input('type') === ChannelType::Email->value, ['email']),
                Rule::when($request->input('type') === ChannelType::WhatsApp->value, ['regex:/^\+[1-9][0-9]{7,14}$/']),
                Rule::unique('notification_channels')->where('agency_id', app(TenantContext::class)->id())->where('type', $request->input('type')),
            ],
        ], [
            'destination.regex' => 'Le numéro WhatsApp doit être au format international, par exemple +2250701020304.',
            'destination.unique' => 'Ce destinataire existe déjà.',
        ], ['destination' => 'destinataire', 'label' => 'libellé']);

        DB::transaction(function () use ($data, $request) {
            $channel = new NotificationChannel($data);
            $channel->created_by = $request->user()->id;
            $channel->save();

            // Par défaut, le canal reçoit les alertes de tous les sites.
            NotificationPreference::create(['notification_channel_id' => $channel->id, 'site_id' => null]);

            AuditLog::record('notification_channel.created', $channel, ['type' => $channel->type->value]);
        });

        return redirect()->route('notifications.index')->with('status', 'Destinataire ajouté.');
    }

    public function updatePreference(Request $request, NotificationChannel $channel): RedirectResponse
    {
        $this->authorize('notifications.manage');

        $data = $request->validate([
            'notify_on_down' => ['boolean'],
            'notify_on_recovery' => ['boolean'],
            'delay_minutes' => ['required', 'integer', 'between:0,120'],
        ], [], ['delay_minutes' => 'délai avant alerte']);

        NotificationPreference::updateOrCreate(
            ['notification_channel_id' => $channel->id, 'site_id' => null],
            [
                'notify_on_down' => $request->boolean('notify_on_down'),
                'notify_on_recovery' => $request->boolean('notify_on_recovery'),
                'delay_minutes' => $data['delay_minutes'],
            ],
        );

        AuditLog::record('notification_preference.updated', $channel);

        return back()->with('status', 'Préférences enregistrées.');
    }

    public function toggle(NotificationChannel $channel): RedirectResponse
    {
        $this->authorize('notifications.manage');

        $channel->is_active = ! $channel->is_active;
        $channel->save();

        AuditLog::record($channel->is_active ? 'notification_channel.enabled' : 'notification_channel.disabled', $channel);

        return back()->with('status', $channel->is_active ? 'Destinataire activé.' : 'Destinataire désactivé.');
    }

    public function destroy(NotificationChannel $channel): RedirectResponse
    {
        $this->authorize('notifications.manage');

        AuditLog::record('notification_channel.deleted', $channel, ['destination' => $channel->destination]);
        $channel->delete();

        return back()->with('status', 'Destinataire supprimé. L\'historique des envois est conservé.');
    }
}
