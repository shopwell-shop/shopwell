<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Locale\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Locale\Exception\InvalidLocaleCodeException;
use Shopwell\Core\System\Locale\LocaleCollection;
use Shopwell\Core\System\Locale\LocaleDefinition;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\System\Locale\Subscriber\LocaleValidator;

/**
 * @internal
 */
#[Package('discovery')]
class LocaleValidatorTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->localeRepository = $this->getContainer()->get('locale.repository');
        $this->definitionInstanceRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
    }

    public function testItCannotCreateLocaleWithInvalidCode(): void
    {
        try {
            $this->localeRepository->create([
                [
                    'code' => 'foo_BAR',
                    'name' => 'English',
                    'territory' => 'USA',
                ],
            ], Context::createDefaultContext());
        } catch (WriteException $e) {
            static::assertInstanceOf(InvalidLocaleCodeException::class, $e->getExceptions()[0]);
            static::assertSame(
                'Cannot create or update locale with invalid code "foo_BAR"',
                $e->getExceptions()[0]->getMessage()
            );

            return;
        }

        static::fail('WriteException not thrown');
    }

    public function testItValidatesAllDefaultLocalesWithoutErrors(): void
    {
        $locales = $this->localeRepository->search(new Criteria(), Context::createDefaultContext())->getEntities()->getElements();
        $definition = $this->definitionInstanceRegistry->get(LocaleDefinition::class);
        $entityExistinceMock = static::createStub(EntityExistence::class);

        $commands = array_map(static fn (LocaleEntity $locale) => new UpdateCommand(
            $definition,
            ['code' => $locale->getCode()],
            ['id' => Uuid::fromHexToBytes($locale->getId())],
            $entityExistinceMock,
            '/0/'
        ), $locales);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            array_values($commands)
        );

        (new LocaleValidator())->preWriteValidateEvent($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }
}
