<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentHandover::class)]
class PaymentHandoverTest extends TestCase
{
    public function testDefaultsOnlySerializeTheFlow(): void
    {
        $params = new PaymentHandover(PaymentInteraction::QRCODE);

        static::assertSame(PaymentInteraction::QRCODE, $params->flow);
        static::assertNull($params->payUrl);
        static::assertSame('GET', $params->httpMethod);
        static::assertNull($params->htmlForm);
        static::assertSame([], $params->extra);
        static::assertSame(['flow' => 'qrcode'], $params->jsonSerialize());
    }

    public function testAJsonSerializableObjectSerializesToTheFlowOnly(): void
    {
        $params = new PaymentHandover(PaymentInteraction::QRCODE);

        static::assertSame('{"flow":"qrcode"}', json_encode($params));
    }

    public function testRedirectFlowSerializesThePayUrlAndTheMethod(): void
    {
        $params = new PaymentHandover(PaymentInteraction::REDIRECT, 'https://pay.example.com/checkout', 'POST');

        static::assertSame([
            'flow' => 'redirect',
            'pay_url' => 'https://pay.example.com/checkout',
            'http_method' => 'POST',
        ], $params->jsonSerialize());
    }

    public function testHtmlFormAndExtraAreSerializedWhenSet(): void
    {
        $params = new PaymentHandover(PaymentInteraction::SDK, null, 'GET', '<form action="x"></form>', ['appId' => 'wx123']);

        static::assertSame([
            'flow' => 'sdk',
            'html_form' => '<form action="x"></form>',
            'extra' => ['appId' => 'wx123'],
        ], $params->jsonSerialize());
    }

    public function testOnlyANullPayUrlIsLeftOut(): void
    {
        // an empty but present url is still part of the payload, only a missing one is skipped
        $params = new PaymentHandover(PaymentInteraction::QRCODE, '', 'GET', null, []);

        static::assertSame([
            'flow' => 'qrcode',
            'pay_url' => '',
            'http_method' => 'GET',
        ], $params->jsonSerialize());
    }

    public function testEveryFieldIsSerializedTogether(): void
    {
        $params = new PaymentHandover(
            PaymentInteraction::REDIRECT,
            'https://pay.example.com/checkout',
            'POST',
            '<form></form>',
            ['openid' => 'o-1'],
        );

        static::assertSame([
            'flow' => 'redirect',
            'pay_url' => 'https://pay.example.com/checkout',
            'http_method' => 'POST',
            'html_form' => '<form></form>',
            'extra' => ['openid' => 'o-1'],
        ], $params->jsonSerialize());
    }
}
