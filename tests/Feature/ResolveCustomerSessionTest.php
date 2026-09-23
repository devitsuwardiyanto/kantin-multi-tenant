<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CustomerSession;
use App\Modules\Ordering\Services\ResolveCustomerSession;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveCustomerSessionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: CustomerSession, 1: string} */
    private function makeSession(string $status = 'active', bool $expired = false): array
    {
        $token = OpaqueToken::issue(32);
        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => Canteen::factory()->create()->id,
            'session_token_hash' => $token['hash'],
            'status' => $status,
            'expires_at' => $expired ? now()->subMinute() : now()->addHours(4),
        ])->save();

        return [$session, $token['plain']];
    }

    public function test_resolves_active_session_from_plain_token(): void
    {
        [$session, $plain] = $this->makeSession();

        $resolved = app(ResolveCustomerSession::class)->fromToken($plain);

        $this->assertNotNull($resolved);
        $this->assertSame($session->id, $resolved->id);
    }

    public function test_rejects_wrong_expired_or_closed_token(): void
    {
        $resolver = app(ResolveCustomerSession::class);

        $this->assertNull($resolver->fromToken('token-tidak-ada'));

        [, $expiredPlain] = $this->makeSession(expired: true);
        $this->assertNull($resolver->fromToken($expiredPlain));

        [, $closedPlain] = $this->makeSession(status: 'closed');
        $this->assertNull($resolver->fromToken($closedPlain));
    }
}
