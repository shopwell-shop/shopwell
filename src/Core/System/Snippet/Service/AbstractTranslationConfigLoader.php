<?php declare(strict_types=1);

namespace Shopwell\Core\System\Snippet\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\Struct\TranslationConfig;

#[Package('discovery')]
abstract class AbstractTranslationConfigLoader
{
    abstract public function getDecorated(): AbstractTranslationConfigLoader;

    abstract public function load(): TranslationConfig;
}
