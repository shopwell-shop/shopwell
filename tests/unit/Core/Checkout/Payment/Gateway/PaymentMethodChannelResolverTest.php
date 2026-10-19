<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelCollection;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodChannelResolver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodChannelResolver::class)]
class PaymentMethodChannelResolverTest extends TestCase
{
    private const PAYMENT_METHOD_ID = 'payment-method-id';

    public function testResolveReturnsTheBindingOfThePaymentMethod(): void
    {
        $binding = $this->binding();
        $resolver = $this->resolver(PaymentGatewayFixture::repository([$binding]));

        static::assertSame($binding, $resolver->resolve(self::PAYMENT_METHOD_ID, Context::createDefaultContext()));
    }

    public function testResolveThrowsWhenThePaymentMethodHasNoChannel(): void
    {
        $resolver = $this->resolver(PaymentGatewayFixture::repository());

        $this->expectExceptionObject(PaymentException::paymentMethodChannelMissing(self::PAYMENT_METHOD_ID));

        $resolver->resolve(self::PAYMENT_METHOD_ID, Context::createDefaultContext());
    }

    public function testChannelOfReturnsTheDeclaredChannelOfTheBinding(): void
    {
        $resolver = $this->resolver(PaymentGatewayFixture::repository([$this->binding()]));

        $channel = $resolver->channelOf(self::PAYMENT_METHOD_ID, Context::createDefaultContext());

        static::assertSame(PaymentGatewayFixture::CHANNEL, $channel->technicalName);
        static::assertSame(PaymentGatewayFixture::PROVIDER, $channel->providerTechnicalName);
        static::assertSame(PaymentFlow::REDIRECT, $channel->flow);
    }

    /**
     * @param EntityRepository<PaymentMethodGatewayChannelCollection>|null $bindingRepository
     */
    private function resolver(?EntityRepository $bindingRepository = null): PaymentMethodChannelResolver
    {
        return new PaymentMethodChannelResolver(
            $bindingRepository ?? PaymentGatewayFixture::repository(),
            new PaymentChannelRegistry(PaymentGatewayFixture::providerRegistry())
        );
    }

    private function binding(): PaymentMethodGatewayChannelEntity
    {
        $binding = new PaymentMethodGatewayChannelEntity();
        $binding->id = 'binding-id';
        $binding->setUniqueIdentifier($binding->id);
        $binding->paymentMethodId = self::PAYMENT_METHOD_ID;
        $binding->providerTechnicalName = PaymentGatewayFixture::PROVIDER;
        $binding->channelTechnicalName = PaymentGatewayFixture::CHANNEL;

        return $binding;
    }
}
