<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CommissionScheme;
use App\Models\CustomerSession;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Modules\Ordering\Services\CartService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Payments\Exceptions\PaymentException;
use App\Modules\Payments\Services\PaymentService;
use App\Support\Tokens\OpaqueToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    private function placeOrder(int $price = 25000, int $qty = 2): Order
    {
        $canteen = Canteen::factory()->create(['tax_rate' => 0.1000, 'service_fee_rate' => 0.0200]);
        $tenant = Tenant::factory()->create(['canteen_id' => $canteen->id]);
        CommissionScheme::factory()->create(['tenant_id' => $tenant->id, 'commission_rate' => 0.15, 'valid_from' => now()->subMonth(), 'valid_to' => null]);
        $menu = Menu::factory()->create(['tenant_id' => $tenant->id, 'base_price' => $price, 'stock_qty' => 50]);

        $session = new CustomerSession;
        $session->forceFill([
            'canteen_id' => $canteen->id,
            'session_token_hash' => OpaqueToken::issue(32)['hash'],
            'status' => 'active',
            'expires_at' => now()->addHours(4),
        ])->save();

        app(CartService::class)->add($session, $menu->id, $qty);

        return app(CheckoutService::class)->checkout($session, (string) Str::uuid())->order;
    }

    protected function tearDown(): void
    {
        foreach (CustomerSession::query()->pluck('id') as $id) {
            Redis::del('cart:'.$id);
        }

        parent::tearDown();
    }

    public function test_initiate_creates_payment_and_dynamic_qris_attempt(): void
    {
        $order = $this->placeOrder(25000, 2); // grand total 56000

        $payment = $this->payments()->initiate($order);

        $this->assertSame('pending', $payment->status);
        $this->assertSame(56000, $payment->amount);

        $attempt = $payment->latestAttempt;
        $this->assertNotNull($attempt);
        $this->assertSame('pending', $attempt->status);
        $this->assertStringStartsWith('000201', $attempt->qris_payload, 'payload EMVCo QRIS');
        $this->assertStringContainsString('5303360', $attempt->qris_payload, 'mata uang IDR (360)');
        $this->assertStringContainsString('540556000', $attempt->qris_payload, 'nominal 56000 pada tag 54');
        $this->assertTrue($attempt->expires_at->isFuture());
    }

    public function test_initiate_is_idempotent(): void
    {
        $order = $this->placeOrder();

        $first = $this->payments()->initiate($order);
        $second = $this->payments()->initiate($order);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, DB::table('payment_attempts')->where('payment_id', $first->id)->count());
    }

    public function test_initiate_rejects_order_not_awaiting_payment(): void
    {
        $order = $this->placeOrder();
        $order->update(['status' => 'cancelled']);

        $this->expectException(PaymentException::class);
        $this->payments()->initiate($order);
    }

    public function test_confirm_sandbox_marks_paid_records_event_and_is_idempotent(): void
    {
        $order = $this->placeOrder();
        $payment = $this->payments()->initiate($order);

        $this->payments()->confirmSandbox($payment);

        $payment->refresh();
        $order->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->settled_at);
        $this->assertSame('paid', $order->status);
        $this->assertSame('success', $payment->latestAttempt()->first()->status);
        $this->assertSame(1, DB::table('payment_events')->where('payment_id', $payment->id)->where('result', 'verified')->count());

        // Idempoten: konfirmasi ulang tak menambah event / tak mengubah status.
        $this->payments()->confirmSandbox($payment->fresh());
        $this->assertSame(1, DB::table('payment_events')->where('payment_id', $payment->id)->count());
    }
}
