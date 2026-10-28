<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReminderResource;
use App\Models\Organization;
use App\Models\Reminder;
use App\Models\Vehicle;
use App\Services\ReminderGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reminders = $this->query($request)
            ->with('vehicle')
            ->get()
            // Vencidos primero y, dentro de cada grupo, lo más urgente delante.
            ->sortBy(fn (Reminder $reminder): array => [
                $reminder->status($reminder->vehicle) === 'overdue' ? 0 : 1,
                $reminder->daysLeft() ?? PHP_INT_MAX,
                $reminder->kmLeft($reminder->vehicle) ?? PHP_INT_MAX,
            ])
            ->values();

        return response()->json([
            'data' => ReminderResource::collection($reminders)->resolve($request),
            'meta' => $this->meta($reminders),
        ]);
    }

    public function dismiss(Request $request, Reminder $reminder): JsonResponse
    {
        // 404 y no 403: no se revela la existencia de recordatorios de vehículos ajenos.
        abort_unless($reminder->vehicle->isAccessibleBy($request->user()), 404);

        $reminder->dismissals()->firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json(['message' => 'Recordatorio descartado.']);
    }

    public function sync(Request $request): JsonResponse
    {
        $created = app(ReminderGenerator::class)->generateAll($this->vehicles($request));

        return response()->json([
            'message' => $created === 1
                ? 'Se creó 1 recordatorio.'
                : "Se crearon {$created} recordatorios.",
            'data' => [
                'created' => $created,
                'total' => $this->query($request)->count(),
            ],
        ]);
    }

    /**
     * @return Builder<Reminder>
     */
    private function query(Request $request): Builder
    {
        return Reminder::query()
            ->whereIn('vehicle_id', $this->vehicles($request)->select('id'))
            ->whereDoesntHave('dismissals', function (Builder $query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            });
    }

    /**
     * Vehículos del ámbito pedido, con el mismo criterio que `vehicles`.
     *
     * @return Builder<Vehicle>
     */
    private function vehicles(Request $request): Builder
    {
        $user = $request->user();
        $query = Vehicle::query();

        if ($request->filled('organization_id')) {
            $organization = Organization::findOrFail((int) $request->input('organization_id'));
            abort_unless($organization->hasMember($user), 403);

            return $query->where('owner_type', $organization->getMorphClass())
                ->where('owner_id', $organization->getKey());
        }

        if ($request->input('scope') === 'personal') {
            return $query->where('owner_type', $user->getMorphClass())
                ->where('owner_id', $user->getKey());
        }

        return $query->accessibleBy($user);
    }

    /**
     * @param  Collection<int, Reminder>  $reminders
     * @return array{overdue: int, upcoming: int, total: int}
     */
    private function meta(Collection $reminders): array
    {
        $overdue = $reminders
            ->filter(fn (Reminder $reminder): bool => $reminder->status($reminder->vehicle) === 'overdue')
            ->count();

        return [
            'overdue' => $overdue,
            'upcoming' => $reminders->count() - $overdue,
            'total' => $reminders->count(),
        ];
    }
}
