<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class HtmlPurifierConfigProvider
{
    public function getConfig(): \HTMLPurifier_Config
    {
        return \HTMLPurifier_Config::createDefault();
    }
}
