<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Symfony\Component\ExpressionLanguage\Lexer;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\ExpressionLanguage\Token as ExpressionToken;
use Symfony\Component\ExpressionLanguage\TokenStream;

/**
 * Adapt the restricted Workflow contract to Symfony tokenization and expression parsing.
 * Domain comparisons remain in ComparisonConditionEvaluator; vendor evaluation is never used.
 *
 * @internal
 *
 * @phpstan-type LexedToken array{token: ExpressionToken, cursor: int}
 * @phpstan-type Token array{type: string, value: string|int|float|bool|null}
 */
#[Package('framework')]
final class ConditionExpressionParser
{
    /**
     * @return list<Token>
     */
    public function parse(string $expression): array
    {
        try {
            [$source, $references, $fields] = $this->normalizeReferences($expression);
            $tokens = $this->restrictTokens($this->tokenize($source, $expression), $source, $references, $expression);
            $tree = (new Parser([]))->parse(new TokenStream($tokens, $source), array_values($references));

            return $this->instructions($tree, $fields, $expression);
        } catch (SyntaxError $exception) {
            throw WorkflowExecutionException::conditionInvalid($expression, $exception->getMessage());
        }
    }

    /**
     * @return non-empty-list<LexedToken>
     */
    private function tokenize(string $source, string $expression): array
    {
        $stream = (new Lexer())->tokenize($source);
        $tokens = [];

        while (true) {
            $token = $stream->current;
            if ($token->cursor === null) {
                throw WorkflowExecutionException::conditionInvalid($expression, 'The expression token has no source position.');
            }

            $tokens[] = ['token' => $token, 'cursor' => $token->cursor];
            if ($stream->isEOF()) {
                return $tokens;
            }

            $stream->next();
        }
    }

    /**
     * @param non-empty-list<LexedToken> $tokens
     * @param array<int, string> $references
     *
     * @return list<ExpressionToken>
     */
    private function restrictTokens(array $tokens, string $source, array $references, string $expression): array
    {
        $allowed = [];
        $groups = [];

        if (trim(substr($source, 0, $tokens[0]['cursor'] - 1)) !== '') {
            throw WorkflowExecutionException::conditionInvalid($expression, 'Comments are not allowed.');
        }

        $tokenCount = \count($tokens) - 1;
        for ($index = 0; $index < $tokenCount; ++$index) {
            $token = $tokens[$index]['token'];
            $cursor = $tokens[$index]['cursor'];
            $next = $tokens[$index + 1];
            $raw = rtrim(substr($source, $cursor - 1, $next['cursor'] - $cursor));
            $negative = $index > 0 && $tokens[$index - 1]['token']->value === '-';
            $this->normalizeToken($token, $raw, $references[$cursor] ?? null, $negative, $expression);

            if ($token->type === ExpressionToken::OPERATOR_TYPE && $token->value === '-') {
                if ($next['token']->type !== ExpressionToken::NUMBER_TYPE || $next['cursor'] !== $cursor + 1) {
                    throw WorkflowExecutionException::conditionInvalid($expression, 'The negative sign must be part of a numeric literal.');
                }

                continue;
            }

            if ($token->type === ExpressionToken::OPERATOR_TYPE) {
                if (!\in_array($token->value, ['>', '>=', '<', '<=', '==', '!=', '&&', '||'], true)) {
                    throw WorkflowExecutionException::conditionInvalid($expression, 'Unsupported expression operator.');
                }

                if ($groups !== [] && !\in_array($token->value, ['&&', '||'], true)) {
                    $groups[array_key_last($groups)] = true;
                }
            } elseif ($token->type === ExpressionToken::PUNCTUATION_TYPE) {
                $this->restrictGrouping($raw, $groups, $expression);
            }

            $allowed[] = $token;
        }
        $allowed[] = $tokens[array_key_last($tokens)]['token'];

        return $allowed;
    }

    private function normalizeToken(ExpressionToken $token, string $raw, ?string $reference, bool $negative, string $expression): void
    {
        if ($token->type === ExpressionToken::STRING_TYPE) {
            if (!preg_match('~^(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')$~sD', $raw)) {
                throw WorkflowExecutionException::conditionInvalid($expression, 'Comments are not allowed.');
            }

            return;
        }

        if ($token->type === ExpressionToken::NUMBER_TYPE) {
            if (!preg_match('/^(?:\d+\.\d+|\.\d+|\d+)$/D', $raw)) {
                throw WorkflowExecutionException::conditionInvalid($expression, 'Unsupported numeric literal.');
            }

            // Preserve integer conversion and contiguous negative literals rather than native unary arithmetic.
            $value = ($negative ? '-' : '') . $raw;
            $token->value = str_contains($raw, '.') ? (float) $value : (int) $value;

            return;
        }

        if ($raw !== $token->value) {
            throw WorkflowExecutionException::conditionInvalid($expression, 'Comments are not allowed.');
        }

        if ($token->type === ExpressionToken::NAME_TYPE) {
            // The caller supplies an alias only at the position of an explicit form reference.
            if ($reference !== null) {
                $token->value = $reference;
            } elseif (\in_array(strtolower($raw), ['true', 'false', 'null'], true)) {
                $token->value = strtolower($raw);
            } else {
                throw WorkflowExecutionException::conditionInvalid($expression, 'Only explicit form references are allowed.');
            }
        }
    }

    /**
     * @param list<bool> $groups
     */
    private function restrictGrouping(string $punctuation, array &$groups, string $expression): void
    {
        if ($punctuation === '(') {
            if (\count($groups) >= 64) {
                throw WorkflowExecutionException::conditionInvalid($expression, 'Condition parentheses cannot be nested more than 64 levels.');
            }

            $groups[] = false;

            return;
        }

        if ($punctuation !== ')' || !array_pop($groups)) {
            throw WorkflowExecutionException::conditionInvalid($expression, 'Parentheses must group a complete condition.');
        }

        if ($groups !== []) {
            $groups[array_key_last($groups)] = true;
        }
    }

    /**
     * @return array{string, array<int, string>, array<string, string>}
     */
    private function normalizeReferences(string $expression): array
    {
        $pattern = '~"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|\$\{\s*form\.([A-Za-z][A-Za-z0-9_]*)\s*\}~s';
        preg_match_all($pattern, $expression, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        $source = '';
        $references = [];
        $fields = [];
        $position = 0;

        foreach ($matches as $match) {
            [$literal, $offset] = $match[0];
            $source .= substr($expression, $position, $offset - $position);
            if (isset($match[1]) && $match[1][1] !== -1) {
                $alias = '_workflow_' . \count($references);
                $references[\strlen($source) + 1] = $alias;
                $source .= $alias;
                // Keep the original field separate from generated parser identifiers.
                $fields[$alias] = $match[1][0];
            } else {
                preg_match_all('~\\\\(.)~s', $literal, $escapes);
                if (array_diff($escapes[1], ['\\', '"', '\'']) !== []) {
                    throw WorkflowExecutionException::conditionInvalid($expression, 'Invalid string escape sequence.');
                }

                // Symfony normalizes source whitespace globally; encode literal controls so their values survive.
                $source .= strtr($literal, ["\r" => '\\r', "\n" => '\\n', "\t" => '\\t', "\v" => '\\v', "\f" => '\\f']);
            }

            $position = $offset + \strlen($literal);
        }
        $source .= substr($expression, $position);

        return [$source, $references, $fields];
    }

    /**
     * @param array<string, string> $fields
     *
     * @return list<Token>
     */
    private function instructions(Node $node, array $fields, string $expression): array
    {
        if (!$node instanceof BinaryNode) {
            throw WorkflowExecutionException::conditionInvalid($expression, 'An explicit comparison is required.');
        }

        $operator = $node->attributes['operator'];
        if (\in_array($operator, ['&&', '||'], true)) {
            return [
                ...$this->instructions($node->nodes['left'], $fields, $expression),
                ...$this->instructions($node->nodes['right'], $fields, $expression),
                ['type' => $operator, 'value' => $operator],
            ];
        }

        if (!\in_array($operator, ['>', '>=', '<', '<=', '==', '!='], true)) {
            throw WorkflowExecutionException::conditionInvalid($expression, 'Unsupported comparison operator.');
        }

        return [
            $this->operand($node->nodes['left'], $fields, $expression),
            $this->operand($node->nodes['right'], $fields, $expression),
            ['type' => $operator, 'value' => $operator],
        ];
    }

    /**
     * @param array<string, string> $fields
     *
     * @return Token
     */
    private function operand(Node $node, array $fields, string $expression): array
    {
        if ($node instanceof NameNode) {
            return ['type' => 'variable', 'value' => $fields[$node->attributes['name']]];
        }

        if ($node instanceof ConstantNode) {
            return ['type' => 'literal', 'value' => $node->attributes['value']];
        }

        throw WorkflowExecutionException::conditionInvalid($expression, 'Only fields and scalar literals are allowed as operands.');
    }
}
