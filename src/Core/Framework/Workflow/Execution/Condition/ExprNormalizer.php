<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * Normalizes form references and validates the executable comparison grammar.
 *
 * @internal
 *
 * @phpstan-import-type Token from ConditionExpressionParser
 */
#[Package('framework')]
final class ExprNormalizer
{
    private const VARIABLE_PATTERN = '/\G\$\{\s*form\.([A-Za-z_][A-Za-z0-9_]*)\s*\}/';

    public function __construct(private readonly ConditionExpressionParser $parser = new ConditionExpressionParser())
    {
    }

    public function normalize(string $expr): string
    {
        $this->parse($expr);

        $normalized = '';
        $quote = null;
        $length = \strlen($expr);

        for ($position = 0; $position < $length; ++$position) {
            $character = $expr[$position];

            if ($quote !== null) {
                $normalized .= $character;

                if ($character === '\\') {
                    $normalized .= $expr[++$position];
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === '\'') {
                $quote = $character;
            } elseif ($character === '$' && preg_match(self::VARIABLE_PATTERN, $expr, $matches, 0, $position) === 1) {
                $normalized .= $matches[1];
                $position += \strlen($matches[0]) - 1;

                continue;
            }

            $normalized .= $character;
        }

        return trim($normalized);
    }

    /**
     * @return list<Token>
     */
    public function parse(string $expr): array
    {
        return $this->parser->parse($expr);
    }

    /**
     * @return list<string>
     */
    public function variables(string $expr): array
    {
        $variables = [];

        foreach ($this->parse($expr) as $token) {
            if ($token['type'] === 'variable') {
                $variables[] = (string) $token['value'];
            }
        }

        return array_values(array_unique($variables));
    }

    /**
     * @param list<string> $allowedKeys
     */
    public function assertValid(string $expr, array $allowedKeys): void
    {
        foreach ($this->variables($expr) as $variable) {
            if (!\in_array($variable, $allowedKeys, true)) {
                throw WorkflowExecutionException::conditionInvalid($expr, \sprintf('The form has no field "%s".', $variable));
            }
        }
    }
}
