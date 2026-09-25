<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('discovery')]
class Page extends Struct
{
    protected ?MetaInformation $metaInformation = null;

    public function getMetaInformation(): ?MetaInformation
    {
        return $this->metaInformation;
    }

    public function setMetaInformation(MetaInformation $metaInformation): void
    {
        $this->metaInformation = $metaInformation;
    }
}
