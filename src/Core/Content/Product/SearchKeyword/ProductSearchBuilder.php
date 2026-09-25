<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SearchKeyword;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductSearchBuilder implements ProductSearchBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductSearchTermInterpreterInterface $interpreter,
        private readonly LoggerInterface $logger,
        private readonly int $searchTermMaxLength,
        private readonly bool $searchKeywordIndexingEnabled = true,
    ) {
    }

    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $search = RequestParamHelper::get($request, 'search');

        if (\is_array($search)) {
            $term = implode(' ', $search);
        } else {
            $term = (string) $search;
        }

        $term = trim($term);
        if (mb_strlen($term) > $this->searchTermMaxLength) {
            $this->logger->notice(
                'The search term "{term}" was trimmed because it exceeded the maximum length of {maxLength} characters.',
                ['term' => $term, 'maxLength' => $this->searchTermMaxLength]
            );

            $term = mb_substr($term, 0, $this->searchTermMaxLength);
        }

        if ($term === '') {
            throw ProductException::missingRequestParameter('search');
        }

        if (!$this->searchKeywordIndexingEnabled) {
            $criteria->setTerm($term);

            return;
        }

        $pattern = $this->interpreter->interpret($term, $context->getContext());

        foreach ($pattern->getTerms() as $searchTerm) {
            $criteria->addQuery(
                new ScoreQuery(
                    new EqualsFilter('product.searchKeywords.keyword', $searchTerm->getTerm()),
                    $searchTerm->getScore(),
                    'product.searchKeywords.ranking'
                )
            );
        }
        $criteria->addQuery(
            new ScoreQuery(
                new ContainsFilter('product.searchKeywords.keyword', $pattern->getOriginal()->getTerm()),
                $pattern->getOriginal()->getScore(),
                'product.searchKeywords.ranking'
            )
        );

        if ($pattern->getBooleanClause() !== SearchPattern::BOOLEAN_CLAUSE_AND) {
            $criteria->addFilter(new AndFilter([
                new EqualsAnyFilter('product.searchKeywords.keyword', array_values($pattern->getAllTerms())),
                new EqualsFilter('product.searchKeywords.languageId', $context->getLanguageId()),
            ]));

            return;
        }

        foreach ($pattern->getTokenTerms() as $terms) {
            $criteria->addFilter(new AndFilter([
                new EqualsFilter('product.searchKeywords.languageId', $context->getLanguageId()),
                new EqualsAnyFilter('product.searchKeywords.keyword', $terms),
            ]));
        }
    }
}
