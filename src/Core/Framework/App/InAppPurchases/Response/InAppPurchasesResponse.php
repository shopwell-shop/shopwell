<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\InAppPurchases\Response;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\AssignArrayInterface;
use Shopwell\Core\Framework\Struct\AssignArrayTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class InAppPurchasesResponse implements AssignArrayInterface
{
    use AssignArrayTrait;

    /**
     * @var list<string>
     */
    public array $purchases = [];
}
