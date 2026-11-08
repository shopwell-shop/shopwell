<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Output\Format;

use Shopwell\Core\Framework\ContentSystem\Output\RenderResult;
use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonElement;
use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonPage;
use Shopwell\Core\Framework\ContentSystem\RenderingMode;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\AbstractContentRouteResponse;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\ContentSkeletonRouteResponse;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class SkeletonResponseFactory extends AbstractResponseFactory
{
    public function getRenderingMode(): RenderingMode
    {
        return RenderingMode::SKELETON;
    }

    public function collectsValueIndex(): bool
    {
        return false;
    }

    public function createResponse(RenderResult $result): AbstractContentRouteResponse
    {
        return new ContentSkeletonRouteResponse(new ContentSkeletonPage(
            $result->reference->id,
            ContentSkeletonElement::fromRendered($result->tree),
            $result->reference->name,
            $result->reference->version,
        ));
    }
}
