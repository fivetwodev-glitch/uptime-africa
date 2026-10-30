@if (session('status'))
    <div class="mb-6 flex items-start gap-3 rounded-lg border border-electric-100 bg-electric-50 px-4 py-3 text-sm text-night-800" role="status">
        <x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-electric-600"/>
        <span>{{ session('status') }}</span>
    </div>
@endif
@if (session('error'))
    <div class="mb-6 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
        <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0"/>
        <span>{{ session('error') }}</span>
    </div>
@endif
