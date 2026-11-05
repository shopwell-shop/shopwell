<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront;

use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;

/**
 * Reads the explicitly provisioned payment tenant of the shop.
 * Merchant accounts and grants are configured by administrators, never created during a payment.
 *
 * @internal
 */
#[Package('checkout')]
class PlatformPaymentApp
{
    final public const TECHNICAL_NAME = PaymentAppEntity::PLATFORM_TECHNICAL_NAME;

    /**
     * @param EntityRepository<PaymentAppCollection> $appRepository
     */
    public function __construct(private readonly EntityRepository $appRepository)
    {
    }

    public function app(): PaymentAppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', self::TECHNICAL_NAME), new EqualsFilter('active', true));
        $criteria->setLimit(1);
        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        if (!$app instanceof PaymentAppEntity) {
            throw PaymentException::platformTenantMissing(self::TECHNICAL_NAME);
        }

        return $app;
    }
}
