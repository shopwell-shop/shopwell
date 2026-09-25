<?php declare(strict_types=1);

namespace Shopwell\Core\System\Locale\SystemCheck;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\SystemCheck\BaseCheck;
use Shopwell\Core\Framework\SystemCheck\Check\Category;
use Shopwell\Core\Framework\SystemCheck\Check\Result;
use Shopwell\Core\Framework\SystemCheck\Check\Status;
use Shopwell\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopwell\Core\System\Locale\LocaleCollection;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\System\Locale\Util\LocaleHelper;

/**
 * @internal
 */
#[Package('discovery')]
class LocalesReadinessCheck extends BaseCheck
{
    /**
     * @param EntityRepository<LocaleCollection> $localeRepository
     */
    public function __construct(private readonly EntityRepository $localeRepository)
    {
    }

    public function run(): Result
    {
        $locales = $this->localeRepository
            ->search(new Criteria(), Context::createDefaultContext())
            ->getEntities()
            ->map(static fn (LocaleEntity $locale) => $locale->getCode());

        $invalidLocales = array_filter(
            $locales,
            static fn (string $locale) => !LocaleHelper::isLocale($locale)
        );

        $status = $invalidLocales === [] ? Status::OK : Status::WARNING;

        return new Result(
            $this->name(),
            $status,
            $status === Status::OK ? 'All locales are OK' : 'Some locales are invalid',
            $status === Status::OK,
            $invalidLocales
        );
    }

    public function category(): Category
    {
        return Category::SYSTEM;
    }

    public function name(): string
    {
        return 'LocalesReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::longRunning();
    }
}
