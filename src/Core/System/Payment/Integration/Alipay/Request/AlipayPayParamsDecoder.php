<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Integration\Alipay\Request;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Provider\PayParamsDecoderInterface;

/**
 * Hands a payer back to the answer alipay already gave for the payment.
 *
 * The keys are read from {@see AlipayCaptureAction}, which records them: the redirect products record
 * the url (or the self submitting form) under `answer` together with the http method of the request,
 * the qr product records the code under `qr_code`. Everything that is not one of those answers belongs
 * to a flow a browser cannot continue - the app and mini program products answer an order string - and
 * decodes to null so the caller captures again instead of guessing.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayPayParamsDecoder implements PayParamsDecoderInterface
{
    public function payParamsFromResponse(array $response): ?PaymentHandover
    {
        $qrCode = $response[AlipayCaptureAction::RESPONSE_QR_CODE] ?? null;

        if (\is_string($qrCode) && $qrCode !== '') {
            return new PaymentHandover(
                flow: PaymentInteraction::QRCODE,
                extra: [AlipayCaptureAction::RESPONSE_QR_CODE => $qrCode]
            );
        }

        $answer = $response[AlipayCaptureAction::RESPONSE_ANSWER] ?? null;

        if (!\is_string($answer) || $answer === '') {
            return null;
        }

        $httpMethod = strtoupper((string) ($response[AlipayCaptureAction::RESPONSE_HTTP_METHOD] ?? AlipayCaptureAction::DEFAULT_HTTP_METHOD));

        if ($httpMethod === 'POST') {
            // alipay hands a self submitting form when the url would grow too long, the storefront
            // renders it on a middle page instead of redirecting to a url
            return new PaymentHandover(flow: PaymentInteraction::REDIRECT, httpMethod: 'POST', htmlForm: $answer);
        }

        return new PaymentHandover(flow: PaymentInteraction::REDIRECT, payUrl: $answer, httpMethod: 'GET');
    }
}
