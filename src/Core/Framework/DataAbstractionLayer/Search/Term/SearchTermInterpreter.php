<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search\Term;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class SearchTermInterpreter
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TokenizerInterface $tokenizer,
        private readonly int $tokenMinimumLength
    ) {
    }

    public function interpret(string $term): SearchPattern
    {
        /** @phpstan-ignore arguments.count (This ignore should be removed when the deprecated method signature is updated) */
        $terms = $this->tokenizer->tokenize($term, $this->tokenMinimumLength);

        $pattern = new SearchPattern(new SearchTerm($term));

        if (\count($terms) === 1) {
            return $pattern;
        }

        foreach ($terms as $part) {
            $percent = mb_strlen($part) / mb_strlen($term);
            $pattern->addTerm(new SearchTerm($part, $percent));
        }

        return $pattern;
    }
}
