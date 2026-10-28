<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\ReminderKind;
use App\Models\Document;
use App\Models\MaintenanceType;
use App\Models\Reminder;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Deriva los recordatorios de cada vehículo desde documentos y planes de mantenimiento. La
 * condición se recalcula siempre; la fila persistida solo sirve para no reavisar y recordar los
 * descartes.
 */
class ReminderGenerator
{
    public function __construct(
        private readonly MaintenanceSchedule $schedule,
        private readonly DocumentStatus $documents,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  Builder<Vehicle>  $vehicles
     * @return int recordatorios creados
     */
    public function generateAll(Builder $vehicles): int
    {
        $created = 0;

        $vehicles->chunkById(100, function (Collection $chunk) use (&$created): void {
            foreach ($chunk as $vehicle) {
                $created += $this->generate($vehicle);
            }
        });

        return $created;
    }

    /**
     * Sincroniza un vehículo: crea lo que falta, avisa una vez y borra lo que ya no aplica.
     *
     * @return int recordatorios creados
     */
    public function generate(Vehicle $vehicle): int
    {
        $candidates = $this->candidates($vehicle);
        $fingerprints = array_map(fn (array $candidate): string => $candidate['fingerprint'], $candidates);

        // Renovar el documento o hacer el servicio deja huérfano el recordatorio anterior.
        $vehicle->reminders()->whereNotIn('fingerprint', $fingerprints)->delete();

        $created = 0;

        foreach ($candidates as $candidate) {
            $reminder = $vehicle->reminders()->firstOrNew(['fingerprint' => $candidate['fingerprint']]);
            $isNew = ! $reminder->exists;

            // Los campos visibles se refrescan en cada pasada: un recordatorio que pasa a vencido
            // cambia de título/cuerpo aunque conserve su huella. Lo único persistente es el aviso.
            $reminder->fill([
                'kind' => $candidate['kind'],
                'source_type' => $candidate['source_type'],
                'source_id' => $candidate['source_id'],
                'title' => $candidate['title'],
                'body' => $candidate['body'],
                'due_on' => $candidate['due_on']?->toDateString(),
                'due_km' => $candidate['due_km'],
            ])->save();

            if ($isNew) {
                $created++;
                $overdue = $reminder->isOverdue($vehicle);
                $this->notify($vehicle, $reminder);

                $reminder->forceFill([
                    'notified_at' => now(),
                    // Si nace ya vencido no tiene sentido un aviso "próximo" después.
                    'overdue_notified_at' => $overdue ? now() : null,
                ])->save();

                continue;
            }

            if (! $reminder->isOverdue($vehicle) || $reminder->overdue_notified_at !== null) {
                continue;
            }

            $this->notify($vehicle, $reminder);
            $reminder->forceFill(['overdue_notified_at' => now()])->save();
        }

        return $created;
    }

    /**
     * @return array<int, array{
     *     kind: ReminderKind,
     *     source_type: string,
     *     source_id: int|string,
     *     fingerprint: string,
     *     title: string,
     *     body: string,
     *     due_on: ?CarbonInterface,
     *     due_km: ?int
     * }>
     */
    private function candidates(Vehicle $vehicle): array
    {
        return array_merge(
            $this->documentCandidates($vehicle),
            $this->maintenanceCandidates($vehicle),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentCandidates(Vehicle $vehicle): array
    {
        $candidates = [];

        foreach ($vehicle->documents()->get() as $document) {
            $state = $this->documents->for($document->expires_on);

            if ($state['days_left'] === null || $state['status'] === 'valid') {
                continue;
            }

            $daysLeft = $state['days_left'];
            $label = $document->kind->label();

            $candidates[] = [
                'kind' => ReminderKind::Document,
                'source_type' => $document->getMorphClass(),
                'source_id' => $document->getKey(),
                'fingerprint' => $this->fingerprint(
                    ReminderKind::Document,
                    $document->getMorphClass(),
                    $document->getKey(),
                    $document->expires_on->toDateString(),
                    null,
                ),
                'title' => $label.($daysLeft < 0 ? ': vencido' : ': por vencer'),
                'body' => $this->documentBody($vehicle, $document, $daysLeft),
                'due_on' => CarbonImmutable::parse($document->expires_on->toDateString()),
                'due_km' => null,
            ];
        }

        return $candidates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function maintenanceCandidates(Vehicle $vehicle): array
    {
        $candidates = [];

        foreach ($this->schedule->dueFor($vehicle) as $due) {
            if (! in_array($due['status'], ['soon', 'due'], true)) {
                continue;
            }

            $type = MaintenanceType::query()->find($due['maintenance_type']['id']);

            if ($type === null) {
                continue;
            }

            $dueOn = $due['due_date'] === null ? null : CarbonImmutable::parse($due['due_date']);

            $candidates[] = [
                'kind' => ReminderKind::Maintenance,
                'source_type' => $type->getMorphClass(),
                'source_id' => $type->getKey(),
                'fingerprint' => $this->fingerprint(
                    ReminderKind::Maintenance,
                    $type->getMorphClass(),
                    $type->getKey(),
                    $dueOn?->toDateString(),
                    $due['due_km'],
                ),
                'title' => $type->name.($due['status'] === 'due' ? ': vencido' : ': próximo'),
                'body' => $this->maintenanceBody($vehicle, $type, $dueOn, $due['due_km']),
                'due_on' => $dueOn,
                'due_km' => $due['due_km'],
            ];
        }

        return $candidates;
    }

    private function documentBody(Vehicle $vehicle, Document $document, int $daysLeft): string
    {
        $date = $document->expires_on->format('d/m/Y');

        $when = $daysLeft < 0
            ? sprintf('venció el %s (hace %d días)', $date, abs($daysLeft))
            : sprintf('vence el %s (%d días)', $date, $daysLeft);

        return sprintf('%s: %s. Vehículo: %s.', $document->name, $when, $this->vehicleLabel($vehicle));
    }

    private function maintenanceBody(
        Vehicle $vehicle,
        MaintenanceType $type,
        ?CarbonInterface $dueOn,
        ?int $dueKm,
    ): string {
        $parts = [];

        if ($dueKm !== null) {
            $remaining = $dueKm - (int) $vehicle->odometer_km;
            $parts[] = $remaining > 0 ? "faltan {$remaining} km" : 'toca por kilometraje';
        }

        if ($dueOn !== null) {
            $days = (int) now()->startOfDay()->diffInDays($dueOn->copy()->startOfDay(), false);

            $parts[] = match (true) {
                $days < 0 => 'plazo vencido hace '.abs($days).' días',
                $days === 0 => 'vence hoy',
                default => "faltan {$days} días",
            };
        }

        $detail = $parts === [] ? 'Servicio pendiente' : ucfirst(implode(' y ', $parts));

        return sprintf('%s. Vehículo: %s.', $detail, $this->vehicleLabel($vehicle));
    }

    private function vehicleLabel(Vehicle $vehicle): string
    {
        $label = trim("{$vehicle->make} {$vehicle->model}");

        return $vehicle->plate === null ? $label : "{$label} ({$vehicle->plate})";
    }

    private function fingerprint(
        ReminderKind $kind,
        string $sourceType,
        int|string $sourceId,
        ?string $dueOn,
        ?int $dueKm,
    ): string {
        return sha1(implode('|', [
            $kind->value,
            $sourceType,
            (string) $sourceId,
            $dueOn ?? '-',
            $dueKm === null ? '-' : (string) $dueKm,
        ]));
    }

    private function notify(Vehicle $vehicle, Reminder $reminder): void
    {
        $this->notifier->sendToMany(
            $this->recipients($vehicle),
            NotificationCategory::Reminders,
            $reminder->title,
            $reminder->body,
            ['reminder_id' => $reminder->getKey(), 'vehicle_id' => $vehicle->getKey()],
            '/recordatorios',
        );
    }

    /**
     * @return iterable<User>
     */
    private function recipients(Vehicle $vehicle): iterable
    {
        $owner = $vehicle->owner;

        return $owner instanceof User ? [$owner] : $owner->members()->get();
    }
}
