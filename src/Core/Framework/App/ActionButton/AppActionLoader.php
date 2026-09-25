<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ActionButton;

use Shopwell\Core\Framework\App\Aggregate\ActionButton\ActionButtonCollection;
use Shopwell\Core\Framework\App\Aggregate\ActionButton\ActionButtonEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopwell\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppActionLoader
{
    /**
     * @param EntityRepository<ActionButtonCollection> $actionButtonRepo
     */
    public function __construct(
        private readonly EntityRepository $actionButtonRepo,
        private readonly AppPayloadServiceHelper $appPayloadServiceHelper,
    ) {
    }

    /**
     * @param array<string> $ids
     */
    public function loadAppAction(string $actionId, array $ids, Context $context): AppAction
    {
        $criteria = new Criteria([$actionId]);
        $criteria->addAssociation('app.integration');

        /** @var ActionButtonEntity $actionButton */
        $actionButton = $this->actionButtonRepo->search($criteria, $context)->getEntities()->first();

        if ($actionButton === null) {
            throw AppException::actionNotFound();
        }

        $app = $actionButton->getApp();
        \assert($app !== null);

        try {
            $source = $this->appPayloadServiceHelper->buildSource($app->getVersion(), $app->getName());
        } catch (ShopIdChangeSuggestedException) {
            throw AppException::actionNotFound();
        }

        return new AppAction(
            $app,
            $source,
            $actionButton->getUrl(),
            $actionButton->getEntity(),
            $actionButton->getAction(),
            $ids,
            $actionId
        );
    }
}
