<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\DependencyInjection\PaymentProviderCompilerPass;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentDecorator;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentProvider;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\TestPaymentProviderDuplicate;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Guards the collection of the payment interfaces at container compile time.
 *
 * This pass is where a second implementation of one technical name is stopped. It has to be stopped
 * here and not at runtime: the technical name is a foreign key value in three tables, so an
 * implementation that silently took over would leave every historic row pointing at code that no
 * longer describes it.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderCompilerPass::class)]
class PaymentProviderCompilerPassTest extends TestCase
{
    public function testAContainerWithoutInterfacesCompiles(): void
    {
        $container = $this->container();

        (new PaymentProviderCompilerPass())->process($container);

        static::assertSame([], $container->getParameter(PaymentProviderCompilerPass::DECORATION_CHAIN_PARAMETER));
        static::assertSame([], $this->registryReferences($container));
    }

    public function testCollectsATaggedDeclaration(): void
    {
        $container = $this->container();
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'testpay', 'priority' => -100]);

        (new PaymentProviderCompilerPass())->process($container);

        static::assertTrue($container->hasDefinition('shopwell.payment.provider.testpay'));
        static::assertSame(
            [TestPaymentProvider::class, 'testpay'],
            $this->definitionArguments($container, 'shopwell.payment.provider.testpay'),
            'The key travels with the reference, so the factory can compare it with the declared name.'
        );
        static::assertSame(['shopwell.payment.provider.testpay'], $this->registryReferences($container));
        static::assertSame(
            ['testpay' => [TestPaymentProvider::class]],
            $container->getParameter(PaymentProviderCompilerPass::DECORATION_CHAIN_PARAMETER)
        );
    }

    public function testTheTagNeedsAKey(): void
    {
        $container = $this->container();
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG);

        $this->expectException(PaymentException::class);

        (new PaymentProviderCompilerPass())->process($container);
    }

    public function testTheKeyHasToFitTheNotifyUrl(): void
    {
        $container = $this->container();
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'test pay']);

        $this->expectExceptionObject(PaymentException::invalidProviderName('test pay'));

        (new PaymentProviderCompilerPass())->process($container);
    }

    public function testRejectsAServiceThatIsNoDeclaration(): void
    {
        $container = $this->container();
        $container->register(\stdClass::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'testpay']);

        $this->expectException(PaymentException::class);

        (new PaymentProviderCompilerPass())->process($container);
    }

    /**
     * The core case: two implementations of one technical name, the second one not a decorator.
     */
    public function testRejectsASecondImplementationThatIsNoDecorator(): void
    {
        $container = $this->container();
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'testpay', 'priority' => -100]);
        $container->register(TestPaymentProviderDuplicate::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'testpay', 'priority' => 0]);

        $this->expectExceptionObject(PaymentException::duplicateProvider(
            'testpay',
            TestPaymentProvider::class,
            TestPaymentProviderDuplicate::class
        ));

        (new PaymentProviderCompilerPass())->process($container);
    }

    public function testAcceptsADecorationChainAndUsesTheOutermostDeclaration(): void
    {
        $container = $this->container();
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'testpay', 'priority' => -100]);
        $container->register(TestPaymentDecorator::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'testpay', 'priority' => 0]);

        (new PaymentProviderCompilerPass())->process($container);

        static::assertSame(
            [TestPaymentDecorator::class, 'testpay'],
            $this->definitionArguments($container, 'shopwell.payment.provider.testpay')
        );
        static::assertSame(
            ['testpay' => [TestPaymentDecorator::class, TestPaymentProvider::class]],
            $container->getParameter(PaymentProviderCompilerPass::DECORATION_CHAIN_PARAMETER),
            'The chain is printed outermost first.'
        );
    }

    /**
     * A decorator that decorates nothing would look like a decorator to the check above and then
     * fail on the first payout - it is the base implementation that has no base.
     */
    public function testRejectsAChainWhoseBaseImplementationAlsoWantsToDecorate(): void
    {
        $container = $this->container();
        $container->register(TestPaymentDecorator::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'testpay', 'priority' => 0]);

        $this->expectException(PaymentException::class);

        (new PaymentProviderCompilerPass())->process($container);
    }

    /**
     * The priority is what orders the chain, so one priority for two declarations leaves the order to
     * the container - and the outermost decorator would change with the next unrelated plugin.
     */
    public function testRejectsTwoDeclarationsOfOneInterfaceWithTheSamePriority(): void
    {
        $container = $this->container();
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'testpay', 'priority' => 0]);
        $container->register(TestPaymentDecorator::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'testpay', 'priority' => 0]);

        $this->expectExceptionObject(PaymentException::ambiguousProviderPriority(
            'testpay',
            0,
            TestPaymentProvider::class,
            TestPaymentDecorator::class
        ));

        (new PaymentProviderCompilerPass())->process($container);
    }

    /**
     * Two interfaces may of course share a priority, the tie only matters within one interface.
     */
    public function testAcceptsTheSamePriorityOnDifferentInterfaces(): void
    {
        $container = $this->container();
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'apay', 'priority' => -100]);
        $container->register(TestPaymentProviderDuplicate::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'zpay', 'priority' => -100]);

        (new PaymentProviderCompilerPass())->process($container);

        static::assertSame(
            ['shopwell.payment.provider.apay', 'shopwell.payment.provider.zpay'],
            $this->registryReferences($container)
        );
    }

    public function testOrdersSeveralInterfacesByTechnicalName(): void
    {
        $container = $this->container();
        $container->register(TestPaymentProviderDuplicate::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'zpay', 'priority' => -100]);
        $container->register(TestPaymentProvider::class)
            ->addTag(PaymentProviderCompilerPass::PROVIDER_TAG, ['key' => 'apay', 'priority' => -100]);

        (new PaymentProviderCompilerPass())->process($container);

        static::assertSame(
            ['shopwell.payment.provider.apay', 'shopwell.payment.provider.zpay'],
            $this->registryReferences($container)
        );
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(PaymentProviderRegistry::class)->setArguments([[]]);

        return $container;
    }

    /**
     * @return list<string>
     */
    private function definitionArguments(ContainerBuilder $container, string $id): array
    {
        return array_values(array_map(
            static fn (mixed $argument): string => (string) $argument,
            $container->getDefinition($id)->getArguments()
        ));
    }

    /**
     * @return list<string>
     */
    private function registryReferences(ContainerBuilder $container): array
    {
        $argument = $container->getDefinition(PaymentProviderRegistry::class)->getArgument(0);

        if (!$argument instanceof IteratorArgument) {
            return [];
        }

        return array_values(array_map(static fn ($reference): string => (string) $reference, $argument->getValues()));
    }
}
