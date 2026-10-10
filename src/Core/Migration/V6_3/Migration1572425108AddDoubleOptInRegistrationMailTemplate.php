<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopwell\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1572425108AddDoubleOptInRegistrationMailTemplate extends MigrationStep
{
    private const ZH_CN_LANGUAGE_NAME = '简体中文';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    public function getCreationTimestamp(): int
    {
        return 1572425108;
    }

    public function update(Connection $connection): void
    {
        $templateId = Uuid::randomBytes();
        $templateTypeId = Uuid::randomBytes();

        $this->insertMailTemplateTypeData($templateTypeId, $connection);
        $this->insertMailTemplateData($templateId, $templateTypeId, $connection);
        $this->insertEventActionData($templateTypeId, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }

    private function fetchLanguageIdByName(string $languageName, Connection $connection): ?string
    {
        try {
            return (string) $connection->fetchOne(
                'SELECT id FROM `language` WHERE `name` = :languageName',
                ['languageName' => $languageName]
            );
        } catch (Exception) {
            return null;
        }
    }

    private function insertMailTemplateTypeData(string $templateTypeId, Connection $connection): void
    {
        $connection->insert(
            'mail_template_type',
            [
                'id' => $templateTypeId,
                'technical_name' => 'customer_register.double_opt_in',
                'available_entities' => $this->getAvailableEntities(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $zhCnLanguageId = $this->fetchLanguageIdByName(self::ZH_CN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $zhCnLanguageId], true)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $defaultLanguageId,
                    'name' => 'Double opt in registration',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $englishLanguageId,
                    'name' => 'Double opt in registration',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($zhCnLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $zhCnLanguageId,
                    'name' => '双重确认注册',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function insertMailTemplateData(string $templateId, string $templateTypeId, Connection $connection): void
    {
        $connection->insert(
            'mail_template',
            [
                'id' => $templateId,
                'mail_template_type_id' => $templateTypeId,
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $zhCnLanguageId = $this->fetchLanguageIdByName(self::ZH_CN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $zhCnLanguageId], true)) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => 'Please confirm your sign-up with {{ salesChannel.translated.name }}',
                    'description' => 'Registration confirmation',
                    'sender_name' => '{{ salesChannel.translated.name }}',
                    'content_html' => $this->getHtmlTemplateEn(),
                    'content_plain' => $this->getPlainTemplateEn(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $defaultLanguageId,
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => 'Please confirm your sign-up with {{ salesChannel.translated.name }}',
                    'description' => 'Registration confirmation',
                    'sender_name' => '{{ salesChannel.translated.name }}',
                    'content_html' => $this->getHtmlTemplateEn(),
                    'content_plain' => $this->getPlainTemplateEn(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $englishLanguageId,
                ]
            );
        }

        if ($zhCnLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => '请确认您在 {{ salesChannel.translated.name }} 的注册',
                    'description' => '注册确认',
                    'sender_name' => '{{ salesChannel.translated.name }}',
                    'content_html' => $this->getHtmlTemplateZhCn(),
                    'content_plain' => $this->getPlainTemplateZhCn(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $zhCnLanguageId,
                ]
            );
        }
    }

    private function insertEventActionData(string $templateTypeId, Connection $connection): void
    {
        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => CustomerDoubleOptInRegistrationEvent::EVENT_NAME,
                'action_name' => SendMailAction::ACTION_NAME,
                'config' => json_encode([
                    'mail_template_type_id' => Uuid::fromBytesToHex($templateTypeId),
                ], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function getHtmlTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <p>
                    您好 {{ customer.salutation.translated.displayName }} {{ customer.name }}，<br/>
                    <br/>
                    感谢您注册 {{ salesChannel.translated.name }}。<br/>
                    请点击下方链接完成注册确认：<br/>
                    <br/>
                    <a href="{{ confirmUrl }}">完成注册</a><br/>
                    <br/>
                    点击确认即表示您同意我们在履行合同过程中向您发送其他邮件。
                </p>
            </div>
        ';
    }

    private function getPlainTemplateZhCn(): string
    {
        return '
            您好 {{ customer.salutation.translated.displayName }} {{ customer.name }}，

            感谢您注册 {{ salesChannel.translated.name }}。
            请点击下方链接完成注册确认：

            {{ confirmUrl }}

            点击确认即表示您同意我们在履行合同过程中向您发送其他邮件。
        ';
    }

    private function getHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <p>
                    Hello {{ customer.salutation.translated.displayName }} {{ customer.name }},<br/>
                    <br/>
                    thank you for your signing up with {{ salesChannel.translated.name }}.<br/>
                    Please confirm the sign-up via the following link:<br/>
                    <br/>
                    <a href="{{ confirmUrl }}">Completing registration</a><br/>
                    <br/>
                    By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
                </p>
            </div>
        ';
    }

    private function getPlainTemplateEn(): string
    {
        return '
            Hello {{ customer.salutation.translated.displayName }} {{ customer.name }},

            thank you for your signing up with {{ salesChannel.translated.name }}.
            Please confirm the sign-up via the following link:

            {{ confirmUrl }}

            By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
        ';
    }

    private function getAvailableEntities(): string
    {
        return '{"customer":"customer","salesChannel":"sales_channel"}';
    }
}
