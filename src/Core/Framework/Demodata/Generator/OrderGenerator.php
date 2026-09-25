<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Demodata\DemodataContext;
use Shopwell\Core\Framework\Demodata\DemodataException;
use Shopwell\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopwell\Core\Framework\Demodata\DemodataService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class OrderGenerator implements DemodataGeneratorInterface
{
    /**
     * @var array<string, SalesChannelContext>
     */
    private array $contexts = [];

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractSalesChannelContextFactory $contextFactory,
        private readonly CartService $cartService,
        private readonly OrderConverter $orderConverter,
        private readonly EntityWriterInterface $writer,
        private readonly OrderDefinition $orderDefinition,
        private readonly CartCalculator $cartCalculator
    ) {
    }

    public function getDefinition(): string
    {
        return OrderDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $salesChannelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');
        if ($salesChannelIds === []) {
            throw DemodataException::wrongExecutionOrder();
        }

        $paymentMethodIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM payment_method');
        $productIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM `product` ORDER BY RAND() LIMIT 1000');
        $promotionCodes = $this->connection->fetchFirstColumn('SELECT `code` FROM `promotion` ORDER BY RAND() LIMIT 1000');
        $customerIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM customer LIMIT 10');
        $tags = $this->getTagIds();
        $writeContext = WriteContext::createFromContext($context->getContext());

        $context->getConsole()->progressStart($numberOfItems);

        $productLineItems = array_map(
            fn ($productId) => (new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, $this->faker->randomDigit() + 1))
                ->setStackable(true)
                ->setRemovable(true),
            $productIds
        );
        $promotionLineItems = array_map(
            static function ($promotionCode) {
                $uniqueKey = 'promotion-' . $promotionCode;

                return (new LineItem(Uuid::fromStringToHex($uniqueKey), LineItem::PROMOTION_LINE_ITEM_TYPE))
                    ->setLabel($uniqueKey)
                    ->setGood(false)
                    ->setReferencedId($promotionCode)
                    ->setPriceDefinition(new PercentagePriceDefinition(0));
            },
            $promotionCodes
        );

        $orders = [];

        for ($i = 1; $i <= $numberOfItems; ++$i) {
            $customerId = $context->getFaker()->randomElement($customerIds);

            $salesChannelContext = $this->getContext($customerId, $salesChannelIds, $paymentMethodIds);

            $cart = $this->cartService->createNew($salesChannelContext->getToken());
            foreach ($this->faker->randomElements($productLineItems, random_int(3, 5)) as $lineItem) {
                $cart->add($lineItem);
            }

            if ($promotionLineItems) {
                foreach ($this->faker->randomElements($promotionLineItems, random_int(0, 3)) as $lineItem) {
                    $cart->add($lineItem);
                }
            }

            $cart = $this->cartCalculator->calculate($cart, $salesChannelContext);
            $tempOrder = $this->orderConverter->convertToOrder($cart, $salesChannelContext, new OrderConversionContext());

            $randomDate = $context->getFaker()->dateTimeBetween('-2 years');

            $tempOrder['orderDateTime'] = $randomDate->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $tempOrder['tags'] = $this->getTags($tags);
            $tempOrder['customFields'] = [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true];

            $orders[] = $tempOrder;

            if (\count($orders) >= 20) {
                $this->writer->upsert($this->orderDefinition, $orders, $writeContext);
                $context->getConsole()->progressAdvance(\count($orders));
                $orders = [];
            }
        }

        if ($orders !== []) {
            $this->writer->upsert($this->orderDefinition, $orders, $writeContext);
        }

        $context->getConsole()->progressFinish();
    }

    /**
     * @param list<string> $tags
     *
     * @return list<array{id: string}>
     */
    private function getTags(array $tags): array
    {
        $tagAssignments = [];

        if ($tags !== []) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->randomDigit());

            if ($chosenTags !== []) {
                $tagAssignments = array_map(
                    static fn (string $id) => ['id' => $id],
                    $chosenTags
                );
            }
        }

        return array_values($tagAssignments);
    }

    /**
     * @return list<string>
     */
    private function getTagIds(): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM tag LIMIT 500');
    }

    /**
     * @param non-empty-list<string> $salesChannelIds
     * @param array<string> $paymentMethodIds
     */
    private function getContext(string $customerId, array $salesChannelIds, array $paymentMethodIds = []): SalesChannelContext
    {
        if (isset($this->contexts[$customerId])) {
            return $this->contexts[$customerId];
        }

        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
        ];

        if ($paymentMethodIds !== []) {
            $options[SalesChannelContextService::PAYMENT_METHOD_ID] = $this->faker->randomElement($paymentMethodIds);
        }

        $context = $this->contextFactory->create(Uuid::randomHex(), $salesChannelIds[array_rand($salesChannelIds)], $options);

        return $this->contexts[$customerId] = $context;
    }
}
