<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Navigation\Error;

use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

#[Package('discovery')]
class ErrorPage extends Page
{
    protected ?CmsPageEntity $cmsPage = null;

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function isErrorPage(): bool
    {
        return true;
    }
}
