<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Snippet\SalesChannel\SnippetRouteTest
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 *
 * @extends StoreApiResponse<SnippetSetResultList>
 */
#[Package('discovery')]
class SnippetRouteResponse extends StoreApiResponse
{
    public function getResult(): SnippetSetResultList
    {
        return $this->object;
    }
}
