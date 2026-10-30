<?php

namespace App\Http\Requests;

use App\Enums\MonitoringProvider;
use App\Models\Site;
use App\Rules\PublicHttpUrl;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SiteRequest extends FormRequest
{
    public const INTERVALS = [1, 2, 5, 10, 15, 30, 60];

    public function authorize(): bool
    {
        return $this->user()->can('sites.manage');
    }

    protected function prepareForValidation(): void
    {
        $codes = trim((string) $this->input('expected_status_codes'));

        $this->merge([
            'url' => trim((string) $this->input('url')),
            'expected_status_codes' => $codes === '' ? null : array_values(array_map(fn ($code) => ctype_digit($code) ? (int) $code : $code, array_filter(array_map('trim', explode(',', $codes)), 'strlen'))),
            'external_monitor_id' => $this->input('external_monitor_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        $agencyId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:2048', new PublicHttpUrl],
            'client_id' => ['required', 'string', Rule::exists('clients', 'id')->where('agency_id', $agencyId)],
            'interval_minutes' => ['required', 'integer', Rule::in($this->allowedIntervals())],
            'timeout_seconds' => ['required', 'integer', 'between:2,30'],
            'expected_status_codes' => ['nullable', 'array', 'max:10'],
            'expected_status_codes.*' => ['integer', 'between:100,599'],
            'monitoring_provider' => ['required', Rule::enum(MonitoringProvider::class)],
            'external_monitor_id' => [
                Rule::requiredIf(fn () => $this->input('monitoring_provider') !== MonitoringProvider::Internal->value),
                'nullable', 'string', 'max:100',
                Rule::unique('sites')->where('monitoring_provider', $this->input('monitoring_provider'))->ignore($this->route('site')),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('url')) {
                return;
            }

            $duplicate = Site::where('url_hash', Site::hashUrl($this->input('url')))
                ->when($this->route('site'), fn ($q, $site) => $q->whereKeyNot($site->id))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('url', 'Ce site est déjà surveillé par votre agence.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom du site',
            'client_id' => 'client',
            'interval_minutes' => 'intervalle de vérification',
            'timeout_seconds' => 'délai maximal',
            'expected_status_codes' => 'codes HTTP attendus',
            'expected_status_codes.*' => 'code HTTP attendu',
            'monitoring_provider' => 'fournisseur de surveillance',
            'external_monitor_id' => 'identifiant du moniteur externe',
        ];
    }

    public function messages(): array
    {
        return [
            'external_monitor_id.required' => 'L\'identifiant du moniteur est obligatoire pour un fournisseur externe.',
            'external_monitor_id.unique' => 'Ce moniteur externe est déjà rattaché à un site.',
            'interval_minutes.in' => 'Intervalle non autorisé (minimum :min minutes).',
        ];
    }

    /** @return list<int> */
    public function allowedIntervals(): array
    {
        $min = (int) config('uptime.monitoring.min_interval_minutes');

        return array_values(array_filter(self::INTERVALS, fn ($i) => $i >= $min));
    }
}
