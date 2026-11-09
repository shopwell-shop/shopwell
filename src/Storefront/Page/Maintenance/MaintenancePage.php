<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Maintenance;

use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

#[Package('discovery')]
class MaintenancePage extends Page
{
    protected ?ContentPage $contentPage = null;

    public function getContentPage(): ?ContentPage
    {
        return $this->contentPage;
    }

    public function setContentPage(ContentPage $contentPage): void
    {
        $this->contentPage = $contentPage;
    }
}
