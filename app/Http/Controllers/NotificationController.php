<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Models\AuditLog;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Services\Billing\PlanLimits;
use App\Services\Notifications\ChannelSender;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationSettings;
use App\Services\Notifications\WhatsAppClient;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('notifications.view');

        $filters = $request->validate([
            'onglet' => ['nullable', Rule::in(['canaux', 'journal'])],
            'statut' => ['nullable', Rule::enum(NotificationStatus::class)],
            'canal' => ['nullable', Rule::enum(ChannelType::class)],
            'evenement' => ['nullable', Rule::enum(NotificationEvent::class)],
        ]);

        $tab = $filters['onglet'] ?? 'canaux';

        return view('notifications.index', [
            'tab' => $tab,
            'filters' => $filters,
            'channels' => NotificationChannel::with(['preferences' => fn ($q) => $q->whereNull('site_id')])->orderBy('type')->orderBy('destination')->get(),
            'logs' => $tab === 'journal'
                ? NotificationLog::with('site:id,name')
                    ->when($filters['statut'] ?? null, fn ($q, $s) => $q->where('status', $s))
                    ->when($filters['canal'] ?? null, fn ($q, $c) => $q->where('channel_type', $c))
                    ->when($filters['evenement'] ?? null, fn ($q, $e) => $q->where('event', $e))
                    ->latest()->paginate(30)->withQueryString()
                : null,
            'whatsappConfigured' => app(WhatsAppClient::class)->isConfigured(),
            'settings' => NotificationSettings::for(app(TenantContext::class)->id()),
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

        if ($data['type'] === ChannelType::WhatsApp->value && ! app(PlanLimits::class)->allowsWhatsApp($request->user()->agency_id)) {
            throw ValidationException::withMessages(['destination' => 'Votre formule n\'inclut pas les alertes WhatsApp (formules Pro et Business).']);
        }

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
            'notify_on_prolonged' => ['boolean'],
            'prolonged_after_minutes' => ['nullable', 'integer', Rule::in(config('uptime.notifications.prolonged_choices'))],
            'delay_minutes' => ['required', 'integer', 'between:0,120'],
        ], [], ['delay_minutes' => 'délai avant alerte']);

        NotificationPreference::updateOrCreate(
            ['notification_channel_id' => $channel->id, 'site_id' => null],
            [
                'notify_on_down' => $request->boolean('notify_on_down'),
                'notify_on_recovery' => $request->boolean('notify_on_recovery'),
                'notify_on_prolonged' => $request->boolean('notify_on_prolonged'),
                'prolonged_after_minutes' => $data['prolonged_after_minutes'] ?? 30,
                'delay_minutes' => $data['delay_minutes'],
            ],
        );

        AuditLog::record('notification_preference.updated', $channel);

        return back()->with('status', 'Préférences enregistrées.');
    }

    public function updateSettings(Request $request, TenantContext $tenant): RedirectResponse
    {
        $this->authorize('notifications.manage');

        $data = $request->validate([
            'enabled' => ['boolean'],
            'email' => ['boolean'],
            'whatsapp' => ['boolean'],
            'digest' => ['required', Rule::in(array_keys(NotificationSettings::DIGEST_CHOICES))],
        ]);

        $settings = [
            'enabled' => $request->boolean('enabled'),
            'channels' => ['email' => $request->boolean('email'), 'whatsapp' => $request->boolean('whatsapp')],
            'digest' => $data['digest'],
        ];
        NotificationSettings::save($tenant->id(), $settings);
        AuditLog::record('notification_settings.updated', null, $settings);

        return back()->with('status', 'Paramètres de notification enregistrés.');
    }

    /** Envoi réel d'un message de test ; le résultat (identifiant ou erreur) est affiché et journalisé. */
    public function test(NotificationChannel $channel, NotificationDispatcher $dispatcher, ChannelSender $sender): RedirectResponse
    {
        $this->authorize('notifications.manage');

        $log = $dispatcher->test($channel, $sender);
        AuditLog::record('notification_channel.tested', $channel, ['status' => $log->status->value]);

        return $log->status->isSuccessful()
            ? back()->with('status', "Message de test envoyé à {$channel->destination}".($log->provider_message_id ? " (identifiant : {$log->provider_message_id})." : '.').($channel->type === ChannelType::WhatsApp ? ' La remise sera confirmée dans le journal.' : ''))
            : back()->with('error', "Échec du test vers {$channel->destination} : ".($log->error_message ?? 'erreur inconnue'));
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
