<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ActionButton\Response;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class ActionButtonResponse extends Struct
{
    public function __construct(protected string $actionType)
    {
    }
}
