<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Handler;

use Shopwell\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopwell\Core\Framework\App\Lifecycle\Persister\ContentSystemStyleOptionPersister;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemStyleOptionLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly ContentSystemStyleOptionPersister $persister,
        private readonly AbstractContentSystemStyleOptionRegistry $registry,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persister->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persister->persist($context);
    }

    public function activate(AppActivationContext $context): void
    {
        // The activating app's persisted options become live now that the app is active; refresh the cache
        // so they appear immediately. Activation does not pre-validate for a cross-loader name collision: it
        // cannot fail atomically here (active=1 is already committed before this handler runs), and the
        // strict all() surfaces such a collision loudly with both source labels on the next write or install.
        $this->registry->invalidate();
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->registry->invalidate();
    }

    public function uninstall(AppRemovalContext $context): void
    {
        $this->registry->invalidate();
    }

    public function delete(AppRemovalContext $context): void
    {
        // Local removal (AppManager::delete / UninstallAppsStrategy) does not deactivate first, so the
        // cached registry would keep serving the gone app's options without this invalidation.
        $this->registry->invalidate();
    }
}
