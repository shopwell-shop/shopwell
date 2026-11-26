<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LegalGuaranteeNotice\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{svg: string|null, link: string|null, text: string|null}>>
 */
#[Package('inventory')]
class LegalGuaranteeNoticeRouteResponse extends StoreApiResponse
{
    public function __construct(?string $svg, ?string $link, ?string $text = null)
    {
        parent::__construct(new ArrayStruct(['svg' => $svg, 'link' => $link, 'text' => $text], 'legal_guarantee_notice'));
    }
}
