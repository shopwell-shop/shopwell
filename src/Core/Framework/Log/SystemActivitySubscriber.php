<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Log;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\App\Event\AppUploadedEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopwell\Core\Framework\Plugin\Event\PluginUploadedEvent;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final class SystemActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'user.written' => 'onEntityWritten',
            'integration.written' => 'onEntityWritten',
            PluginUploadedEvent::class => 'onPluginUploaded',
            AppUploadedEvent::class => 'onAppUploaded',
            PluginPostActivateEvent::class => 'onPluginLifecycle',
            PluginPostDeactivateEvent::class => 'onPluginLifecycle',
            PluginPostInstallEvent::class => 'onPluginLifecycle',
            PluginPostUninstallEvent::class => 'onPluginLifecycle',
            PluginPostUpdateEvent::class => 'onPluginLifecycle',
        ];
    }

    public function onEntityWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            if ($result->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $this->logger->info($event->getEntityName() . ':create', array_filter([
                'entityId' => $result->getPrimaryKey(),
                ...$this->actor($event->getContext()),
            ], static fn (mixed $value): bool => $value !== null));
        }
    }

    public function onPluginUploaded(PluginUploadedEvent $event): void
    {
        $this->logger->info('plugin:upload', array_filter([
            'filename' => $event->filename,
            'pluginName' => $event->pluginName,
            'pluginVersion' => $event->pluginVersion,
            ...$this->actor($event->context),
        ], static fn (mixed $value): bool => $value !== null));
    }

    public function onAppUploaded(AppUploadedEvent $event): void
    {
        $this->logger->info('app:upload', array_filter([
            'filename' => $event->filename,
            'appName' => $event->appName,
            'appVersion' => $event->appVersion,
            ...$this->actor($event->context),
        ], static fn (mixed $value): bool => $value !== null));
    }

    public function onPluginLifecycle(PluginPostActivateEvent|PluginPostDeactivateEvent|PluginPostInstallEvent|PluginPostUninstallEvent|PluginPostUpdateEvent $event): void
    {
        $action = match (true) {
            $event instanceof PluginPostActivateEvent => 'enable',
            $event instanceof PluginPostDeactivateEvent => 'disable',
            $event instanceof PluginPostInstallEvent => 'install',
            $event instanceof PluginPostUninstallEvent => 'uninstall',
            $event instanceof PluginPostUpdateEvent => 'update',
        };

        $this->logger->info('plugin:' . $action, array_filter([
            'pluginName' => $event->getPlugin()->getName(),
            'pluginVersion' => $event instanceof PluginPostUpdateEvent ? $event->getContext()->getUpdatePluginVersion() : $event->getContext()->getCurrentPluginVersion(),
            ...($event instanceof PluginPostUpdateEvent ? ['previousPluginVersion' => $event->getContext()->getCurrentPluginVersion()] : []),
            ...$this->actor($event->getContext()->getContext()),
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array{actorType?: string|null, userId?: string|null, username?: string|null, integrationAccessKey?: string|null}
     */
    private function actor(Context $context): array
    {
        $source = $context->getSource();
        if ($source instanceof SystemSource) {
            return ['actorType' => 'system'];
        }
        if (!$source instanceof AdminApiSource) {
            return [];
        }

        $actor = [
            'actorType' => match (true) {
                $source->getUserId() !== null => 'user',
                $source->getIntegrationId() !== null => 'integration',
                default => null,
            },
            'userId' => $source->getUserId(),
            'integrationAccessKey' => null,
        ];
        if ($source->getUserId() !== null) {
            $username = $this->connection->fetchOne(
                'SELECT username FROM `user` WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($source->getUserId())]
            );
            $actor['username'] = \is_string($username) ? $username : null;
        }
        if ($source->getIntegrationId() !== null) {
            $accessKey = $this->connection->fetchOne(
                'SELECT access_key FROM integration WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($source->getIntegrationId())]
            );
            $actor['integrationAccessKey'] = \is_string($accessKey) ? $accessKey : null;
        }

        return $actor;
    }
}
