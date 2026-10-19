<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeNotificationExtractor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeNotificationExtractor::class)]
class StripeNotificationExtractorTest extends TestCase
{
    public function testExtractReadsTheReferencesOfASucceededPaymentIntent(): void
    {
        $data = (new StripeNotificationExtractor())->extract($this->body([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'object' => 'payment_intent',
                'id' => 'pi_1',
                'metadata' => ['payment_no' => 'P-1', 'out_trade_no' => 'T-1'],
            ]],
        ]), []);

        static::assertSame('P-1', $data->paymentNo);
        static::assertSame('T-1', $data->outTradeNo);
        static::assertSame('pi_1', $data->outPaymentNo);
        static::assertNull($data->refundNo);
    }

    public function testExtractReadsThePaymentIntentOfACompletedCheckoutSession(): void
    {
        $data = (new StripeNotificationExtractor())->extract($this->body([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'object' => 'checkout.session',
                'id' => 'cs_1',
                'payment_intent' => 'pi_2',
                'metadata' => ['payment_no' => 'P-2'],
            ]],
        ]), []);

        static::assertSame('P-2', $data->paymentNo);
        static::assertSame('pi_2', $data->outPaymentNo);
        static::assertNull($data->outTradeNo);
    }

    public function testExtractIgnoresAnExpandedPaymentIntentOfASession(): void
    {
        $data = (new StripeNotificationExtractor())->extract($this->body([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'object' => 'checkout.session',
                'id' => 'cs_1',
                'payment_intent' => ['id' => 'pi_3'],
            ]],
        ]), []);

        static::assertNull($data->outPaymentNo);
    }

    public function testExtractReadsTheRefundNumberOfARefund(): void
    {
        $data = (new StripeNotificationExtractor())->extract($this->body([
            'type' => 'refund.created',
            'data' => ['object' => [
                'object' => 'refund',
                'id' => 're_1',
                'payment_intent' => 'pi_4',
                'metadata' => ['refund_no' => 'R-1'],
            ]],
        ]), []);

        static::assertSame('R-1', $data->refundNo);
        static::assertSame('pi_4', $data->outPaymentNo);
        static::assertNull($data->paymentNo);
        static::assertNull($data->outTradeNo);
    }

    public function testExtractTreatsAnEmptyValueAsMissing(): void
    {
        $data = (new StripeNotificationExtractor())->extract($this->body([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'object' => 'payment_intent',
                'id' => '',
                'metadata' => ['payment_no' => '', 'out_trade_no' => ''],
            ]],
        ]), []);

        static::assertNull($data->paymentNo);
        static::assertNull($data->outTradeNo);
        static::assertNull($data->outPaymentNo);
    }

    public function testExtractIgnoresANonScalarReference(): void
    {
        $data = (new StripeNotificationExtractor())->extract($this->body([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'object' => 'payment_intent',
                'id' => 'pi_1',
                'metadata' => ['payment_no' => ['P-1']],
            ]],
        ]), []);

        static::assertNull($data->paymentNo);
    }

    public function testExtractReturnsEmptyDataForInvalidJson(): void
    {
        $data = (new StripeNotificationExtractor())->extract('this is not json', []);

        static::assertNull($data->paymentNo);
        static::assertNull($data->outTradeNo);
        static::assertNull($data->outPaymentNo);
        static::assertNull($data->refundNo);
    }

    public function testExtractReturnsEmptyDataWithoutAnObject(): void
    {
        $data = (new StripeNotificationExtractor())->extract($this->body([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => 'a string'],
        ]), []);

        static::assertNull($data->paymentNo);
        static::assertNull($data->outPaymentNo);
    }

    public function testAcknowledgeAnswersAnEmptyJsonBody(): void
    {
        $ack = (new StripeNotificationExtractor())->acknowledge();

        static::assertSame('', $ack->body);
        static::assertSame('application/json', $ack->contentType);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function body(array $payload): string
    {
        return (string) json_encode($payload);
    }
}
