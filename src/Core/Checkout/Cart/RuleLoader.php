<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart;

use Shopwell\Core\Content\Rule\RuleCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @final Depend on the AbstractRuleLoader which is the definition of public API for this scope
 */
#[Package('checkout')]
class RuleLoader extends AbstractRuleLoader
{
    /**
     * @internal
     *
     * @param EntityRepository<RuleCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function getDecorated(): AbstractRuleLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Context $context): RuleCollection
    {
        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('id'))
            ->addFilter(new EqualsFilter('invalid', false))
            ->setLimit(500)
            ->setTitle('cart-rule-loader::load-rules');

        $repositoryIterator = new RepositoryIterator($this->repository, $context, $criteria);
        $rules = new RuleCollection();

        while (($result = $repositoryIterator->fetch()) !== null) {
            foreach ($result->getEntities() as $rule) {
                if ($rule->getPayload()) {
                    $rules->add($rule);
                }
            }
            if ($result->getEntities()->count() < 500) {
                break;
            }
        }

        return $rules;
    }
}
