<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Inactive users do not receive reset links.
        Password::sendResetLink(
            $request->only('email') + ['activo' => true]
        );

        // Same answer whether the email exists or not, so the form does not reveal registered users.
        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
