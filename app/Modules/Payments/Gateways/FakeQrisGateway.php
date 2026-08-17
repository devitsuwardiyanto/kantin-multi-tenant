<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\PaymentChargeRequest;
use App\Modules\Payments\Data\QrisCharge;
use Illuminate\Support\Str;

/**
 * Gateway QRIS TIRUAN untuk sandbox/pengembangan. Menghasilkan payload EMVCo QRIS dinamis
 * yang plausibel (termasuk CRC16-CCITT) tanpa memanggil provider nyata. TIDAK untuk produksi.
 *
 * Satu-satunya implementasi PaymentGateway yang di-bind (lihat PaymentsServiceProvider) —
 * memasang beberapa provider sekaligus dilarang oleh kontrak.
 */
final class FakeQrisGateway implements PaymentGateway
{
    public function createQrisCharge(PaymentChargeRequest $request): QrisCharge
    {
        $providerReference = 'FAKE-QRIS-'.strtoupper(Str::random(20));
        $payload = $this->buildEmvcoPayload($request->amount, $request->reference);

        return new QrisCharge(
            providerReference: $providerReference,
            qrisPayload: $payload,
            expiresAt: now()->addSeconds($request->expiresInSeconds),
        );
    }

    public function name(): string
    {
        return 'fake-qris-sandbox';
    }

    public function isSandbox(): bool
    {
        return true;
    }

    /**
     * Menyusun string EMVCo QRIS dinamis minimal + CRC16. Nilai bersifat tiruan.
     */
    private function buildEmvcoPayload(int $amount, string $reference): string
    {
        $fields = $this->tlv('00', '01')          // payload format indicator
            .$this->tlv('01', '12')                // point of initiation: dynamic
            .$this->tlv('52', '0000')              // merchant category code
            .$this->tlv('53', '360')               // currency: IDR
            .$this->tlv('54', (string) $amount)    // transaction amount (Rupiah)
            .$this->tlv('58', 'ID')                // country
            .$this->tlv('59', 'KANTIN MULTITENANT')// merchant name
            .$this->tlv('60', 'JAKARTA')           // merchant city
            .$this->tlv('62', $this->tlv('05', substr($reference, 0, 25))); // additional data: reference

        $withCrcTag = $fields.'6304';

        return $withCrcTag.$this->crc16($withCrcTag);
    }

    private function tlv(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /**
     * CRC16-CCITT (FALSE): poly 0x1021, init 0xFFFF — sesuai spesifikasi EMVCo tag 63.
     */
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
}
