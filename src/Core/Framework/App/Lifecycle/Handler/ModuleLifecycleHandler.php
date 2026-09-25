<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Handler;

use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopwell\Core\Framework\App\Manifest\Xml\Administration\Module;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ModuleLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(private readonly EntityRepository $appRepository)
    {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    private function persist(AppPersistContext $context): void
    {
        if (!$context->app->getAppSecret()) {
            return;
        }

        $payload = [
            'id' => $context->app->getId(),
            'mainModule' => null,
            'modules' => [],
        ];

        $admin = $context->manifest->getAdmin();
        if ($admin !== null) {
            if ($admin->getMainModule() !== null) {
                $payload['mainModule'] = [
                    'source' => $admin->getMainModule()->getSource(),
                ];
            }

            $payload['modules'] = array_map(
                fn (Module $module) => $module->toArray($context->defaultLocale),
                $admin->getModules()
            );
        }

        $this->appRepository->update([$payload], $context->context);
    }
}
