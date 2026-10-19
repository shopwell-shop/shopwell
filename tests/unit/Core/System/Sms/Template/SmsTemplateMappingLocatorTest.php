<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingCollection;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateMappingLocator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsTemplateMappingLocator::class)]
class SmsTemplateMappingLocatorTest extends TestCase
{
    private const TEMPLATE_ID = '0f8f7a5b1c2d4e3f8a9b0c1d2e3f4a5b';
    private const TECHNICAL_NAME = 'aliyun';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testTheRowOfTheAccountWinsOverTheRowOfTheProvider(): void
    {
        $ofProvider = $this->createMapping(null, TemplateApprovalStatus::APPROVED);
        $ofAccount = $this->createMapping('a1b2c3d4e5f60718293a4b5c6d7e8f90');

        $locator = $this->createLocator([$ofProvider, $ofAccount]);

        static::assertSame($ofAccount, $locator->locate($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, 'a1b2c3d4e5f60718293a4b5c6d7e8f90'));
    }

    public function testItFallsBackToTheRowOfTheProvider(): void
    {
        $ofProvider = $this->createMapping(null);
        $ofAnotherAccount = $this->createMapping('b1b2c3d4e5f60718293a4b5c6d7e8f91');

        $locator = $this->createLocator([$ofAnotherAccount, $ofProvider]);

        static::assertSame($ofProvider, $locator->locate($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, 'a1b2c3d4e5f60718293a4b5c6d7e8f90'));
    }

    public function testItAsksForTheRowOfTheProviderWhenNoAccountIsGiven(): void
    {
        $ofProvider = $this->createMapping(null);
        $ofAccount = $this->createMapping('a1b2c3d4e5f60718293a4b5c6d7e8f90');

        $locator = $this->createLocator([$ofAccount, $ofProvider]);

        static::assertSame($ofProvider, $locator->locate($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, null));
    }

    public function testItReturnsNullWhenOnlyAnotherAccountHasARow(): void
    {
        $locator = $this->createLocator([$this->createMapping('b1b2c3d4e5f60718293a4b5c6d7e8f91')]);

        static::assertNull($locator->locate($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, 'a1b2c3d4e5f60718293a4b5c6d7e8f90'));
    }

    public function testItReturnsNullWhenNothingMatches(): void
    {
        static::assertNull($this->createLocator([])->locate($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, 'a1b2c3d4e5f60718293a4b5c6d7e8f90'));
    }

    public function testAnApprovedRowIsPreferredAmongDuplicates(): void
    {
        $approved = $this->createMapping(null, TemplateApprovalStatus::APPROVED, '2026-01-01 10:00:00.000');
        $pending = $this->createMapping(null, TemplateApprovalStatus::PENDING, '2026-02-01 10:00:00.000');

        $locator = $this->createLocator([$pending, $approved]);

        static::assertSame($approved, $locator->locate($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, null));
    }

    public function testTheMostRecentlyUpdatedRowIsPreferredWithoutAnApprovedOne(): void
    {
        $older = $this->createMapping(null, TemplateApprovalStatus::PENDING, '2026-01-01 10:00:00.000');
        $newer = $this->createMapping(null, TemplateApprovalStatus::REJECTED, '2026-02-01 10:00:00.000');

        $locator = $this->createLocator([$older, $newer]);

        static::assertSame($newer, $locator->locate($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, null));
    }

    public function testTheLowestIdWinsWhenNothingElseDistinguishesTheRows(): void
    {
        $higherId = $this->createMappingWithId('ff1b2c3d4e5f60718293a4b5c6d7e8f91');
        $lowerId = $this->createMappingWithId('001b2c3d4e5f60718293a4b5c6d7e8f92');

        $locator = $this->createLocator([$higherId, $lowerId]);

        static::assertSame($lowerId, $locator->locate($this->context, self::TEMPLATE_ID, self::TECHNICAL_NAME, null));
    }

    /**
     * @param list<SmsTemplateMappingEntity> $mappings
     */
    private function createLocator(array $mappings): SmsTemplateMappingLocator
    {
        return new SmsTemplateMappingLocator(
            StaticEntityRepository::of(SmsTemplateMappingCollection::class, [new SmsTemplateMappingCollection($mappings)])
        );
    }

    private function createMapping(
        ?string $gatewayId,
        string $auditStatus = TemplateApprovalStatus::PENDING,
        ?string $updatedAt = null,
    ): SmsTemplateMappingEntity {
        return $this->createMappingWithId(Uuid::randomHex(), $gatewayId, $auditStatus, $updatedAt);
    }

    private function createMappingWithId(
        string $id,
        ?string $gatewayId = null,
        string $auditStatus = TemplateApprovalStatus::PENDING,
        ?string $updatedAt = null,
    ): SmsTemplateMappingEntity {
        $mapping = new SmsTemplateMappingEntity();
        $mapping->id = $id;
        $mapping->setUniqueIdentifier($id);
        $mapping->templateId = self::TEMPLATE_ID;
        $mapping->technicalName = self::TECHNICAL_NAME;
        $mapping->gatewayId = $gatewayId;
        $mapping->auditStatus = $auditStatus;

        if ($updatedAt !== null) {
            $mapping->setUpdatedAt(new \DateTimeImmutable($updatedAt));
        }

        return $mapping;
    }
}
