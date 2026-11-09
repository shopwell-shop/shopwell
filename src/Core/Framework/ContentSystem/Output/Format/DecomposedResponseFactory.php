<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Output\Format;

use Shopwell\Core\Framework\ContentSystem\Output\RenderResult;
use Shopwell\Core\Framework\ContentSystem\RenderingMode;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\ContentDecomposedRouteResponse;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class DecomposedResponseFactory extends AbstractResponseFactory
{
    /**
     * @codeCoverageIgnore
     */
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::FULL;
    }

    public function collectsValueIndex(): bool
    {
        return true;
    }

    public function createResponse(RenderResult $result): AbstractContentRouteResponse
    {
        return new ContentDecomposedRouteResponse($result);
    }
}
