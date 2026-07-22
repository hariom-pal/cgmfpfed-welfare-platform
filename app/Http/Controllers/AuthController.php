<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\CscConnectServiceInterface;
use App\Models\User;
use App\Services\SessionService;
use App\Support\LegacyPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AuthController extends Controller
{
    public function __construct(
        private readonly CscConnectServiceInterface $cscConnectService,
        private readonly SessionService $sessions,
    ) {}

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $user = User::query()
            ->where('email', $credentials['username'])
            ->orWhere('mobile', $credentials['username'])
            ->first();

        if ($user === null) {
            return back()->withErrors(['username' => 'Invalid username or password.'])->onlyInput('username');
        }

        if (! $user->isActive()) {
            return back()->withErrors(['username' => 'This user account is disabled.'])->onlyInput('username');
        }

        if (! LegacyPassword::verify($credentials['password'], $user->password)) {
            $user->increment('fail_attempt');

            return back()->withErrors(['username' => 'Invalid username or password.'])->onlyInput('username');
        }

        $user->forceFill(['fail_attempt' => 0])->save();

        Auth::login($user, (bool) ($credentials['remember'] ?? false));
        $this->sessions->storeLegacyKeys($request, $user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function checkLogin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $payload = $this->decryptLegacyPayload($validated['message']);

        if ($payload === null) {
            return redirect()->route('login')->withErrors(['username' => 'Invalid legacy login payload.']);
        }

        $user = User::query()
            ->where('id', $payload['USER_ID'] ?? null)
            ->orWhere('email', $payload['EMAIL'] ?? null)
            ->first();

        if ($user === null || ! $user->isActive()) {
            return redirect()->route('login')->withErrors(['username' => 'Legacy user is not active in this portal.']);
        }

        Auth::login($user);
        $this->sessions->storeLegacyKeys($request, $user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function redirectToCsc(Request $request): RedirectResponse
    {
        return redirect()->away($this->cscConnectService->authorizationUrl($request));
    }

    public function cscCallback(Request $request): RedirectResponse
    {
        $user = $this->cscConnectService->authenticateCallback($request);

        Auth::login($user);
        $this->sessions->storeLegacyKeys($request, $user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $this->sessions->clearLegacyKeys($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Signed out successfully.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decryptLegacyPayload(string $encryptedMessage): ?array
    {
        $json = openssl_decrypt(
            $encryptedMessage,
            'AES-128-CTR',
            'HariomPal',
            0,
            '1234567891011121',
        );

        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }
}
