<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1580743279UpdateDeliveryMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580743279;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        $zhCnLangId = $this->fetchLanguageId('zh-CN', $connection);

        // update order confirmation
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryCancellationHtmlTemplateEn(),
                $this->getDeliveryCancellationPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $this->getDeliveryCancellationHtmlTemplateZhCn(),
                $this->getDeliveryCancellationPlainTemplateZhCn()
            );
        }

        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryReturnedHtmlTemplateEn(),
                $this->getDeliveryReturnedPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $this->getDeliveryReturnedHtmlTemplateZhCn(),
                $this->getDeliveryReturnedPlainTemplateZhCn()
            );
        }

        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryShippedPartiallyHtmlTemplateEn(),
                $this->getDeliveryShippedPartiallyPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $this->getDeliveryShippedPartiallyHtmlTemplateZhCn(),
                $this->getDeliveryShippedPartiallyPlainTemplateZhCn()
            );
        }

        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryShippedHtmlTemplateEn(),
                $this->getDeliveryShippedPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $this->getDeliveryShippedHTMLTemplateZhCn(),
                $this->getDeliveryShippedPlainTemplateZhCn()
            );
        }

        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY);
        if ($templateId !== null) {
            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $enLangId,
                $this->getDeliveryReturnedPartiallyHtmlTemplateEn(),
                $this->getDeliveryReturnedPartiallyPlainTemplateEn()
            );

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $this->getDeliveryReturnedPartiallyHTMLTemplateZhCn(),
                $this->getDeliveryReturnedPartiallyPlainTemplateZhCn()
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchOne();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        if (!$langId) {
            return null;
        }

        return $langId;
    }

    private function updateMailTemplateTranslation(
        Connection $connection,
        string $mailTemplateId,
        ?string $langId,
        ?string $contentHtml,
        ?string $contentPlain,
        ?string $senderName = null
    ): void {
        if (!$langId) {
            return;
        }

        $sqlString = '';
        $sqlParams = [
            'templateId' => $mailTemplateId,
            'langId' => $langId,
        ];

        if ($contentHtml !== null) {
            $sqlString .= '`content_html` = :contentHtml ';
            $sqlParams['contentHtml'] = $contentHtml;
        }

        if ($contentPlain !== null) {
            $sqlString .= ($sqlString !== '' ? ', ' : '') . '`content_plain` = :contentPlain ';
            $sqlParams['contentPlain'] = $contentPlain;
        }

        if ($senderName !== null) {
            $sqlString .= ($sqlString !== '' ? ', ' : '') . '`sender_name` = :senderName ';
            $sqlParams['senderName'] = $senderName;
        }

        $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString . 'WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $connection->executeStatement($sqlString, $sqlParams);
    }

    private function getDeliveryCancellationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                        <br/>
                        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
                </div>';
    }

    private function getDeliveryCancellationPlainTemplateEn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

            the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
            The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

            You can check the current status of your order on our website under "My account" - "My orders" anytime.
            But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryCancellationHtmlTemplateZhCn(): string
    {
        return '
        <div style="font-family:arial; font-size:12px;">
           <br/>
           <p>
               {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
               <br/>
               您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
               <strong>支付状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
               <br/>
               您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。若您未注册、未开通客户账户即下单，则无法使用该功能。
           </p>
        </div>';
    }

    private function getDeliveryCancellationPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        支付状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getDeliveryReturnedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                  <p>
                      {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                      <br/>
                      the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                      <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                      <br/>
                      You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>
        ';
    }

    private function getDeliveryReturnedPlainTemplateEn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

            the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
            The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

            You can check the current status of your order on our website under "My account" - "My orders" anytime.
            But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryReturnedHtmlTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>';
    }

    private function getDeliveryReturnedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getDeliveryShippedPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
               <br/>
               <p>
                   {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                   <br/>
                   the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                   <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                   <br/>
                   You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
               </p>
            </div>
        ';
    }

    private function getDeliveryShippedPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryShippedPartiallyHtmlTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>';
    }

    private function getDeliveryShippedPartiallyPlainTemplateZhCn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getDeliveryShippedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>
        ';
    }

    private function getDeliveryShippedPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryShippedHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getDeliveryShippedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getDeliveryReturnedPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    You can check the current status of your order on our website under "My account" - "My orders" anytime. But in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>
        ';
    }

    private function getDeliveryReturnedPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime.
        But in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryReturnedPartiallyHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getDeliveryReturnedPartiallyPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态。
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }
}
