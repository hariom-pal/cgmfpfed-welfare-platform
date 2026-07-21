<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\CscConnectServiceInterface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CscConnectService implements CscConnectServiceInterface
{
    public function authorizationUrl(Request $request): string
    {
        $state = (string) random_int(10000, 99999);
        $request->session()->put('connect_state', $state);

        return config('csc.connect.authorization_endpoint').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('csc.connect.client_id'),
            'redirect_uri' => config('csc.connect.redirect_uri'),
            'state' => $state,
        ]);
    }

    public function authenticateCallback(Request $request): User
    {
        $state = (string) $request->query('state', '');
        $expectedState = (string) $request->session()->pull('connect_state', '');

        if ($expectedState !== '' && $state !== $expectedState) {
            throw ValidationException::withMessages(['csc' => 'STATE mismatch from CSC Connect.']);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            throw ValidationException::withMessages(['csc' => 'CSC Connect did not return an authorization code.']);
        }

        $token = $this->fetchAccessToken($code);
        $profile = $this->fetchProfile($token);
        $cscUser = $profile['User'] ?? null;

        if (! is_array($cscUser) && ! is_object($cscUser)) {
            throw ValidationException::withMessages(['csc' => 'CSC Connect did not return a valid user profile.']);
        }

        $cscUser = (array) $cscUser;
        $cscId = (string) ($cscUser['csc_id'] ?? '');

        if ($cscId === '') {
            throw ValidationException::withMessages(['csc' => 'CSC Connect profile is missing CSC ID.']);
        }

        return User::query()->updateOrCreate(
            ['csc_id' => $cscId],
            [
                'name' => (string) ($cscUser['fullname'] ?? $cscUser['name'] ?? $cscId),
                'email' => $cscUser['email'] ?? null,
                'mobile' => $cscUser['mobile'] ?? $cscUser['phone'] ?? null,
                'password' => bcrypt(Str::random(40)),
                'status' => '1',
                'user_type' => (int) config('csc.vle_role_id'),
                'csc_payload' => $cscUser,
            ],
        );
    }

    private function fetchAccessToken(string $code): string
    {
        $response = Http::asForm()->post((string) config('csc.connect.token_endpoint'), [
            'code' => $code,
            'redirect_uri' => config('csc.connect.redirect_uri'),
            'grant_type' => 'authorization_code',
            'client_id' => config('csc.connect.client_id'),
            'client_secret' => $this->encryptClientSecret((string) config('csc.connect.client_secret')),
        ]);

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw ValidationException::withMessages(['csc' => 'CSC Connect token exchange failed.']);
        }

        return $accessToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchProfile(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->post((string) config('csc.connect.resource_url'), [
            'access_token' => $accessToken,
        ]);

        $profile = $response->json();

        return is_array($profile) ? $profile : [];
    }

    private function encryptClientSecret(string $secret): string
    {
        $token = (string) config('csc.connect.client_token');
        if ($secret === '' || $token === '') {
            return $secret;
        }

        $plaintext = random_int(10, 99).':'.$secret.'@'.random_int(10, 99);
        $padding = 16 - (strlen($plaintext) % 16);
        $padded = $plaintext.str_repeat(chr($padding), $padding);
        $encrypted = openssl_encrypt($padded, 'AES-128-CBC', $token, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, '0000000000000000');

        return $encrypted === false ? $secret : bin2hex($encrypted);
    }
}
