<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Gateway;

use Shopwell\Core\Checkout\Promotion\PromotionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final
 */
#[Package('checkout')]
class PromotionGateway implements PromotionGatewayInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<PromotionCollection> $promotionRepository
     */
    public function __construct(private readonly EntityRepository $promotionRepository)
    {
    }

    /**
     * Gets a list of promotions for the provided criteria and
     * sales channel context.
     */
    public function get(Criteria $criteria, SalesChannelContext $context): PromotionCollection
    {
        $criteria->setTitle('cart::promotion');
        $criteria->addSorting(
            new FieldSorting('priority', FieldSorting::DESCENDING)
        );

        return $this->promotionRepository->search($criteria, $context->getContext())->getEntities();
    }
}
