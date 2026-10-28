<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Regresión: con `APP_LOCALE=es` y sin `lang/es/`, Laravel devolvía las claves crudas
 * (`validation.min.numeric`) en vez del mensaje. Estos tests fijan que haya traducción real.
 */
class LocalizationTest extends TestCase
{
    public function test_validation_messages_are_translated_to_spanish(): void
    {
        $errors = Validator::make(
            ['liters' => 0],
            ['liters' => 'required|numeric|min:0.01'],
        )->errors()->toArray();

        $this->assertSame(
            ['El campo litros debe ser al menos 0.01.'],
            $errors['liters'],
        );
    }

    public function test_validation_message_never_exposes_a_raw_key(): void
    {
        $cases = [
            [['filled_on' => null], ['filled_on' => 'required|date']],
            [['email' => 'no-es-correo'], ['email' => 'email']],
            [['odometer_km' => 'x'], ['odometer_km' => 'integer|min:0']],
            [['currency' => 'USDX'], ['currency' => 'size:3']],
        ];

        foreach ($cases as [$data, $rules]) {
            $messages = Validator::make($data, $rules)->errors()->all();

            foreach ($messages as $message) {
                $this->assertStringNotContainsString('validation.', $message);
                $this->assertStringContainsString('El campo', $message);
            }
        }
    }

    public function test_registration_validation_errors_are_spanish(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'no-es-correo',
            'password' => 'corta',
            'password_confirmation' => 'otra',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertSame(
            ['El campo nombre es obligatorio.'],
            $response->json('errors.name'),
        );
    }
}
