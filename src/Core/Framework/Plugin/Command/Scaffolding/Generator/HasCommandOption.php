<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 */
#[Package('framework')]
trait HasCommandOption
{
    public function getCommandOption(): InputOption
    {
        return new InputOption(self::OPTION_NAME, null, InputOption::VALUE_NONE, self::OPTION_DESCRIPTION);
    }
}
