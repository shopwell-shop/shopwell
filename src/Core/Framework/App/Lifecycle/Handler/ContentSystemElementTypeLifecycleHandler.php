<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Handler;

use Shopwell\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopwell\Core\Framework\App\Lifecycle\Persister\ContentSystemElementTypePersister;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ContentSystemElementTypeLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly ContentSystemElementTypePersister $persister,
        private readonly AbstractContentSystemElementTypeRegistry $registry,
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
        $this->registry->invalidate();
    }
}
