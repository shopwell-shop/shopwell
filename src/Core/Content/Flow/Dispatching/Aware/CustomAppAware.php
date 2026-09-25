<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Aware;

use Shopwell\Core\Framework\Event\IsFlowEventAware;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
#[IsFlowEventAware]
interface CustomAppAware
{
    public const CUSTOM_DATA = 'customAppData';

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomAppData(): ?array;
}
