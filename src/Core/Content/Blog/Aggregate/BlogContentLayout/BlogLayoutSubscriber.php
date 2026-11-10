<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopwell\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Blog-specific configuration and assignment policy, registered by the Blog module.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Content\Blog\BlogLayoutSubscriberTest
 */
#[Package('discovery')]
class BlogLayoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LayoutRootSourceReader $rootSourceReader,
        private readonly Connection $connection,
        private readonly CacheInvalidator $cacheInvalidator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSystemConfigChangedEvent::class => 'validateDefault',
            SystemConfigChangedEvent::class => 'invalidateDefault',
            EntityDeleteEvent::class => 'beforeDelete',
            EntityWrittenContainerEvent::class => 'invalidateAssignments',
        ];
    }

    public function validateDefault(BeforeSystemConfigChangedEvent $event): void
    {
        $layoutId = $event->getValue();
        if (!$this->isBlogDefault($event->getKey()) || $layoutId === null || $layoutId === '') {
            return;
        }
        if (!\is_string($layoutId)) {
            throw BlogException::invalidLayoutConfiguration(ContentSystemException::contentLayoutNotFound((string) json_encode($layoutId)));
        }
        $expected = $event->getKey() === 'core.content_system.default_blog_listing_content_layout' ? 'none' : 'blog';
        $rootSource = $this->rootSourceReader->read($layoutId, [], Context::createDefaultContext());
        if ($rootSource === null) {
            throw BlogException::invalidLayoutConfiguration(ContentSystemException::contentLayoutNotFound($layoutId));
        }
        if ($rootSource !== $expected) {
            throw BlogException::invalidLayoutConfiguration(ContentSystemException::rootSourceAssignmentMismatch($rootSource, $expected));
        }
    }

    public function invalidateDefault(SystemConfigChangedEvent $event): void
    {
        if ($this->isBlogDefault($event->getKey())) {
            $this->cacheInvalidator->invalidate([SystemConfigService::buildName($event->getKey())]);
        }
    }

    public function invalidateAssignments(EntityWrittenContainerEvent $event): void
    {
        if ($event->getPrimaryKeys(BlogContentLayoutDefinition::ENTITY_NAME) !== []) {
            $this->cacheInvalidator->invalidate(['blog-content-layout']);
        }
    }

    public function beforeDelete(EntityDeleteEvent $event): void
    {
        if ($event->getIds(BlogContentLayoutDefinition::ENTITY_NAME) !== []) {
            $event->addSuccess(fn () => $this->cacheInvalidator->invalidate(['blog-content-layout']));
        }
        $ids = array_filter($event->getIds('content_layout'), \is_string(...));
        if ($ids === []) {
            return;
        }
        $defaults = $this->connection->fetchAllAssociative('SELECT configuration_key, configuration_value FROM system_config WHERE configuration_key LIKE \'core.content_system.default_blog%\'');
        $protected = [];
        foreach ($defaults as $default) {
            if (!$this->isBlogDefault($default['configuration_key'])) {
                continue;
            }
            $id = json_decode($default['configuration_value'], true, 512, \JSON_THROW_ON_ERROR)['_value'] ?? null;
            if (\is_string($id) && \in_array($id, $ids, true)) {
                $protected[] = $id;
            }
        }
        if ($protected !== []) {
            throw BlogException::invalidLayoutConfiguration(ContentSystemException::defaultContentLayoutDeletion(array_values(array_unique($protected))));
        }
    }

    private function isBlogDefault(string $key): bool
    {
        return $key === BlogContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT
            || (str_starts_with($key, 'core.content_system.default_blog_') && str_ends_with($key, '_content_layout'));
    }
}
