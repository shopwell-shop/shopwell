<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\LandingPage;

use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

#[Package('discovery')]
class LandingPage extends Page
{
    protected ?LandingPageEntity $landingPage = null;

    protected ?string $navigationId = null;

    public function getEntityName(): string
    {
        return LandingPageDefinition::ENTITY_NAME;
    }

    public function getLandingPage(): ?LandingPageEntity
    {
        return $this->landingPage;
    }

    public function setLandingPage(?LandingPageEntity $landingPage): void
    {
        $this->landingPage = $landingPage;
    }

    public function getNavigationId(): ?string
    {
        return $this->navigationId;
    }

    public function setNavigationId(?string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }
}
