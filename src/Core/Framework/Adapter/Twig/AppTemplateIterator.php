<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Adapter\Twig;

use Shopwell\Core\Framework\Adapter\Database\MySQLFactory;
use Shopwell\Core\Framework\App\Template\TemplateCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class AppTemplateIterator implements TemplatePathIteratorInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<TemplateCollection> $templateRepository
     */
    public function __construct(
        private readonly TemplatePathIteratorInterface $templateIterator,
        private readonly EntityRepository $templateRepository
    ) {
    }

    public function getIterator(): \Traversable
    {
        yield from $this->templateIterator;

        yield from $this->getDatabaseTemplatePaths();
    }

    /**
     * @return iterable<string>
     */
    public function getTemplatePathsForSubPath(string $subPath, bool $includeDotFiles = false): iterable
    {
        $subPath = trim($subPath, '/');
        if ($subPath === '') {
            return;
        }

        yield from $this->templateIterator->getTemplatePathsForSubPath($subPath, $includeDotFiles);

        foreach ($this->getDatabaseTemplatePaths($subPath) as $templatePath) {
            if ($includeDotFiles || !str_contains('/' . mb_substr($templatePath, mb_strlen($subPath) + 1), '/.')) {
                yield $templatePath;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getDatabaseTemplatePaths(?string $subPath = null): array
    {
        if (MySQLFactory::hasNoDatabaseAvailable()) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        if ($subPath !== null) {
            $criteria->addFilter(new PrefixFilter('path', $subPath . '/'));
        }

        $criteria->addAggregation(
            new TermsAggregation('path-names', 'path')
        );

        /** @var TermsResult $pathNames */
        $pathNames = $this->templateRepository->aggregate(
            $criteria,
            Context::createDefaultContext()
        )->get('path-names');

        return $pathNames->getKeys();
    }
}
