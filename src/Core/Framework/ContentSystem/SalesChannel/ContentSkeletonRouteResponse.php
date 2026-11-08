<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\SalesChannel;

use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentSkeletonPage;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSkeletonRouteResponse extends AbstractContentRouteResponse
{
    private readonly ContentSkeletonPage $skeletonPage;

    public function __construct(
        ContentSkeletonPage $skeletonPage,
    ) {
        parent::__construct($skeletonPage);
        $this->skeletonPage = $skeletonPage;
    }

    public function getContentSkeletonPage(): ContentSkeletonPage
    {
        return $this->skeletonPage;
    }
}
