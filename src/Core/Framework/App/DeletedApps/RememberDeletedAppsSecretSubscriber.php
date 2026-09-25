<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\DeletedApps;

use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\Event\AppDeletedEvent;
use Shopwell\Core\Framework\App\Event\AppInstalledEvent;
use Shopwell\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Shopwell\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class RememberDeletedAppsSecretSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private EntityRepository $appRepository,
        private DeletedAppsGateway $deletedAppsGateway,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppDeletedEvent::class => 'saveSecretFromDeletedApp',
            AppInstalledEvent::class => 'removeDeletedAppSecret',
            ShopIdChangedEvent::class => 'purgeOldSecrets',
            ShopIdDeletedEvent::class => 'purgeOldSecrets',
        ];
    }

    public function saveSecretFromDeletedApp(AppDeletedEvent $event): void
    {
        $criteria = new Criteria([$event->getAppId()]);
        $app = $this->appRepository->search($criteria, $event->getContext())->getEntities()->first();

        if (!$secret = $app?->getAppSecret()) {
            return;
        }

        // An app that adopted a candidate stops trusting the committed secret, so a reinstall needs both.
        $this->deletedAppsGateway->insertSecretsForDeletedApp(
            $app->getName(),
            $secret,
            $app->getUnconfirmedAppSecrets() ?? []
        );
    }

    public function removeDeletedAppSecret(AppInstalledEvent $event): void
    {
        $this->deletedAppsGateway->deleteSecretForApp($event->getApp()->getName());
    }

    /**
     * When the shopId changes, all current apps are re-registered
     * stored old secrets should be dismissed, as they are only valid when you re-install the app on the same shopId
     */
    public function purgeOldSecrets(): void
    {
        $this->deletedAppsGateway->purgeOldSecrets();
    }
}
