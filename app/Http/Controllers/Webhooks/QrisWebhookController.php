<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Services\ProcessPaymentWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint webhook QRIS (publik, tanpa auth, di-gate signature). Memakai RAW BODY untuk
 * verifikasi HMAC — jangan pakai payload hasil parse untuk signature.
 */
final class QrisWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessPaymentWebhook $processor): JsonResponse
    {
        $result = $processor->handle(
            rawBody: $request->getContent(),
            signature: $request->header('X-Qris-Signature'),
        );

        return response()->json(['status' => $result['status']], $result['code']);
    }
}
