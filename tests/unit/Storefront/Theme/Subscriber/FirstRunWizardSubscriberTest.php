<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopwell\Core\Framework\Store\Struct\FrwState;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Storefront\Theme\Exception\ThemeException;
use Shopwell\Storefront\Theme\Subscriber\FirstRunWizardSubscriber;
use Shopwell\Storefront\Theme\ThemeCollection;
use Shopwell\Storefront\Theme\ThemeEntity;
use Shopwell\Storefront\Theme\ThemeLifecycleService;
use Shopwell\Storefront\Theme\ThemeService;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FirstRunWizardSubscriber::class)]
class FirstRunWizardSubscriberTest extends TestCase
{
    private ThemeService&MockObject $themeService;

    private string $themeId;

    protected function setUp(): void
    {
        $this->themeService = $this->createMock(ThemeService::class);
        $this->themeId = Uuid::randomHex();
    }

    public function testFrwFinishedAssignsTheDefaultThemeToEveryStorefrontSalesChannel(): void
    {
        $salesChannelIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $themeSalesChannelRepository = new StaticEntityRepository([]);
        $subscriber = $this->createSubscriber($salesChannelIds, $themeSalesChannelRepository);

        $this->themeService
            ->expects($this->exactly(3))
            ->method('compileTheme');

        $subscriber->frwFinished($this->createEvent());

        static::assertSame(
            array_map(fn (string $id): array => [['themeId' => $this->themeId, 'salesChannelId' => $id]], $salesChannelIds),
            $themeSalesChannelRepository->upserts
        );
    }

    public function testFrwFinishedDoesNothingWhileTheWizardIsStillOpen(): void
    {
        $themeSalesChannelRepository = new StaticEntityRepository([]);
        $subscriber = $this->createSubscriber([Uuid::randomHex()], $themeSalesChannelRepository);

        $this->themeService->expects($this->never())->method('compileTheme');

        $subscriber->frwFinished(new FirstRunWizardFinishedEvent(
            FrwState::openState(),
            FrwState::openState(),
            Context::createDefaultContext()
        ));

        static::assertSame([], $themeSalesChannelRepository->upserts);
    }

    public function testFrwFinishedThrowsWhenTheDefaultThemeIsMissing(): void
    {
        $subscriber = $this->createSubscriber([Uuid::randomHex()], new StaticEntityRepository([]), false);

        $this->themeService->expects($this->never())->method('compileTheme');

        $this->expectExceptionObject(ThemeException::couldNotFindThemeByName('Storefront'));

        $subscriber->frwFinished($this->createEvent());
    }

    /**
     * @param list<string> $salesChannelIds
     * @param StaticEntityRepository<EntityCollection<Entity>> $themeSalesChannelRepository
     */
    private function createSubscriber(
        array $salesChannelIds,
        StaticEntityRepository $themeSalesChannelRepository,
        bool $withDefaultTheme = true
    ): FirstRunWizardSubscriber {
        $themes = [];

        if ($withDefaultTheme) {
            $theme = new ThemeEntity();
            $theme->setUniqueIdentifier($this->themeId);
            $theme->setId($this->themeId);

            $themes[] = $theme;
        }

        $themeRepository = new StaticEntityRepository([new ThemeCollection($themes)]);

        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [$salesChannelIds]);

        return new FirstRunWizardSubscriber(
            $this->themeService,
            static::createStub(ThemeLifecycleService::class),
            $themeRepository,
            $themeSalesChannelRepository,
            $salesChannelRepository
        );
    }

    private function createEvent(): FirstRunWizardFinishedEvent
    {
        return new FirstRunWizardFinishedEvent(
            FrwState::completedState(),
            FrwState::openState(),
            Context::createDefaultContext()
        );
    }
}
