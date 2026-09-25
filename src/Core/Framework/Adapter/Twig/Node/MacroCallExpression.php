<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Twig\Node;

use Shopwell\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\MacroReferenceExpression;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Adapter\Twig\ReturnNodeTest
 */
#[Package('framework')]
final class MacroCallExpression extends AbstractExpression
{
    public function __construct(MacroReferenceExpression $macro)
    {
        parent::__construct(['macro' => $macro], [], $macro->getTemplateLine());
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('\\Shopwell\\Core\\Framework\\Adapter\\Twig\\SwTwigFunction::callMacro(fn () => ')
            ->subcompile($this->getNode('macro'))
            ->raw(')')
        ;
    }
}
