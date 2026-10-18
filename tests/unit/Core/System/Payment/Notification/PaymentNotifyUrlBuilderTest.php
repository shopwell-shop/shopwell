<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
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

    /**
     * The payout url names the app and not the payout: the channels notify a merchant account, and the
     * payout number is read from the signed body. It is the same base url the payment notifications
     * use - an installation behind a proxy pins it once.
     */
    public function testBuildTransferNamesTheAppAndNotThePayout(): void
    {
        $builder = new PaymentNotifyUrlBuilder(new RequestStack(), 'https://configured.example.com');

        $app = new PaymentAppEntity();
        $app->id = 'app-id';
        $app->settings = null;

        static::assertSame(
            'https://configured.example.com/payment-api/v1/transfer-notify/stripe/app-id',
            $builder->buildTransfer($app, 'stripe')
        );
    }

    public function testBuildTransferPrefersTheSettingAndTrimsItsTrailingSlash(): void
    {
        $builder = new PaymentNotifyUrlBuilder(new RequestStack(), 'https://configured.example.com');

        $app = new PaymentAppEntity();
        $app->id = 'app-id';
        $app->settings = [PaymentNotifyUrlBuilder::SETTING_BASE_URL => 'https://app.example.com/'];

        static::assertSame(
            'https://app.example.com/payment-api/v1/transfer-notify/alipay/app-id',
            $builder->buildTransfer($app, 'alipay')
        );
    }

    public function testBuildTransferFallsBackToTheCurrentRequest(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://shop.example.com/some/path'));

        $app = new PaymentAppEntity();
        $app->id = 'app-id';
        $app->settings = null;

        static::assertSame(
            'https://shop.example.com/payment-api/v1/transfer-notify/wechat/app-id',
            (new PaymentNotifyUrlBuilder($stack))->buildTransfer($app, 'wechat')
        );
    }

    public function testBuildTransferUrlEncodesTheProviderAndTheApp(): void
    {
        $builder = new PaymentNotifyUrlBuilder(new RequestStack(), 'https://shop.example.com');

        $app = new PaymentAppEntity();
        $app->id = 'app/id';
        $app->settings = null;

        static::assertSame(
            'https://shop.example.com/payment-api/v1/transfer-notify/alipay%2Fh5/app%2Fid',
            $builder->buildTransfer($app, 'alipay/h5')
        );
    }
}
