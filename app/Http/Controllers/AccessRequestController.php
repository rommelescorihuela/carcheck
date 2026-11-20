<?php

namespace App\Http\Controllers;

use App\Mail\AccessRequestReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AccessRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Campo trampa: si viene lleno, es un bot.
        if ($request->filled('website')) {
            return back()->with('access_request_sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'company' => ['required', 'string', 'max:160'],
            'fleet' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $destination = config('mail.contact_address') ?: config('mail.from.address');

        Mail::to($destination)->send(new AccessRequestReceived($data));

        return redirect()
            ->to(url('/').'#acceso')
            ->with('access_request_sent', true);
    }
}
