<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Subscriber;

use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Deleting the shop id abandons every registration keyed to it, so unconfirmed secret candidates can
 * never repair one again — discard them before the apps are re-registered or removed under the new
 * identity. Same-identity moves never delete the shop id, so they keep their candidates for recovery.
 */
#[Package('framework')]
class DiscardUnconfirmedAppSecretsListener
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
    ) {
    }

    public function __invoke(ShopIdDeletedEvent $event): void
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('unconfirmedAppSecrets', null));

        $apps = $this->appRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        if ($apps === []) {
            return;
        }

        foreach ($apps as &$app) {
            $app['unconfirmedAppSecrets'] = null;
        }
        unset($app);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($apps): void {
            $this->appRepository->update($apps, $context);
        });
    }
}
