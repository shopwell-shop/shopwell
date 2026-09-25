<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Demodata\DemodataContext;
use Shopwell\Core\Framework\Demodata\DemodataException;
use Shopwell\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopwell\Core\Framework\Demodata\DemodataService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class ProductReviewGenerator implements DemodataGeneratorInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityWriterInterface $writer,
        private readonly ProductReviewDefinition $productReviewDefinition,
        private readonly Connection $connection,
        private readonly ProductReviewCountService $productReviewCountService
    ) {
    }

    public function getDefinition(): string
    {
        return ProductReviewDefinition::class;
    }

    /**
     * @param array<mixed> $options
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $context->getConsole()->progressStart($numberOfItems);

        $customerIds = $this->getCustomerIds();
        $productIds = $this->getProductIds();
        $salesChannelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');
        if ($salesChannelIds === []) {
            throw DemodataException::wrongExecutionOrder();
        }
        $points = [1, 2, 3, 4, 5];

        $payload = [];

        $writeContext = WriteContext::createFromContext($context->getContext());

        $customerIdsWithReviews = [];

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $customerId = $context->getFaker()->randomElement($customerIds);
            \assert(\is_string($customerId));
            $customerIdsWithReviews[$customerId] = true;

            $payload[] = [
                'id' => Uuid::randomHex(),
                'productId' => $context->getFaker()->randomElement($productIds),
                'customerId' => $customerId,
                'salesChannelId' => $salesChannelIds[array_rand($salesChannelIds)],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'externalUser' => $context->getFaker()->name(),
                'externalEmail' => $context->getFaker()->email(),
                'title' => $context->getFaker()->sentence(),
                'content' => $context->getFaker()->text(),
                'points' => $context->getFaker()->randomElement($points),
                'status' => (bool) random_int(0, 1),
                'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            ];

            if (\count($payload) >= 100) {
                $this->writer->upsert($this->productReviewDefinition, $payload, $writeContext);

                $context->getConsole()->progressAdvance(\count($payload));

                $payload = [];
            }
        }

        if ($payload !== []) {
            $this->writer->upsert($this->productReviewDefinition, $payload, $writeContext);

            $context->getConsole()->progressAdvance(\count($payload));
        }

        foreach ($customerIdsWithReviews as $customerId => $_) {
            $this->productReviewCountService->updateReviewCountForCustomer(Uuid::fromHexToBytes($customerId));
        }

        $context->getConsole()->progressFinish();
    }

    /**
     * @return array<string>
     */
    private function getCustomerIds(): array
    {
        $sql = 'SELECT LOWER(HEX(id)) as id FROM customer LIMIT 200';

        $customerIds = $this->connection->fetchAllAssociative($sql);

        return array_column($customerIds, 'id');
    }

    /**
     * @return array<string>
     */
    private function getProductIds(): array
    {
        $sql = 'SELECT LOWER(HEX(id)) as id FROM product WHERE version_id = :liveVersionId LIMIT 200';

        $productIds = $this->connection->fetchAllAssociative($sql, ['liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]);

        return array_column($productIds, 'id');
    }
}
