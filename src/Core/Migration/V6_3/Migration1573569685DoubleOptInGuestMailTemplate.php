<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopwell\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1573569685DoubleOptInGuestMailTemplate extends MigrationStep
{
    private const ZH_CN_LANGUAGE_NAME = '简体中文';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    public function getCreationTimestamp(): int
    {
        return 1573569685;
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
                'technical_name' => 'guest_order.double_opt_in',
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
                    'name' => 'Double opt in guest order',
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
                    'name' => 'Double opt in guest order',
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
                    'name' => '双重确认访客下单',
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
                    'subject' => 'Please confirm your email address at {{ salesChannel.name }}',
                    'description' => 'Email confirmation at guest orders',
                    'sender_name' => '{{ salesChannel.name }}',
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
                    'subject' => 'Please confirm your email address at {{ salesChannel.name }}',
                    'description' => 'Email confirmation at guest orders',
                    'sender_name' => '{{ salesChannel.name }}',
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
                    'subject' => '请确认您在 {{ salesChannel.name }} 的邮箱地址',
                    'description' => '访客下单的注册确认',
                    'sender_name' => '{{ salesChannel.name }}',
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
                'event_name' => DoubleOptInGuestOrderEvent::EVENT_NAME,
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
                    您好 {{ customer.salutation.displayName }} {{ customer.name }}，<br/>
                    <br/>
                    请点击下方链接确认您的邮箱地址：<br/>
                    <br/>
                    <a href="{{ confirmUrl }}">确认邮箱</a><br/>
                    <br/>
                    确认后您将进入结算流程，可再次核对并完成订单。<br/>
                    点击确认即表示您同意我们在履行合同过程中向您发送其他邮件。
                </p>
            </div>
        ';
    }

    private function getPlainTemplateZhCn(): string
    {
        return '
            您好 {{ customer.salutation.displayName }} {{ customer.name }}，

            请点击下方链接确认您的邮箱地址：

            {{ confirmUrl }}

            确认后您将进入结算流程，可再次核对并完成订单。
            点击确认即表示您同意我们在履行合同过程中向您发送其他邮件。
        ';
    }

    private function getHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <p>
                    Hello {{ customer.salutation.displayName }} {{ customer.name }},<br/>
                    <br/>
                    Please confirm your email address via the following link:<br/>
                    <br/>
                    <a href="{{ confirmUrl }}">Confirm email</a><br/>
                    <br/>
                    After the confirmation, you will be directed to the checkout, where you can check and complete your order again.<br/>
                    By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
                </p>
            </div>
        ';
    }

    private function getPlainTemplateEn(): string
    {
        return '
            Hello {{ customer.salutation.displayName }} {{ customer.name }},

            Please confirm your email address via the following link:

            {{ confirmUrl }}

            After the confirmation, you will be directed to the checkout, where you can check and complete your order again.
            By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
        ';
    }

    private function getAvailableEntities(): string
    {
        return '{"customer":"customer","salesChannel":"sales_channel"}';
    }
}
