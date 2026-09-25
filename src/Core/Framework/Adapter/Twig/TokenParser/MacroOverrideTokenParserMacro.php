<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Twig\TokenParser;

use Shopwell\Core\Framework\Log\Package;
use Twig\Token;

/**
 * @internal
 *
 * deprecated tag:v6.8.0 - reason:remove-subscriber - Will be removed use `sw_macro_function` instead of macro in app scripts
 * we can not use @ deprecated, as the phpstorm plugin would mark all macros as deprecated
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Adapter\Twig\ReturnNodeTest
 */
#[Package('framework')]
class MacroOverrideTokenParserMacro extends SwMacroFunctionTokenParser
{
    public function decideBlockEnd(Token $token): bool
    {
        return $token->test('endmacro');
    }

    public function getTag(): string
    {
        return 'macro';
    }
}
