<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\Docs\Script\_fixtures;

use Shopwell\Core\DevOps\Docs\Script\ServiceReferenceGenerator;

/**
 * @script-service data_loading
 */
class ServiceWithShopwellReturnType
{
    /**
     * @return ServiceReferenceGenerator
     */
    public function foo(): void
    {
    }
}
