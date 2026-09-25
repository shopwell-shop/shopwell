<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Lifecycle\Handler;

use Shopwell\Core\Framework\Adapter\Cache\CacheClearer;
use Shopwell\Core\Framework\App\AppCollection;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopwell\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopwell\Core\Framework\App\Template\TemplateCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class TemplateLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @param EntityRepository<TemplateCollection> $templateRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly AbstractTemplateLoader $templateLoader,
        private readonly EntityRepository $templateRepository,
        private readonly EntityRepository $appRepository,
        private readonly CacheClearer $cacheClearer,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        // on install the cache is cleared when the templates are activated, see self::updateActiveState()
        $this->persist($context, clearCacheAfterChange: false);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context, clearCacheAfterChange: true);
    }

    public function activate(AppActivationContext $context): void
    {
        $this->updateActiveState($context->app->getId(), $context->context, false, true);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->updateActiveState($context->app->getId(), $context->context, true, false);
    }

    private function persist(AppPersistContext $context, bool $clearCacheAfterChange): void
    {
        $app = $this->getAppWithExistingTemplates($context->app->getId(), $context->context);
        $existingTemplates = $app->getTemplates();

        \assert($existingTemplates !== null);

        $templatePaths = $this->templateLoader->getTemplatePathsForApp($context->manifest);

        $upserts = [];

        foreach ($templatePaths as $templatePath) {
            $templateContent = $this->templateLoader->getTemplateContent($templatePath, $context->manifest);

            $existing = $existingTemplates->filterByProperty('path', $templatePath)->first();
            if (!$existing) {
                $upserts[] = [
                    'template' => $templateContent,
                    'path' => $templatePath,
                    'active' => $app->isActive(),
                    'appId' => $context->app->getId(),
                    'hash' => Hasher::hash($templateContent),
                ];

                continue;
            }

            $existingTemplates->remove($existing->getId());

            if (Hasher::hash($templateContent) === $existing->getHash()) {
                continue;
            }

            $upserts[] = [
                'id' => $existing->getId(),
                'template' => $templateContent,
                'hash' => Hasher::hash($templateContent),
            ];
        }
        $needsCacheClear = false;

        if ($upserts !== []) {
            $needsCacheClear = true;
            $this->templateRepository->upsert($upserts, $context->context);
        }

        $ids = $existingTemplates->getIds();
        if ($ids !== []) {
            $needsCacheClear = true;
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->templateRepository->delete($ids, $context->context);
        }

        if ($needsCacheClear && $clearCacheAfterChange) {
            $this->cacheClearer->clearHttpCache();
        }
    }

    private function getAppWithExistingTemplates(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('templates');

        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();
        if ($app === null) {
            throw AppException::notFoundByField($appId, 'id');
        }

        return $app;
    }

    private function updateActiveState(string $appId, Context $context, bool $currentActiveState, bool $newActiveState): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', $currentActiveState));

        $templates = $this->templateRepository->searchIds($criteria, $context)->getPrimaryKeyData();
        foreach ($templates as &$template) {
            $template['active'] = $newActiveState;
        }
        unset($template);

        $this->templateRepository->update($templates, $context);

        $this->cacheClearer->clearHttpCache();
    }
}
