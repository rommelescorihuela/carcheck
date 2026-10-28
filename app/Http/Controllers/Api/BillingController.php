<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing;
use App\Services\Ownership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return PlanResource::collection($plans)->response();
    }

    public function show(Request $request): JsonResponse
    {
        $ownership = app(Ownership::class);
        $billing = app(Billing::class);

        $owner = $ownership->resolve($request);
        $subscription = $billing->currentFor($owner);

        return response()->json([
            'data' => [
                'subscription' => $subscription === null
                    ? null
                    : SubscriptionResource::make($subscription)->resolve($request),
                'usage' => $billing->usage($owner),
                'can_manage' => $ownership->canManage($request->user(), $owner),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
        ]);

        $owner = $this->manageableOwner($request);
        $plan = Plan::findOrFail($validated['plan_id']);

        $subscription = app(Billing::class)->subscribe($owner, $plan);

        return (new SubscriptionResource($subscription))->response()->setStatusCode(201);
    }

    public function update(Request $request): SubscriptionResource
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
        ]);

        $owner = $this->manageableOwner($request);
        $subscription = $this->currentSubscription($owner);

        return new SubscriptionResource(
            app(Billing::class)->changePlan($subscription, Plan::findOrFail($validated['plan_id'])),
        );
    }

    public function destroy(Request $request): SubscriptionResource
    {
        $owner = $this->manageableOwner($request);

        return new SubscriptionResource(app(Billing::class)->cancel($this->currentSubscription($owner)));
    }

    private function manageableOwner(Request $request): User|Organization
    {
        $ownership = app(Ownership::class);
        $owner = $ownership->resolve($request);

        abort_unless($ownership->canManage($request->user(), $owner), 403);

        return $owner;
    }

    private function currentSubscription(User|Organization $owner): Subscription
    {
        $subscription = app(Billing::class)->currentFor($owner);

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'subscription' => 'Primero elige un plan.',
            ]);
        }

        return $subscription;
    }
}
