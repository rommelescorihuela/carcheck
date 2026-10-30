<?php

namespace App\Services;

use App\Enums\FuelType;
use App\Enums\VehicleType;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Alta masiva de vehículos desde CSV. Una flota no se carga a mano: el archivo se procesa fila a
 * fila para poder informar qué entró y qué falló, y respeta el límite del plan.
 */
class VehicleImport
{
    public const MAX_ROWS = 500;

    /**
     * Encabezados aceptados (sin acentos ni mayúsculas) para cada campo del vehículo.
     */
    private const HEADERS = [
        'make' => ['marca', 'make', 'fabricante'],
        'model' => ['modelo', 'model'],
        'year' => ['anio', 'ano', 'year'],
        'plate' => ['placa', 'plate', 'matricula', 'patente'],
        'vin' => ['vin', 'chasis', 'serie'],
        'type' => ['tipo', 'type'],
        'fuel_type' => ['combustible', 'fuel_type', 'fuel'],
        'odometer_km' => ['kilometraje', 'km', 'odometro', 'odometer_km'],
        'color' => ['color'],
        'notes' => ['notas', 'notes', 'observaciones'],
    ];

    private const VEHICLE_TYPES = [
        'auto' => 'car', 'carro' => 'car', 'coche' => 'car', 'car' => 'car',
        'moto' => 'motorcycle', 'motocicleta' => 'motorcycle', 'motorcycle' => 'motorcycle',
        'camion' => 'truck', 'camioneta' => 'truck', 'truck' => 'truck',
        'otro' => 'other', 'other' => 'other',
    ];

    private const FUEL_TYPES = [
        'gasolina' => 'gasoline', 'gasoline' => 'gasoline',
        'diesel' => 'diesel', 'gasoil' => 'diesel',
        'gnc' => 'cng', 'cng' => 'cng', 'gas' => 'cng',
        'electrico' => 'electric', 'electric' => 'electric',
        'hibrido' => 'hybrid', 'hybrid' => 'hybrid',
    ];

    public function __construct(private readonly Billing $billing) {}

    /**
     * @return array{
     *     created: int,
     *     failed: int,
     *     limit: int|null,
     *     remaining: int|null,
     *     errors: array<int, array{line: int, message: string}>
     * }
     */
    public function import(UploadedFile $file, User $user, User|Organization $owner): array
    {
        $remaining = $this->billing->remainingVehicles($owner);
        $limit = $this->billing->planFor($owner)?->max_vehicles;

        [$header, $rows] = $this->parse($file);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => ['El archivo no tiene filas de datos.'],
            ]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => ['El archivo supera las '.self::MAX_ROWS.' filas. Divídelo en varios.'],
            ]);
        }

        $columns = $this->columns($header);

        if (! isset($columns['make'], $columns['model'])) {
            throw ValidationException::withMessages([
                'file' => ['El CSV necesita una columna "marca" y otra "modelo".'],
            ]);
        }

        $created = 0;
        $errors = [];
        $seenVin = [];

        foreach ($rows as $index => $row) {
            // La fila 1 es el encabezado. Se fuerza a entero para que el JSON no cambie de tipo.
            $line = (int) $index + 2;
            $data = $this->row($row, $columns);

            if ($data === []) {
                continue;
            }

            if ($remaining !== null && $created >= $remaining) {
                $errors[] = [
                    'line' => $line,
                    'message' => "El plan permite {$limit} vehículos. Mejora el plan para importar más.",
                ];

                continue;
            }

            if (isset($data['vin']) && isset($seenVin[$data['vin']])) {
                $errors[] = ['line' => $line, 'message' => 'El VIN está repetido en el archivo.'];

                continue;
            }

            $validator = Validator::make($data, [
                'make' => 'required|string|max:100',
                'model' => 'required|string|max:100',
                'year' => 'nullable|integer|between:1900,'.(date('Y') + 1),
                'plate' => 'nullable|string|max:16',
                'vin' => ['nullable', 'string', 'max:32', Rule::unique('vehicles', 'vin')],
                'type' => ['sometimes', Rule::enum(VehicleType::class)],
                'fuel_type' => ['sometimes', Rule::enum(FuelType::class)],
                'odometer_km' => 'nullable|integer|min:0',
                'color' => 'nullable|string|max:40',
                'notes' => 'nullable|string|max:2000',
            ]);

            if ($validator->fails()) {
                $errors[] = ['line' => $line, 'message' => (string) $validator->errors()->first()];

                continue;
            }

            $vehicle = new Vehicle($validator->validated());
            $vehicle->owner()->associate($owner);
            $vehicle->save();

            if (isset($data['vin'])) {
                $seenVin[$data['vin']] = true;
            }

            $created++;
        }

        return [
            'created' => $created,
            'failed' => count($errors),
            'limit' => $limit,
            'remaining' => $remaining === null ? null : max(0, $remaining - $created),
            'errors' => $errors,
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|null>>}
     */
    private function parse(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages(['file' => ['No se pudo leer el archivo.']]);
        }

        $content = (string) file_get_contents($path);

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $firstLine = (string) fgets($handle);
        rewind($handle);

        $delimiter = $this->delimiter($firstLine);
        $header = array_map(fn ($value): string => $this->normalize((string) $value), (array) fgetcsv($handle, 0, $delimiter));

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $values = array_map(fn ($value): string => trim((string) $value), $row);

            if (implode('', $values) === '') {
                continue;
            }

            $rows[] = $values;
        }

        fclose($handle);

        return [$header, $rows];
    }

    private function delimiter(string $line): string
    {
        return match (true) {
            substr_count($line, ';') > substr_count($line, ',') => ';',
            substr_count($line, "\t") > substr_count($line, ',') => "\t",
            default => ',',
        };
    }

    /**
     * Índice canónico => posición en el CSV.
     *
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function columns(array $header): array
    {
        $aliases = [];

        foreach (self::HEADERS as $field => $names) {
            foreach ($names as $name) {
                $aliases[$name] = $field;
            }
        }

        $columns = [];

        foreach ($header as $position => $name) {
            if (isset($aliases[$name]) && ! isset($columns[$aliases[$name]])) {
                $columns[$aliases[$name]] = $position;
            }
        }

        return $columns;
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $columns
     * @return array<string, mixed>
     */
    private function row(array $row, array $columns): array
    {
        $data = [];

        foreach ($columns as $field => $position) {
            $value = trim((string) ($row[$position] ?? ''));

            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        if (isset($data['type'])) {
            $key = $this->normalize((string) $data['type']);
            $data['type'] = self::VEHICLE_TYPES[$key] ?? $key;
        }

        if (isset($data['fuel_type'])) {
            $key = $this->normalize((string) $data['fuel_type']);
            $data['fuel_type'] = self::FUEL_TYPES[$key] ?? $key;
        }

        return $data;
    }

    private function normalize(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i',
            'ò' => 'o', 'ù' => 'u',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $value), '_');
    }
}
