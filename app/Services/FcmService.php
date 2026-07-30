<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications through the Firebase Cloud Messaging HTTP v1 API.
 *
 * Configure via .env (see config/services.php):
 *   FCM_PROJECT_ID    - Firebase project id
 *   FCM_CLIENT_EMAIL  - service account client_email
 *   FCM_PRIVATE_KEY   - service account private_key (with \n for newlines)
 *
 * These three values come straight out of the service account JSON you
 * download from Firebase Console > Project Settings > Service Accounts >
 * "Generate new private key". No external composer package is required -
 * the OAuth2 JWT is built and signed by hand with openssl.
 */
class FcmService
{
    /** Exchange the service account credentials for a short-lived OAuth2 access token (cached). */
    protected function getAccessToken(): ?string
    {
        $clientEmail = config('services.fcm.client_email');
        $privateKey = config('services.fcm.private_key');

        if (! $clientEmail || ! $privateKey) {
            Log::warning('FCM not configured: missing client_email/private_key.');
            return null;
        }

        return Cache::remember('fcm_access_token', 3300, function () use ($clientEmail, $privateKey) {
            $privateKey = str_replace('\\n', "\n", $privateKey);
            $now = time();

            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claimSet = $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signingInput = "{$header}.{$claimSet}";
            $signature = '';
            $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

            if (! $signed) {
                Log::error('FCM: failed to sign JWT with service account private key.');
                return null;
            }

            $jwt = $signingInput.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::warning('FCM: OAuth2 token exchange failed.', ['body' => $response->body()]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send the same notification to a list of device tokens.
     *
     * $data must be a flat array of string => string|scalar (FCM's "data"
     * payload only supports string values). $badge, if given, sets the
     * iOS app-icon badge count (Android badge is derived from the number
     * of active notifications by the OS / launcher automatically, but we
     * also pass a hint via the "badge" data key for the app to apply
     * flutter_app_badger/app_badge_plus on both platforms).
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = [], ?int $badge = null): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (empty($tokens)) {
            return;
        }

        $projectId = config('services.fcm.project_id');
        $accessToken = $this->getAccessToken();
        if (! $projectId || ! $accessToken) {
            return;
        }

        $stringData = array_map(
            fn ($v) => is_string($v) ? $v : (is_scalar($v) ? (string) $v : json_encode($v)),
            $data
        );
        if ($badge !== null) {
            $stringData['badge'] = (string) $badge;
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach ($tokens as $token) {
            try {
                $response = Http::withToken($accessToken)->post($endpoint, [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $stringData,
                        'android' => [
                            'priority' => 'high',
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => array_filter([
                                    'sound' => 'default',
                                    'badge' => $badge,
                                ], fn ($v) => $v !== null),
                            ],
                        ],
                    ],
                ]);

                if ($response->failed()) {
                    $status = $response->json('error.status');
                    // Token no longer valid on Google's end - stop trying to use it.
                    if (in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                        DeviceToken::where('token', $token)->delete();
                    }
                    Log::warning('FCM send failed.', ['token' => $token, 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('FCM send threw an exception.', ['error' => $e->getMessage()]);
            }
        }
    }
}
