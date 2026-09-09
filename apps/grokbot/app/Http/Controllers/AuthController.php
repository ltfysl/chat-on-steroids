<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class AuthController
{
    public function login(): Response { return Inertia::render('Auth/Login'); }
    public function register(): Response { return Inertia::render('Auth/Register'); }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials do not match our records.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'], 'email' => ['required','email','max:255','unique:users'],
            'password' => ['required','confirmed', Password::min(12)], 'workspace' => ['required','string','max:120'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
            $workspace = Workspace::query()->create(['public_id' => (string) Str::uuid(), 'name' => $data['workspace'], 'slug' => Str::slug($data['workspace']).'-'.Str::lower(Str::random(5)), 'owner_id' => $user->id]);
            $workspace->users()->attach($user, ['role' => 'owner']);
            return $user;
        });

        Auth::login($user); $request->session()->regenerate();
        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
