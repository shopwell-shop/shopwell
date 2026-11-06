<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway\Request;

use Payum\Core\Request\Generic;
use Shopwell\Core\Framework\Log\Package;

/**
 * Reads a refund without submitting another transfer of money.
 *
 * @internal
 */
#[Package('framework')]
final class RefundQuery extends Generic
{
    public function __construct(mixed $model, public readonly ?string $outRefundId = null)
    {
        parent::__construct($model);
    }
}
