<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\DependencyInjection\PaymentProviderCompilerPass;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\PluginProvider;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentAction;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentDecorator;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentProvider;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentProviderDuplicate;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestSecondPaymentDecorator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The acceptance test of the extension contract: all three promises, proven on a real container.
 *
 * The other tests of this directory check one part of the mechanism each. This one compiles an actual
 * container and looks at what the platform can do afterwards, because the claim is not "the classes are
 * correct" but "an extension adds a payment interface without the core changing":
 *
 *  1. one service and one tag are enough - the interface is in the registry, its channel is
 *     addressable, and no core file is involved in it;
 *  2. a decorator adds a capability and keeps the ones of the interface it decorates;
 *  3. a second implementation of the same technical name that is not a decorator stops the container.
 *
 * The container is built here instead of loading `payment.php` so that the container holds *only* the
 * plugin: if the mechanism needed anything from the core configuration, these tests would not compile.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderCompilerPass::class)]
#[CoversClass(PaymentProviderFactory::class)]
class PaymentDeclarationAcceptanceTest extends TestCase
{
    /**
     * One service, one tag. Nothing else is registered for the interface.
     */
    public function testAnExtensionAddsAnInterfaceWithOneTaggedService(): void
    {
        $container = $this->container();
        $container->register(PluginProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => PluginProvider::TECHNICAL_NAME]);

        $container->compile();

        $providers = $container->get(PaymentProviderRegistry::class);

        static::assertTrue($providers->has(PluginProvider::TECHNICAL_NAME));
        static::assertSame(PluginProvider::TECHNICAL_NAME, $providers->get(PluginProvider::TECHNICAL_NAME)->technicalName);

        $provider = $providers->get(PluginProvider::TECHNICAL_NAME);

        static::assertSame(
            [PaymentCapability::CAPTURE],
            $this->capabilityValues($provider),
            'The capability of the interface is derived from the action it declares, the extension declares no capability itself.'
        );
        static::assertSame(
            [PluginProvider::CREDENTIAL],
            array_map(static fn (PaymentConfigField $field): string => $field->name, $provider->credentials)
        );

        $channel = $container->get(PaymentChannelRegistry::class)->get(PluginProvider::CHANNEL);

        static::assertSame(
            PluginProvider::TECHNICAL_NAME,
            $channel->providerTechnicalName,
            'The channel of an interface of an extension resolves to that interface, which is how the credentials of a payment are found.'
        );
        $chain = $container->getParameter(PaymentProviderCompilerPass::DECORATION_CHAIN_PARAMETER);

        static::assertSame(
            [PluginProvider::TECHNICAL_NAME => [PluginProvider::class]],
            $chain
        );
    }

    /**
     * A decorator keeps the interface it wraps and adds a step of its own: the capability set is the
     * union, and `CAPTURE` - the capability the decorated interface brought - is still there.
     */
    public function testADecoratorAddsACapabilityAndKeepsTheOnesItInherits(): void
    {
        $container = $this->container();
        $container->register(PluginProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => PluginProvider::TECHNICAL_NAME, 'priority' => -100]);
        $container->register(TestPaymentDecorator::class)
            ->setArguments([
                new Reference(PluginProvider::class),
                [new Definition(TestPaymentAction::class, [PaymentCapability::REFUND])],
            ])
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => PluginProvider::TECHNICAL_NAME, 'priority' => 0]);

        $container->compile();

        $provider = $container->get(PaymentProviderRegistry::class)->get(PluginProvider::TECHNICAL_NAME);

        static::assertSame(
            [PaymentCapability::CAPTURE, PaymentCapability::REFUND],
            $this->capabilityValues($provider),
            'The refund action of the decorator adds a capability, and CAPTURE of the decorated interface is still part of the set.'
        );

        // the container of this test holds the plugin as its only interface, so the whole
        // parameter is the chain of the plugin
        static::assertSame(
            [PluginProvider::TECHNICAL_NAME => [TestPaymentDecorator::class, PluginProvider::class]],
            $container->getParameter(PaymentProviderCompilerPass::DECORATION_CHAIN_PARAMETER),
            'The chain is printed outermost first.'
        );
    }

    /**
     * The same technical name twice, and the second one is no decorator: the container does not compile.
     *
     * This is the case the technical name is protected against - it is a foreign key value in
     * `payment_order`, `payment_provider_config` and `payment_app_provider`, so an interface that
     * silently took the name over would reinterpret every stored row.
     */
    public function testASecondImplementationOfOneNameThatIsNoDecoratorFailsTheContainer(): void
    {
        $container = $this->container();
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => PluginProvider::TECHNICAL_NAME, 'priority' => -100]);
        $container->register(TestPaymentProviderDuplicate::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => PluginProvider::TECHNICAL_NAME, 'priority' => 0]);

        $this->expectExceptionObject(PaymentException::duplicateProvider(
            PluginProvider::TECHNICAL_NAME,
            TestPaymentProvider::class,
            TestPaymentProviderDuplicate::class
        ));

        $container->compile();
    }

    /**
     * A three step chain: the core declaration at the bottom, then two decorators above it.
     *
     * The capability set is the union over the whole chain - nothing is lost on the way up - and the
     * printed chain is ordered from the outermost declaration down to the base implementation, which is
     * the only order an operator can read as "this is what runs first".
     */
    public function testAThreeStepChainUnitesEveryCapabilityAndIsPrintedOutermostFirst(): void
    {
        $container = $this->container();
        $container->register(PluginProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => PluginProvider::TECHNICAL_NAME, 'priority' => -100]);
        $container->register('payment_decorator_a', TestPaymentDecorator::class)
            ->setArguments([
                new Reference(PluginProvider::class),
                [new Definition(TestPaymentAction::class, [PaymentCapability::REFUND])],
            ])
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => PluginProvider::TECHNICAL_NAME, 'priority' => 0]);
        $container->register('payment_decorator_b', TestSecondPaymentDecorator::class)
            ->setArguments([
                new Reference('payment_decorator_a'),
                [new Definition(TestPaymentAction::class, [PaymentCapability::CLOSE])],
            ])
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => PluginProvider::TECHNICAL_NAME, 'priority' => 10]);

        $container->compile();

        $provider = $container->get(PaymentProviderRegistry::class)->get(PluginProvider::TECHNICAL_NAME);

        static::assertSame(
            [PaymentCapability::CAPTURE, PaymentCapability::REFUND, PaymentCapability::CLOSE],
            $this->capabilityValues($provider),
            'CAPTURE comes from the core declaration, REFUND from the first and CLOSE from the second decorator - none of them is dropped.'
        );

        static::assertSame(
            [
                PluginProvider::TECHNICAL_NAME => [
                    TestSecondPaymentDecorator::class,
                    TestPaymentDecorator::class,
                    PluginProvider::class,
                ],
            ],
            $container->getParameter(PaymentProviderCompilerPass::DECORATION_CHAIN_PARAMETER),
            'The chain starts at the outermost decorator and ends at the declaration that has no decorator below it.'
        );
    }

    /**
     * One service, tagged twice with two different keys.
     *
     * A service can only be one interface - it names itself once, and the container addresses it by that
     * name. Tagging it with a second key therefore produces a declaration whose key and name disagree,
     * which the factory refuses: the interface would be configured under one name and paid under the
     * other, and every stored row would point at a name the registry does not have.
     */
    public function testAServiceTaggedWithTwoDifferentKeysIsRefused(): void
    {
        $container = $this->container();
        // the fixture names itself "testpay", the two tags claim two other interfaces
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'aaa'])
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'bbb']);

        // the pass itself only groups: the key travels to the factory, which compares it with the name
        $container->compile();

        static::assertSame(
            ['aaa' => [TestPaymentProvider::class], 'bbb' => [TestPaymentProvider::class]],
            $container->getParameter(PaymentProviderCompilerPass::DECORATION_CHAIN_PARAMETER),
            'Both tags are collected, so the refusal has to come from the comparison of the key with the name.'
        );

        $this->expectExceptionObject(PaymentException::invalidProviderDeclaration(\sprintf(
            'The declaration "%s" is tagged as the payment interface "%s" but names itself "%s". The tag key and the technical name have to be the same.',
            TestPaymentProvider::class,
            'aaa',
            'testpay'
        )));

        $container->get(PaymentProviderRegistry::class);
    }

    /**
     * A container with the registry, the channel index, the factory and the pass - nothing else.
     *
     * The registry and the channel index are made public so that the test can ask them what an
     * installation offers; every other service stays private, as in the real configuration.
     */
    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(PaymentProviderFactory::class);
        $container->register(PaymentProviderRegistry::class)
            ->setArguments([[]])
            ->setPublic(true);
        $container->register(PaymentChannelRegistry::class)
            ->setArguments([new Reference(PaymentProviderRegistry::class)])
            ->setPublic(true);
        $container->addCompilerPass(new PaymentProviderCompilerPass());

        return $container;
    }

    /**
     * @return list<PaymentCapability>
     */
    private function capabilityValues(PaymentProvider $provider): array
    {
        return $provider->capabilities;
    }
}
