<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RecaptchaService
{
    public function verify(string $token, string $action): bool
    {
        if (!$token) {
            return false;
        }

        $response = Http::asForm()->post(
            'https://www.google.com/recaptcha/api/siteverify',
            [
                'secret'   => config('services.recaptcha.secret'),
                'response' => $token,
            ]
        );

        if (!$response->successful()) {
            return false;
        }

        $data = $response->json();

        return $data['success'] === true
            && $data['action'] === $action
            && ($data['score'] ?? 0) >= config('services.recaptcha.min_score');
    }
}