<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Wechat\Request;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatCaptureAction;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Provider\PayParamsDecoderInterface;

/**
 * Hands a payer back to the answer wechat already gave for the payment.
 *
 * The keys are read from {@see WechatCaptureAction}, which records the raw answer of the trade: the h5
 * product answers `h5_url`, the native product answers `code_url`. The jsapi, mini program and app
 * products answer a prepay id that is signed per call and expires, so their recorded answer cannot be
 * handed to a browser at all - those decode to null and the caller captures again.
 *
 * @internal
 */
#[Package('framework')]
final class WechatPayParamsDecoder implements PayParamsDecoderInterface
{
    public function payParamsFromResponse(array $response): ?PaymentHandover
    {
        $codeUrl = $response[WechatCaptureAction::RESPONSE_CODE_URL] ?? null;

        if (\is_string($codeUrl) && $codeUrl !== '') {
            return new PaymentHandover(
                flow: PaymentInteraction::QRCODE,
                extra: ['qr_code' => $codeUrl]
            );
        }

        $payUrl = $response[WechatCaptureAction::RESPONSE_H5_URL] ?? null;

        if (\is_string($payUrl) && $payUrl !== '') {
            return new PaymentHandover(flow: PaymentInteraction::REDIRECT, payUrl: $payUrl, httpMethod: 'GET');
        }

        return null;
    }
}
