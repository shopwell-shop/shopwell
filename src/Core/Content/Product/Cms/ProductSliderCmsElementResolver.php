<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Cms;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopwell\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopwell\Core\Content\Product\Cms\ProductSlider\AbstractProductSliderProcessor;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class ProductSliderCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @var array<string, AbstractProductSliderProcessor>
     */
    private array $processors = [];

    /**
     * @param iterable<AbstractProductSliderProcessor> $processors
     *
     * @internal
     */
    public function __construct(
        iterable $processors,
        private readonly LoggerInterface $logger
    ) {
        foreach ($processors as $processor) {
            $this->processors[$processor->getSource()] = $processor;
        }
    }

    public function getType(): string
    {
        return 'product-slider';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $productConfig = $config->get('products');

        if (!$productConfig || !$productConfig->getValue()) {
            return null;
        }

        $source = $productConfig->getSource();
        $processor = $this->processors[$source] ?? null;

        if (!$processor) {
            $this->logNoProcessorFoundError($source);

            return null;
        }

        return $processor->collect($slot, $config, $resolverContext);
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $slider = new ProductSliderStruct();
        $slot->setData($slider);

        $productConfig = $config->get('products');

        if (!$productConfig) {
            return;
        }

        $source = $productConfig->getSource();
        $processor = $this->processors[$source] ?? null;

        if (!$processor) {
            $this->logNoProcessorFoundError($source);

            return;
        }

        $processor->enrich($slot, $result, $resolverContext);
    }

    private function logNoProcessorFoundError(string $source): void
    {
        $this->logger->error(\sprintf('No product slider processor found by provided source: "%s"', $source));
    }
}
