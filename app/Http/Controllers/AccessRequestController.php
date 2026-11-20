<?php

namespace App\Http\Controllers;

use App\Mail\AccessRequestReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AccessRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Campo trampa: si viene lleno, es un bot.
        if ($request->filled('website')) {
            return back()->with('access_request_sent', true);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'company' => ['nullable', 'string', 'max:160'],
            'fleet' => ['required', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [
            'required' => 'Completa este campo.',
            'email' => 'Escribe un correo válido.',
            'max' => 'Es demasiado largo.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->to(url('/').'#acceso')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();
        $data['company'] = $data['company'] ?: 'Sin empresa';

        $destination = config('mail.contact_address') ?: config('mail.from.address');

        Mail::to($destination)->send(new AccessRequestReceived($data));

        return redirect()
            ->to(url('/').'#acceso')
            ->with('access_request_sent', true);
    }
}
