<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Twig;

use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Log\Package;
use Twig\Environment;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class TwigVariableParserFactory
{
    public function getParser(Environment $twig): TwigVariableParser
    {
        return new TwigVariableParser($twig);
    }
}
