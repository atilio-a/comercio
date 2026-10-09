<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\RegistradorAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, RegistradorAuditoria $auditoria): RedirectResponse
    {
        $user = $request->user()->fill($request->validated());
        $cambios = $user->getDirty();

        if ($cambios !== []) {
            DB::transaction(function () use ($user, $cambios, $auditoria) {
                $antes = array_intersect_key($user->getOriginal(), $cambios);
                $user->save();

                $auditoria->registrar('usuario.perfil_actualizado', $user, $antes, $cambios);
            });
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }
}
