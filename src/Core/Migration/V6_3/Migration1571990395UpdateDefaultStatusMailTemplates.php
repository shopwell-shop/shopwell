<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1571990395UpdateDefaultStatusMailTemplates extends MigrationStep
{
    private ?string $defaultLangId = null;

    private ?string $zhCnLangId = null;

    public function getCreationTimestamp(): int
    {
        return 1571990395;
    }

    public function update(Connection $connection): void
    {
        // update DELIVERY_STATE_SHIPPED_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially delivered',
            $this->getOrderStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已部分发货'
        );

        // update DELIVERY_STATE_RETURNED_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially returned',
            $this->getOrderStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已部分退货'
        );

        // update DELIVERY_STATE_RETURNED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is returned',
            $this->getOrderStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已退货'
        );

        // update DELIVERY_STATE_CANCELLED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is cancelled',
            $this->getOrderStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已取消'
        );

        // update DELIVERY_STATE_SHIPPED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is delivered',
            $this->getOrderStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已发货'
        );

        // update ORDER_STATE_OPEN
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is open',
            $this->getOrderStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单待处理'
        );

        // update ORDER_STATE_IN_PROGRESS
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is in process',
            $this->getOrderStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单处理中'
        );

        // update ORDER_STATE_COMPLETED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is completed',
            $this->getOrderStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已完成'
        );

        // update ORDER_STATE_CANCELLED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $this->getOrderStatusUpdateHtmlTemplateEn(),
            $this->getOrderStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is cancelled',
            $this->getOrderStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已取消'
        );

        // update TRANSACTION_STATE_REFUNDED_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially refunded',
            $this->getOrderTransactionStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已部分退款'
        );

        // update TRANSACTION_STATE_REMINDED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Reminder for your order with {{ salesChannel.name }}',
            $this->getOrderTransactionStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单付款提醒'
        );

        // update TRANSACTION_STATE_OPEN
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }}',
            $this->getOrderTransactionStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单'
        );

        // update TRANSACTION_STATE_PAID
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is completly paid',
            $this->getOrderTransactionStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已付清'
        );

        // update TRANSACTION_STATE_CANCELLED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'The payment for your order with {{ salesChannel.name }} is cancelled',
            $this->getOrderTransactionStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单付款已取消'
        );

        // update TRANSACTION_STATE_REFUNDED
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is refunded',
            $this->getOrderTransactionStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已退款'
        );

        // update TRANSACTION_STATE_PAID_PARTIALLY
        $this->updateMailTemplateTranslation(
            $connection,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            $this->getOrderTransactionStatusUpdateHtmlTemplateEn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateEn(),
            '{{ salesChannel.name }}',
            'Your order with {{ salesChannel.name }} is partially paid',
            $this->getOrderTransactionStatusUpdateHtmlTemplateZhCn(),
            $this->getOrderTransactionStatusUpdatePlainTemplateZhCn(),
            '{{ salesChannel.name }}',
            '您在 {{ salesChannel.name }} 的订单已部分付款'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function changeMailTemplateNameForType(Connection $connection, string $mailTemplateType): void
    {
        $connection->executeStatement(
            'UPDATE `mail_template_type` SET `technical_name` = REPLACE(`technical_name`, \'state_enter.\', \'\')
            WHERE `technical_name` = :type',
            ['type' => $mailTemplateType]
        );
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = (string) $connection->fetchOne(
            'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
            WHERE `code` = :code LIMIT 1',
            ['code' => $code]
        );
        if (!$langId && $code !== 'en-GB') {
            return null;
        }

        if (!$langId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $langId;
    }

    private function updateMailTemplateTranslation(
        Connection $connection,
        string $mailTemplateType,
        string $contentHtmlEn,
        string $contentPlainEn,
        string $senderNameEn,
        string $subjectEn,
        string $contentHtmlZhCn,
        string $contentPlainZhCn,
        string $senderNameZhCn,
        string $subjectZhCn
    ): void {
        if (!$this->defaultLangId) {
            $this->defaultLangId = $this->fetchLanguageId('en-GB', $connection);
        }
        if (!$this->zhCnLangId) {
            $this->zhCnLangId = $this->fetchLanguageId('zh-CN', $connection);
        }

        $templateTypeId = $connection->executeQuery(
            'SELECT `id` from `mail_template_type` WHERE `technical_name` = :type',
            ['type' => 'state_enter.' . $mailTemplateType]
        )->fetchOne();

        $this->changeMailTemplateNameForType($connection, 'state_enter.' . $mailTemplateType);

        $templateId = $connection->executeQuery(
            'SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `updated_at` = null',
            ['typeId' => $templateTypeId]
        )->fetchOne();

        $descriptionEn = 'Shopwell Default Template';
        $descriptionZhCn = 'Shopwell 基础模板';

        $newTemplateId = false;

        if ($templateId) {
            $connection->executeStatement(
                'UPDATE `mail_template` SET `system_default` = 1 WHERE `id`= :templateId',
                ['templateId' => $templateId]
            );
        } else {
            $newTemplateId = Uuid::randomBytes();
            $connection->insert(
                'mail_template',
                [
                    'id' => $newTemplateId,
                    'mail_template_type_id' => $templateTypeId,
                    'system_default' => true,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($this->defaultLangId !== $this->zhCnLangId) {
            $sqlString = '';
            $sqlParams = [
                'templateId' => $templateId,
                'langId' => $this->defaultLangId,
            ];

            $sqlString .= '`content_html` = :contentHtml ';
            $sqlParams['contentHtml'] = $contentHtmlEn;

            $sqlString .= ', `content_plain` = :contentPlain ';
            $sqlParams['contentPlain'] = $contentPlainEn;

            $sqlString .= ', `sender_name` = :senderName ';
            $sqlParams['senderName'] = $senderNameEn;

            $sqlString .= ', `subject` = :subject ';
            $sqlParams['subject'] = $subjectEn;

            $sqlString .= ', `description` = :description ';
            $sqlParams['description'] = $descriptionEn;

            if ($newTemplateId) {
                $connection->insert(
                    'mail_template_translation',
                    [
                        'mail_template_id' => $newTemplateId,
                        'language_id' => $this->defaultLangId,
                        'subject' => $subjectEn,
                        'description' => $descriptionEn,
                        'sender_name' => '{{ salesChannel.name }}',
                        'content_html' => $contentHtmlEn,
                        'content_plain' => $contentPlainEn,
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            } else {
                $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString
                    . 'WHERE `mail_template_id`= :templateId AND `language_id` = :langId';
                $connection->executeStatement($sqlString, $sqlParams);
            }
        }

        if ($this->zhCnLangId) {
            $sqlString = '';
            $sqlParams = [
                'templateId' => $templateId,
                'langId' => $this->zhCnLangId,
            ];

            $sqlString .= '`content_html` = :contentHtml ';
            $sqlParams['contentHtml'] = $contentHtmlZhCn;

            $sqlString .= ', `content_plain` = :contentPlain ';
            $sqlParams['contentPlain'] = $contentPlainZhCn;

            $sqlString .= ', `sender_name` = :senderName ';
            $sqlParams['senderName'] = $senderNameZhCn;

            $sqlString .= ', `subject` = :subject ';
            $sqlParams['subject'] = $subjectZhCn;

            $sqlString .= ', `description` = :description ';
            $sqlParams['description'] = $descriptionZhCn;

            $templateTranslationDeId = $connection->executeQuery(
                'SELECT `mail_template_id` from `mail_template_translation`
                    WHERE `mail_template_id` = :templateId AND language_id = :languageId',
                [
                    'templateId' => $templateId,
                    'languageId' => $this->zhCnLangId,
                ]
            )->fetchOne();

            if ($newTemplateId || !$templateTranslationDeId) {
                $connection->insert(
                    'mail_template_translation',
                    [
                        'mail_template_id' => $newTemplateId ?: $templateId,
                        'language_id' => $this->zhCnLangId,
                        'subject' => $subjectZhCn,
                        'sender_name' => '{{ salesChannel.name }}',
                        'description' => $descriptionZhCn,
                        'content_html' => $contentHtmlZhCn,
                        'content_plain' => $contentPlainZhCn,
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            } else {
                $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString
                    . 'WHERE `mail_template_id`= :templateId AND `language_id` = :langId';
                $connection->executeStatement($sqlString, $sqlParams);
            }
        }
    }

    private function getOrderStatusUpdateHtmlTemplateEn(): string
    {
        return <<<EOT
<div style="font-family:arial; font-size:12px;">
 <br/>
    <p>
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
        <br/>
        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
        <br/>
        You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
    </p>
</div>
EOT;
    }

    private function getOrderStatusUpdatePlainTemplateEn(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.
EOT;
    }

    private function getOrderStatusUpdateHtmlTemplateZhCn(): string
    {
        return <<<EOT
        <div style="font-family:arial; font-size:12px;">
         <br/>
            <p>
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                <br/>
                您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新。<br/>
                <strong>订单最新状态：{{order.stateMachineState.name}}。</strong><br/>
                <br/>
                您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。若您未注册、未开通客户账户即下单，则无法使用该功能。
            </p>
        </div>
EOT;
    }

    private function getOrderStatusUpdatePlainTemplateZhCn(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新。
        订单最新状态：{{order.stateMachineState.name}}。

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。
        若您未注册、未开通客户账户即下单，则无法使用该功能。
EOT;
    }

    private function getOrderTransactionStatusUpdateHtmlTemplateEn(): string
    {
        return <<<EOT
<div style="font-family:arial; font-size:12px;">
 <br/>
    <p>
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
        <br/>
        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
        <br/>
        You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
    </p>
</div>
EOT;
    }

    private function getOrderTransactionStatusUpdatePlainTemplateEn(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.
EOT;
    }

    private function getOrderTransactionStatusUpdateHtmlTemplateZhCn(): string
    {
        return <<<EOT
        <div style="font-family:arial; font-size:12px;">
         <br/>
            <p>
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                <br/>
                您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新。<br/>
                <strong>支付最新状态：{{order.transactions.first.stateMachineState.name}}。</strong><br/>
                <br/>
                您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。若您未注册、未开通客户账户即下单，则无法使用该功能。
            </p>
        </div>
EOT;
    }

    private function getOrderTransactionStatusUpdatePlainTemplateZhCn(): string
    {
        return <<<EOT

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新。
        支付最新状态：{{order.transactions.first.stateMachineState.name}}。

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。
        若您未注册、未开通客户账户即下单，则无法使用该功能。
EOT;
    }
}
