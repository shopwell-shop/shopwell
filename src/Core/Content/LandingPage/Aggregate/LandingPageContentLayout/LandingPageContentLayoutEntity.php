<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Shopwell\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class LandingPageContentLayoutEntity extends AbstractContentLayoutAssignmentEntity
{
    protected string $landingPageId;

    public function getLandingPageId(): string
    {
        return $this->landingPageId;
    }

    public function setLandingPageId(string $landingPageId): void
    {
        $this->landingPageId = $landingPageId;
    }
}
