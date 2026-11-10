<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\OrderReturn\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnTypes;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderReturnOrderVersionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testOpeningAndDiscardingAnOrderDraftPreservesItsReturnsAndPositions(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $order = (new OrderBuilder($ids, 'after-sales-version'))
            ->add('languageId', Defaults::LANGUAGE_SYSTEM)
            ->addAddress('billing_address', ['id' => $ids->get('billing_address')])
            ->addLineItem([
                'id' => $ids->get('line'), 'identifier' => 'test', 'quantity' => 1, 'type' => 'custom', 'label' => 'Test item',
                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ])->build();
        /** @var EntityRepository<OrderCollection> $orders */
        $orders = static::getContainer()->get('order.repository');
        $orders->create([$order], $context);
        /** @var EntityRepository<OrderReturnCollection> $returns */
        $returns = static::getContainer()->get('order_return.repository');
        $returns->create([[
            'id' => $ids->get('return'), 'returnNumber' => 'RT-' . $ids->get('return'),
            'orderId' => $ids->get('after-sales-version'), 'orderVersionId' => Defaults::LIVE_VERSION,
            'salesChannelId' => TestDefaults::SALES_CHANNEL, 'type' => OrderReturnTypes::RETURN_REFUND,
            'refundAmount' => 1000, 'currency' => 'EUR',
            'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderReturnStates::STATE_MACHINE),
            'lineItems' => [[
                'id' => $ids->get('return-line'), 'orderLineItemId' => $ids->get('line'),
                'orderLineItemVersionId' => Defaults::LIVE_VERSION, 'quantity' => 1, 'refundAmount' => 1000,
                'accepted' => true, 'restocked' => false,
            ]],
        ]], $context);

        $versionId = $orders->createVersion($ids->get('after-sales-version'), $context);
        $orders->delete([['id' => $ids->get('after-sales-version')]], $context->createWithVersionId($versionId));

        $criteria = (new Criteria([$ids->get('return')]))->addAssociation('lineItems');
        $orderReturn = $returns->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($orderReturn);
        static::assertSame(Defaults::LIVE_VERSION, $orderReturn->orderVersionId);
        static::assertCount(1, $orderReturn->lineItems ?? []);
        static::assertSame(Defaults::LIVE_VERSION, static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(order_line_item_version_id)) FROM order_return_line_item WHERE id = UNHEX(?)',
            [$ids->get('return-line')]
        ));
    }
}
