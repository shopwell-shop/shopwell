<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Services;

use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Struct\ExtensionCollection;

/**
 * @internal
 */
#[Package('checkout')]
abstract class AbstractExtensionDataProvider
{
    abstract public function getInstalledExtensions(Context $context, bool $loadCloudExtensions = true, ?Criteria $searchCriteria = null): ExtensionCollection;

    abstract public function getAppEntityFromTechnicalName(string $technicalName, Context $context): AppEntity;

    abstract public function getAppEntityFromId(string $id, Context $context): AppEntity;

    abstract protected function getDecorated(): AbstractExtensionDataProvider;
}
