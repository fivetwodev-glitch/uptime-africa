@php
    $classes = match ($log->status) {
        \App\Enums\NotificationStatus::Sent, \App\Enums\NotificationStatus::Delivered, \App\Enums\NotificationStatus::Read => 'bg-emerald-50 text-emerald-700',
        \App\Enums\NotificationStatus::Failed => 'bg-rose-50 text-rose-700',
        \App\Enums\NotificationStatus::Queued, \App\Enums\NotificationStatus::Sending => 'bg-electric-50 text-electric-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp
<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $classes }}" @if ($log->error_message) title="{{ $log->error_message }}" @endif>{{ $log->status->label() }}</span>
