<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\Service\PaymentNotifyUrlBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentNotifyUrlBuilder::class)]
class PaymentNotifyUrlBuilderTest extends TestCase
{
    public function testBuildUsesTheBaseUrlSettingOfTheApp(): void
    {
        $builder = new PaymentNotifyUrlBuilder(new RequestStack(), 'https://configured.example.com');

        $app = new PaymentAppEntity();
        $app->settings = [PaymentNotifyUrlBuilder::SETTING_BASE_URL => 'https://app.example.com'];

        static::assertSame(
            'https://app.example.com/payment-api/v1/notify/stripe/P-1',
            $builder->build($app, 'stripe', 'P-1')
        );
    }

    public function testBuildTrimsATrailingSlashOfTheBaseUrl(): void
    {
        $builder = new PaymentNotifyUrlBuilder(new RequestStack());

        $app = new PaymentAppEntity();
        $app->settings = [PaymentNotifyUrlBuilder::SETTING_BASE_URL => 'https://app.example.com/'];

        static::assertSame(
            'https://app.example.com/payment-api/v1/notify/alipay_h5/P-1',
            $builder->build($app, 'alipay_h5', 'P-1')
        );
    }

    public function testBuildFallsBackToTheConfiguredBaseUrl(): void
    {
        $builder = new PaymentNotifyUrlBuilder(new RequestStack(), 'https://configured.example.com/');

        $app = new PaymentAppEntity();
        $app->settings = [PaymentNotifyUrlBuilder::SETTING_BASE_URL => ''];

        static::assertSame(
            'https://configured.example.com/payment-api/v1/notify/stripe/P-1',
            $builder->build($app, 'stripe', 'P-1')
        );
    }

    public function testBuildFallsBackToTheCurrentRequest(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://shop.example.com/some/path'));

        $builder = new PaymentNotifyUrlBuilder($stack);

        $app = new PaymentAppEntity();
        $app->settings = null;

        static::assertSame(
            'https://shop.example.com/payment-api/v1/notify/stripe/P-1',
            $builder->build($app, 'stripe', 'P-1')
        );
    }

    public function testBuildWithoutAnyBaseUrlKeepsThePathRelative(): void
    {
        $builder = new PaymentNotifyUrlBuilder(new RequestStack());

        $app = new PaymentAppEntity();

        static::assertSame('/payment-api/v1/notify/stripe/P-1', $builder->build($app, 'stripe', 'P-1'));
    }

    public function testBuildUrlEncodesTheProviderAndThePaymentNumber(): void
    {
        $builder = new PaymentNotifyUrlBuilder(new RequestStack(), 'https://shop.example.com');

        $app = new PaymentAppEntity();

        static::assertSame(
            'https://shop.example.com/payment-api/v1/notify/alipay%2Fh5/P%201',
            $builder->build($app, 'alipay/h5', 'P 1')
        );
    }

    public function testTheSettingWinsOverTheConfiguredAndRequestedBaseUrl(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://request.example.com'));

        $builder = new PaymentNotifyUrlBuilder($stack, 'https://configured.example.com');

        $app = new PaymentAppEntity();
        $app->settings = [PaymentNotifyUrlBuilder::SETTING_BASE_URL => 'https://app.example.com'];

        static::assertStringStartsWith('https://app.example.com/', $builder->build($app, 'stripe', 'P-1'));
    }
}
