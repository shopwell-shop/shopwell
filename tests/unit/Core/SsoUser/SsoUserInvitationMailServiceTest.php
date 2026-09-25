<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\SsoUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Mail\Service\AbstractMailService;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopwell\Core\Content\MailTemplate\MailTemplateCollection;
use Shopwell\Core\Content\MailTemplate\MailTemplateDefinition;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sso\SsoUser\SsoUserInvitationMailService;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Language\LanguageCollection;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserDefinition;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SsoUserInvitationMailService::class)]
class SsoUserInvitationMailServiceTest extends TestCase
{
    public function testSendInvitationMailToUser(): void
    {
        $abstractMailService = $this->createMock(AbstractMailService::class);
        $abstractMailService->expects($this->once())
            ->method('send')
            ->with(static::callback(static function (array $data) {
                self::assertNull($data['senderEmail']);
                self::assertSame('ShopName', $data['senderName']);

                return true;
            }));

        $systemConfigService = new StaticSystemConfigService([
            'core.basicInformation.shopName' => 'ShopName',
        ]);

        $mailTemplateEntity = new MailTemplateEntity();
        $mailTemplateEntity->setUniqueIdentifier(Uuid::randomHex());
        $mailTemplateEntity->setId(Uuid::randomHex());
        $mailTemplateRepository = new StaticEntityRepository([
            new MailTemplateCollection([$mailTemplateEntity]),
        ], new MailTemplateDefinition());

        $mailTemplateTypeEntity = new MailTemplateTypeEntity();
        $mailTemplateTypeEntity->setUniqueIdentifier(Uuid::randomHex());
        $mailTemplateTypeEntity->setId(Uuid::randomHex());
        $mailTemplateTypeRepository = new StaticEntityRepository([
            new MailTemplateTypeCollection([$mailTemplateTypeEntity]),
        ], new MailTemplateTypeDefinition());

        $userEntity = new UserEntity();
        $userEntity->setUniqueIdentifier(Uuid::randomHex());
        $userEntity->setFirstName('FirstName');
        $userEntity->setLastName('LastName');
        $userEntity->setUsername('UserName');
        $userRepository = new StaticEntityRepository([
            new UserCollection([$userEntity]),
        ], new UserDefinition());

        $languageEntity = new LanguageEntity();
        $languageEntity->setUniqueIdentifier(Uuid::randomHex());
        $languageEntity->setId(Uuid::randomHex());
        $languageRepository = new StaticEntityRepository([
            new LanguageCollection([$languageEntity]),
        ], new LanguageDefinition());

        $routerMock = $this->createMock(UrlGeneratorInterface::class);
        $routerMock->expects($this->once())->method('generate')->willReturn('/admin');

        $ssoUserInvitationMailService = new SsoUserInvitationMailService(
            $abstractMailService,
            $systemConfigService,
            $mailTemplateRepository,
            $mailTemplateTypeRepository,
            $userRepository,
            $languageRepository,
            $routerMock,
            'app.url'
        );

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex(), null));

        $ssoUserInvitationMailService->sendInvitationMailToUser('test@test.com', Uuid::randomHex(), $context);
    }
}
