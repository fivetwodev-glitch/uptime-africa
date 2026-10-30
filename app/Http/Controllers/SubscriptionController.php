<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('subscriptions.view');

        $agency = $request->user()->agency;

        return view('subscription.show', [
            'subscription' => $agency->currentSubscription()->with('plan')->first(),
            'history' => $agency->subscriptions()->with('plan:id,name')->latest('starts_at')->limit(10)->get(),
            'plans' => Plan::where('is_public', true)->where('is_active', true)->orderBy('monthly_price_xof')->get(),
            'sitesCount' => Site::count(),
            'usersCount' => User::where('agency_id', $agency->id)->count(),
            'supportEmail' => config('uptime.support_email'),
        ]);
    }
}
