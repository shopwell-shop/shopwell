<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DataLoaderProvider
{
    /**
     * @param ServiceLocator<AbstractContentDataLoader<Struct>> $locator
     */
    public function __construct(
        private readonly ServiceLocator $locator
    ) {
    }

    /**
     * @return list<string> the registered source identifiers, keyed by getRequirementType()
     */
    public function getSources(): array
    {
        return array_keys($this->locator->getProvidedServices());
    }

    /**
     * @throws ContentSystemException
     *
     * @return AbstractContentDataLoader<Struct>
     */
    public function get(string $type): AbstractContentDataLoader
    {
        if (!$this->locator->has($type)) {
            throw ContentSystemException::dataLoaderNotRegistered($type, 'unknown', 'unknown');
        }

        return $this->locator->get($type);
    }
}
