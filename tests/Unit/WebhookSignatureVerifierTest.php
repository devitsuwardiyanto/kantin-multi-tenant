<?php

namespace Tests\Unit;

use App\Modules\Payments\Support\WebhookSignatureVerifier;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookSignatureVerifierTest extends TestCase
{
    public function test_accepts_matching_hmac_and_rejects_tampered(): void
    {
        Config::set('services.qris.webhook_secret', 'rahasia');
        $verifier = new WebhookSignatureVerifier;
        $body = '{"event_id":"e1","status":"success"}';

        $this->assertTrue($verifier->verify($body, $verifier->sign($body)));
        $this->assertFalse($verifier->verify($body.'x', $verifier->sign($body)), 'body diubah → tolak');
        $this->assertFalse($verifier->verify($body, 'salah'));
    }

    public function test_fails_closed_without_secret_or_signature(): void
    {
        Config::set('services.qris.webhook_secret', null);
        $verifier = new WebhookSignatureVerifier;

        $this->assertFalse($verifier->verify('{}', 'apa pun'), 'tanpa secret → tolak');

        Config::set('services.qris.webhook_secret', 'rahasia');
        $this->assertFalse($verifier->verify('{}', null), 'tanpa signature → tolak');
    }
}
