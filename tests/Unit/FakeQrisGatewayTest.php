<?php

namespace Tests\Unit;

use App\Modules\Payments\Data\PaymentChargeRequest;
use App\Modules\Payments\Gateways\FakeQrisGateway;
use PHPUnit\Framework\TestCase;

class FakeQrisGatewayTest extends TestCase
{
    private function crc16(string $data): string
    {
        $crc = 0xFFFF;
        foreach (str_split($data) as $char) {
            $crc ^= ord($char) << 8;
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public function test_charge_has_sandbox_flag_and_reference(): void
    {
        $gateway = new FakeQrisGateway;

        $this->assertTrue($gateway->isSandbox());
        $this->assertSame('fake-qris-sandbox', $gateway->name());

        $charge = $gateway->createQrisCharge(new PaymentChargeRequest('PAY-ABC', 'ORD-1', 42000, 600));
        $this->assertStringStartsWith('FAKE-QRIS-', $charge->providerReference);
        $this->assertTrue($charge->expiresAt->isFuture());
    }

    public function test_payload_is_valid_emvco_with_correct_crc(): void
    {
        $charge = (new FakeQrisGateway)->createQrisCharge(new PaymentChargeRequest('PAY-XYZ', 'ORD-2', 42000, 600));
        $payload = $charge->qrisPayload;

        // Nominal & mata uang muncul sebagai TLV.
        $this->assertStringContainsString('5303360', $payload);   // currency IDR
        $this->assertStringContainsString('540542000', $payload); // amount 42000

        // CRC (4 hex terakhir) harus cocok dengan CRC16 atas seluruh data hingga tag "6304".
        $body = substr($payload, 0, -4);
        $crc = substr($payload, -4);
        $this->assertStringEndsWith('6304', $body);
        $this->assertSame($this->crc16($body), $crc, 'CRC16 EMVCo harus valid');
    }
}
