<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\Framework\Struct\VariablesAccessTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * @template TResponseObject of Struct
 */
#[Package('framework')]
class StoreApiResponse extends Response
{
    // allows the cache key finder to get access of all returned data to build the cache tags
    use VariablesAccessTrait;

    /**
     * @param TResponseObject $object
     */
    public function __construct(protected Struct $object)
    {
        parent::__construct();
    }

    /**
     * @return TResponseObject
     */
    public function getObject(): Struct
    {
        return $this->object;
    }
}
