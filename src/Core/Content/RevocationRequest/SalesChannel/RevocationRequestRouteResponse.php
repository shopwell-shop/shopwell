<?php declare(strict_types=1);

namespace Shopwell\Core\Content\RevocationRequest\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{individualSuccessMessage: string}>>
 */
#[Package('after-sales')]
class RevocationRequestRouteResponse extends StoreApiResponse
{
    public function __construct(private string $individualSuccessMessage)
    {
        parent::__construct(
            new ArrayStruct(['individualSuccessMessage' => $individualSuccessMessage])
        );
    }

    public function getIndividualSuccessMessage(): string
    {
        return $this->individualSuccessMessage;
    }
}
