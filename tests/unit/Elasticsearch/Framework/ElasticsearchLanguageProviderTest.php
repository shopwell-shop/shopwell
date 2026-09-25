<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Shopwell\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchLanguageProvider::class)]
class ElasticsearchLanguageProviderTest extends TestCase
{
    public function testGetLanguages(): void
    {
        $languageRepository = static::createStub(EntityRepository::class);

        $languageRepository
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) {
                static::assertTrue($criteria->hasEqualsFilter('fooo'));
                $sortings = $criteria->getSorting();
                static::assertCount(1, $sortings);
                static::assertSame('id', $sortings[0]->getField());

                return new EntitySearchResult('foo', 0, new LanguageCollection(), null, $criteria, Context::createDefaultContext());
            });

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ElasticsearchIndexerLanguageCriteriaEvent::class, static function (ElasticsearchIndexerLanguageCriteriaEvent $event): void {
            $event->getCriteria()->addFilter(new EqualsFilter('fooo', null));
        });

        $provider = new ElasticsearchLanguageProvider(
            $languageRepository,
            $dispatcher
        );

        $provider->getLanguages(
            Context::createDefaultContext()
        );
    }
}
