<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Twig\TokenParser;

use Shopwell\Core\Framework\Adapter\Twig\Node\SwInclude;
use Shopwell\Core\Framework\Log\Package;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

#[Package('discovery')]
final class IconTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): SwInclude
    {
        /** @var AbstractExpression $iconExpr */
        $iconExpr = $this->parser->parseExpression();

        $expr = new ConstantExpression('@Storefront/storefront/utilities/icon.html.twig', $token->getLine());

        $stream = $this->parser->getStream();

        if ($stream->nextIf(Token::NAME_TYPE, 'style')) {
            /** @var ArrayExpression $variables */
            $variables = $this->parser->parseExpression();
        } else {
            $variables = new ArrayExpression([], $token->getLine());
        }

        $stream->next();

        $variables->addElement(
            $iconExpr,
            new ConstantExpression('name', $token->getLine())
        );

        return new SwInclude($expr, $variables, false, false, $token->getLine());
    }

    public function getTag(): string
    {
        return 'sw_icon';
    }
}
