<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\ShopwellHttpException;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ShopwellHttpException>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ExceptionCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'plugin_exception_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return ShopwellHttpException::class;
    }
}
