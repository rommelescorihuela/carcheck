<?php

namespace Tests\Feature;

use App\Mail\AccessRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccessRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_landing_ofrece_un_camino_para_pedir_acceso(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Solicitar acceso')
            ->assertSee('Empieza por el vehículo que más te preocupa');
    }

    public function test_una_solicitud_valida_se_envia_por_correo(): void
    {
        Mail::fake();

        $this->post('/solicitar-acceso', [
            'name' => 'Ana Pérez',
            'email' => 'ana@flota.test',
            'company' => 'Transportes Delta',
            'fleet' => '6 a 25',
            'message' => 'Tenemos 12 camionetas.',
        ])->assertRedirect(url('/').'#acceso')
            ->assertSessionHas('access_request_sent', true);

        Mail::assertSent(AccessRequestReceived::class, function (AccessRequestReceived $mail): bool {
            return $mail->request['company'] === 'Transportes Delta'
                && $mail->hasTo(config('mail.contact_address') ?: config('mail.from.address'));
        });
    }

    public function test_la_solicitud_valida_los_datos(): void
    {
        Mail::fake();

        $this->post('/solicitar-acceso', ['name' => '', 'email' => 'no-es-correo'])
            ->assertSessionHasErrors(['name', 'email', 'company']);

        Mail::assertNothingSent();
    }

    public function test_el_campo_trampa_no_envia_correo(): void
    {
        Mail::fake();

        $this->post('/solicitar-acceso', [
            'name' => 'Bot',
            'email' => 'bot@spam.test',
            'company' => 'Spam',
            'website' => 'http://spam.test',
        ])->assertRedirect();

        Mail::assertNothingSent();
    }
}
