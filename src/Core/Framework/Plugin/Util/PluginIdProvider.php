<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Util;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\PluginCollection;
use Shopwell\Core\Framework\Plugin\PluginException;

#[Package('framework')]
class PluginIdProvider
{
    /**
     * @internal
     *
     * @param EntityRepository<PluginCollection> $pluginRepo
     */
    public function __construct(private readonly EntityRepository $pluginRepo)
    {
    }

    public function getPluginIdByBaseClass(string $pluginBaseClassName, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('baseClass', $pluginBaseClassName));
        $id = $this->pluginRepo->searchIds($criteria, $context)->firstId();
        if ($id === null) {
            throw PluginException::notFound($pluginBaseClassName);
        }

        return $id;
    }
}
