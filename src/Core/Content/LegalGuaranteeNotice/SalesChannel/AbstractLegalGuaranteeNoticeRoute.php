<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LegalGuaranteeNotice\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractLegalGuaranteeNoticeRoute
{
    abstract public function getDecorated(): AbstractLegalGuaranteeNoticeRoute;

    abstract public function load(SalesChannelContext $context): LegalGuaranteeNoticeRouteResponse;
}
