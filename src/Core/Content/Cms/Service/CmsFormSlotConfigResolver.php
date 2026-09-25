<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\Service;

use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopwell\Core\Content\LandingPage\LandingPageCollection;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

#[Package('discovery')]
readonly class CmsFormSlotConfigResolver
{
    /**
     * @internal
     *
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param EntityRepository<LandingPageCollection> $landingPageRepository
     * @param EntityRepository<ProductCollection> $productRepository
     * @param EntityRepository<CmsSlotCollection> $cmsSlotRepository
     */
    public function __construct(
        private EntityRepository $categoryRepository,
        private EntityRepository $landingPageRepository,
        private EntityRepository $productRepository,
        private EntityRepository $cmsSlotRepository,
        private SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @return array{receivers: array<string, string>, message: string}
     */
    public function resolve(SalesChannelContext $context, ?string $slotId, ?string $navigationId, ?string $entityName): array
    {
        $slotConfig = $this->getSlotConfig($context, $slotId, $navigationId, $entityName);

        if (!\is_array($slotConfig['receivers']) || $slotConfig['receivers'] === []) {
            $slotConfig['receivers'] = [
                $this->systemConfigService->getString('core.basicInformation.email', $context->getSalesChannelId()) => $this->systemConfigService->getString('core.basicInformation.shopName', $context->getSalesChannelId()),
            ];
        } else {
            $slotConfig['receivers'] = \array_combine($slotConfig['receivers'], $slotConfig['receivers']);
        }

        if (!\is_string($slotConfig['message'])) {
            $slotConfig['message'] = '';
        }

        return $slotConfig;
    }

    /**
     * @return array{receivers: array<int, string>|null, message: string|null}
     */
    private function getSlotConfig(SalesChannelContext $context, ?string $slotId, ?string $navigationId, ?string $entityName): array
    {
        $slotConfig = ['receivers' => null, 'message' => null];

        if (!$slotId) {
            return $slotConfig;
        }

        if ($navigationId) {
            $slotConfig = $this->getSlotConfigFromEntity($context, $slotId, $navigationId, $entityName);

            if (\is_array($slotConfig['receivers']) && \is_string($slotConfig['message'])) {
                return $slotConfig;
            }
        }

        $criteria = new Criteria([$slotId]);
        $slot = $this->cmsSlotRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$slot) {
            return $slotConfig;
        }

        $config = $slot->getTranslated()['config'] ?? null;

        if (\is_array($config)) {
            if (!\is_array($slotConfig['receivers'])
                && \is_array($config['mailReceiver'] ?? null)
                && \is_array($config['mailReceiver']['value'] ?? null)
            ) {
                $slotConfig['receivers'] = $config['mailReceiver']['value'];
            }

            if (!\is_string($slotConfig['message'])
                && \is_array($config['confirmationText'] ?? null)
                && \is_string($config['confirmationText']['value'] ?? null)
            ) {
                $slotConfig['message'] = $config['confirmationText']['value'];
            }
        }

        return $slotConfig;
    }

    /**
     * @return array{receivers: array<int, string>|null, message: string|null}
     */
    private function getSlotConfigFromEntity(SalesChannelContext $context, string $slotId, string $navigationId, ?string $entityName = null): array
    {
        $slotConfig = ['receivers' => null, 'message' => null];

        $criteria = new Criteria([$navigationId]);

        $entity = match ($entityName) {
            ProductDefinition::ENTITY_NAME => $this->productRepository->search($criteria, $context->getContext())->getEntities()->first(),
            LandingPageDefinition::ENTITY_NAME => $this->landingPageRepository->search($criteria, $context->getContext())->getEntities()->first(),
            default => $this->categoryRepository->search($criteria, $context->getContext())->getEntities()->first(),
        };

        if (!$entity || !$entity->getSlotConfig() || !\array_key_exists($slotId, $entity->getSlotConfig())) {
            return $slotConfig;
        }

        $config = $entity->getSlotConfig()[$slotId];

        if (!\is_array($config)) {
            return $slotConfig;
        }

        if (\is_array($config['mailReceiver'] ?? null) && \is_array($config['mailReceiver']['value'] ?? null)) {
            $slotConfig['receivers'] = $config['mailReceiver']['value'];
        }

        if (\is_array($config['confirmationText'] ?? null) && \is_string($config['confirmationText']['value'] ?? null)) {
            $slotConfig['message'] = $config['confirmationText']['value'];
        }

        return $slotConfig;
    }
}
