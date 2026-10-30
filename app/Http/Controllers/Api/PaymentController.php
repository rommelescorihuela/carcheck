<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing;
use App\Services\Ownership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $owner = app(Ownership::class)->resolve($request);
        $subscription = app(Billing::class)->currentFor($owner);

        $payments = Payment::query()
            ->where('subscription_id', $subscription?->getKey())
            ->latest('paid_on')
            ->latest('id')
            ->paginate(20);

        return PaymentResource::collection($payments)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $ownership = app(Ownership::class);
        $owner = $ownership->resolve($request);
        abort_unless($ownership->canManage($request->user(), $owner), 403);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0.0001',
            'method' => ['required', Rule::in(['transfer', 'mobile_payment', 'zelle', 'binance', 'cash', 'other'])],
            'reference' => 'nullable|string|max:100',
            'paid_on' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
        ]);

        unset($validated['organization_id']);

        $subscription = $this->currentSubscription($owner);
        $planCurrency = strtoupper((string) $subscription->plan->currency);
        $currency = strtoupper($validated['currency']);

        // Si paga en otra moneda, la tasa es obligatoria: sin ella el equivalente no es fiable.
        if ($currency !== $planCurrency && blank($validated['exchange_rate'] ?? null)) {
            throw ValidationException::withMessages([
                'exchange_rate' => ['Indica la tasa de cambio de tu moneda frente a '.$planCurrency.'.'],
            ]);
        }

        $validated['currency'] = $currency;
        $validated['exchange_rate'] = $currency === $planCurrency ? 1 : $validated['exchange_rate'];

        $payment = $subscription->payments()->create($validated + [
            'status' => PaymentStatus::Pending,
        ]);

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    public function storeProof(Request $request, Payment $payment): PaymentResource
    {
        $this->authorize('update', $payment);

        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,webp,pdf|max:10240',
        ]);

        // La colección es single file: subir otro reemplaza el comprobante anterior.
        $payment->addMediaFromRequest('file')->toMediaCollection('proof');

        return new PaymentResource($payment->fresh());
    }

    public function destroyProof(Request $request, Payment $payment): PaymentResource
    {
        $this->authorize('update', $payment);

        $payment->clearMediaCollection('proof');

        return new PaymentResource($payment->fresh());
    }

    public function approve(Request $request, Payment $payment): PaymentResource
    {
        $this->authorize('review', $payment);

        return new PaymentResource(app(Billing::class)->approvePayment($payment, $request->user()));
    }

    public function reject(Request $request, Payment $payment): PaymentResource
    {
        $this->authorize('review', $payment);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        return new PaymentResource(
            app(Billing::class)->rejectPayment($payment, $request->user(), $validated['reason'] ?? null),
        );
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
