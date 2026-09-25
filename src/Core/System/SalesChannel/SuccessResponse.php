<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;

/**
 * @extends StoreApiResponse<ArrayStruct<array{success: bool}>>
 */
#[Package('framework')]
class SuccessResponse extends StoreApiResponse
{
    public function __construct()
    {
        parent::__construct(new ArrayStruct(['success' => true]));
    }
}
