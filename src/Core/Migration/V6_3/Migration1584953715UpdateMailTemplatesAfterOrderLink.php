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
class Migration1584953715UpdateMailTemplatesAfterOrderLink extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1584953715;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $defaultLangId = $this->fetchLanguageId('en-GB', $connection);
        $zhCnLangId = $this->fetchLanguageId('zh-CN', $connection);

        // update order confirmation email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getOrderConfirmationHtmlTemplateEn(),
            $this->getOrderConfirmationPlainTemplateEn(),
            $this->getOrderConfirmationHTMLTemplateZhCn(),
            $this->getOrderConfirmationPlainTemplateZhCn()
        );

        // update delivery email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getDeliveryCancellationHtmlTemplateEn(),
            $this->getDeliveryCancellationPlainTemplateEn(),
            $this->getDeliveryCancellationHtmlTemplateZhCn(),
            $this->getDeliveryCancellationPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getDeliveryReturnedHtmlTemplateEn(),
            $this->getDeliveryReturnedPlainTemplateEn(),
            $this->getDeliveryReturnedHtmlTemplateZhCn(),
            $this->getDeliveryReturnedPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getDeliveryShippedPartiallyHtmlTemplateEn(),
            $this->getDeliveryShippedPartiallyPlainTemplateEn(),
            $this->getDeliveryShippedPartiallyHtmlTemplateZhCn(),
            $this->getDeliveryShippedPartiallyPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getDeliveryShippedHtmlTemplateEn(),
            $this->getDeliveryShippedPlainTemplateEn(),
            $this->getDeliveryShippedHTMLTemplateZhCn(),
            $this->getDeliveryShippedPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getDeliveryReturnedPartiallyHtmlTemplateEn(),
            $this->getDeliveryReturnedPartiallyPlainTemplateEn(),
            $this->getDeliveryReturnedPartiallyHTMLTemplateZhCn(),
            $this->getDeliveryReturnedPartiallyPlainTemplateZhCn()
        );

        // update order email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getOrderStateCancelledHtmlTemplateEn(),
            $this->getOrderStateCancelledPlainTemplateEn(),
            $this->getOrderStateCancelledHTMLTemplateZhCn(),
            $this->getOrderStateCancelledPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getOrderStateOpenHtmlTemplateEn(),
            $this->getOrderStateOpenPlainTemplateEn(),
            $this->getOrderStateOpenHTMLTemplateZhCn(),
            $this->getOrderStateOpenPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getOrderStateProgressHtmlTemplateEn(),
            $this->getOrderStateProgressPlainTemplateEn(),
            $this->getOrderStateProgressHTMLTemplateZhCn(),
            $this->getOrderStateProgressPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getOrderStateCompletedHtmlTemplateEn(),
            $this->getOrderStateCompletedPlainTemplateEn(),
            $this->getOrderStateCompletedHTMLTemplateZhCn(),
            $this->getOrderStateCompletedPlainTemplateZhCn()
        );

        // update payment email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getPaymentRefundPartiallyHtmlTemplateEn(),
            $this->getPaymentRefundPartiallyPlainTemplateEn(),
            $this->getPaymentRefundPartiallyHTMLTemplateZhCn(),
            $this->getPaymentRefundPartiallyPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getPaymentRemindedHtmlTemplateEn(),
            $this->getPaymentRemindedPlainTemplateEn(),
            $this->getPaymentRemindedHTMLTemplateZhCn(),
            $this->getPaymentRemindedPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getPaymentOpenHtmlTemplateEn(),
            $this->getPaymentOpenPlainTemplateEn(),
            $this->getPaymentOpenHTMLTemplateZhCn(),
            $this->getPaymentOpenPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getPaymentPaidHtmlTemplateEn(),
            $this->getPaymentPaidPlainTemplateEn(),
            $this->getPaymentPaidHTMLTemplateZhCn(),
            $this->getPaymentPaidPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getPaymentCancelledHtmlTemplateEn(),
            $this->getPaymentCancelledPlainTemplateEn(),
            $this->getPaymentCancelledHTMLTemplateZhCn(),
            $this->getPaymentCancelledPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getPaymentRefundedHtmlTemplateEn(),
            $this->getPaymentRefundedPlainTemplateEn(),
            $this->getPaymentRefundedHTMLTemplateZhCn(),
            $this->getPaymentRefundedPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            $connection,
            $defaultLangId,
            $zhCnLangId,
            $this->getPaymentPaidPartiallyHtmlTemplateEn(),
            $this->getPaymentPaidPartiallyPlainTemplateEn(),
            $this->getPaymentPaidPartiallyHTMLTemplateZhCn(),
            $this->getPaymentPaidPartiallyPlainTemplateZhCn()
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function updateMailTemplate(
        string $mailTemplateType,
        Connection $connection,
        ?string $enLangId,
        ?string $zhCnLangId,
        string $getHtmlTemplateEn,
        string $getPlainTemplateEn,
        string $getHtmlTemplateZhCn,
        string $getPlainTemplateZhCn
    ): void {
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, $mailTemplateType);
        if ($templateId !== null) {
            if ($enLangId !== $zhCnLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $enLangId,
                    $getHtmlTemplateEn,
                    $getPlainTemplateEn
                );
            }

            if ($zhCnLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $zhCnLangId,
                    $getHtmlTemplateZhCn,
                    $getPlainTemplateZhCn
                );
            }

            $ids = Uuid::fromBytesToHexList(
                array_filter([$zhCnLangId, $enLangId])
            );

            if (!\in_array(Defaults::LANGUAGE_SYSTEM, $ids, true)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $getHtmlTemplateEn,
                    $getPlainTemplateEn
                );
            }
        }
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
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
                </div>';
    }

    private function getDeliveryCancellationPlainTemplateEn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

            the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
            The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
            However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryCancellationHtmlTemplateZhCn(): string
    {
        return '
        <div style="font-family:arial; font-size:12px;">
           <br/>
           <p>
               {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
               <br/>
               您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
               <strong>支付状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
               <br/>
               您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
               </br>
               若您未注册、未开通客户账户即下单，则无法使用该功能。
           </p>
        </div>';
    }

    private function getDeliveryCancellationPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        支付状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getDeliveryReturnedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                  <p>
                      {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                      <br/>
                      the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                      <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                      <br/>
                      You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                      </br>
                      However, in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>
        ';
    }

    private function getDeliveryReturnedPlainTemplateEn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

            the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
            The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
            However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryReturnedHtmlTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>';
    }

    private function getDeliveryReturnedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getDeliveryShippedPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
               <br/>
               <p>
                   {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                   <br/>
                   the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                   <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                   <br/>
                   You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    However, in case you have purchased without a registration or a customer account, you do not have this option.
               </p>
            </div>
        ';
    }

    private function getDeliveryShippedPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryShippedPartiallyHtmlTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>';
    }

    private function getDeliveryShippedPartiallyPlainTemplateZhCn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getDeliveryShippedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    However, in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>
        ';
    }

    private function getDeliveryShippedPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryShippedHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getDeliveryShippedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getDeliveryReturnedPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    However, in case you have purchased without a registration or a customer account, you do not have this option.
                </p>
            </div>
        ';
    }

    private function getDeliveryReturnedPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getDeliveryReturnedPartiallyHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getDeliveryReturnedPartiallyPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getOrderStateCancelledHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
             <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                    <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    However, in case you have purchased without a registration or a customer account, you do not have this option.</p>
            </div>
        ';
    }

    private function getOrderStateCancelledPlainTemplateEn(): string
    {
        return '

        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderStateCancelledHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getOrderStateCancelledPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新!
        订单状态最新状态：{{order.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getOrderStateOpenHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getOrderStateOpenPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderStateOpenHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getOrderStateOpenPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新!
        订单状态最新状态：{{order.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getOrderStateProgressHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getOrderStateProgressPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderStateProgressHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getOrderStateProgressPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新!
        订单状态最新状态：{{order.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getOrderStateCompletedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                        <br/>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getOrderStateCompletedPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderStateCompletedHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getOrderStateCompletedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新!
        订单状态最新状态：{{order.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentRefundPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentRefundPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentRefundPartiallyHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentRefundPartiallyPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentRemindedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentRemindedPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentRemindedHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentRemindedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentOpenHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentOpenPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentOpenHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentOpenPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentPaidHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentPaidPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentPaidHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentPaidPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentCancelledHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your delivery at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentCancelledPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentCancelledHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                   {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                   <br/>
                   您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                   <strong>支付状态最新状态：{{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                   <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentCancelledPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        支付状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentRefundedHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentRefundedPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentRefundedHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentRefundedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentPaidPartiallyHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
            </div>
        ';
    }

    private function getPaymentPaidPartiallyPlainTemplateEn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        the status of your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
        The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getPaymentPaidPartiallyHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentPaidPartiallyPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getOrderConfirmationHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">

            {% set currencyIsoCode = order.currency.isoCode %}
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br>
            <br>
            Thank you for your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.<br>
            <br>
            <strong>Information on your order:</strong><br>
            <br>

            <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
                <tr>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Description</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Quantities</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Price</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Total</strong></td>
                </tr>

                {% for lineItem in order.lineItems %}
                <tr>
                    <td style="border-bottom:1px solid #cccccc;">{{ loop.index }} </td>
                    <td style="border-bottom:1px solid #cccccc;">
                      {{ lineItem.label|u.wordwrap(80) }}<br>
                      {% if lineItem.payload.productNumber is defined %}Art. No.: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
                    </td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
                </tr>
                {% endfor %}
            </table>

            {% set delivery = order.deliveries.first %}
            <p>
                <br>
                <br>
                Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>

                Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
                {% for calculatedTax in order.price.calculatedTaxes %}
                    {% if order.taxStatus is same as(\'net\') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
                {% endfor %}
                <strong>Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>

                <br>

                <strong>Selected payment type:</strong> {{ order.transactions.first.paymentMethod.name }}<br>
                {{ order.transactions.first.paymentMethod.description }}<br>
                <br>

                <strong>Selected shipping type:</strong> {{ delivery.shippingMethod.name }}<br>
                {{ delivery.shippingMethod.description }}<br>
                <br>

                {% set billingAddress = order.addresses.get(order.billingAddressId) %}
                <strong>Billing address:</strong><br>
                {{ billingAddress.company }}<br>
                {{ billingAddress.name }}<br>
                {{ billingAddress.street }} <br>
                {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
                {{ billingAddress.country.name }}<br>
                <br>

                <strong>Shipping address:</strong><br>
                {{ delivery.shippingOrderAddress.company }}<br>
                {{ delivery.shippingOrderAddress.name }}<br>
                {{ delivery.shippingOrderAddress.street }} <br>
                {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
                {{ delivery.shippingOrderAddress.country.name }}<br>
                <br>
                {% if billingAddress.vatId %}
                    Your VAT-ID: {{ billingAddress.vatId }}
                    In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.<br>
                {% endif %}
                <br/>
                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                </br>
                If you have any questions, do not hesitate to contact us.

            </p>
            <br>
            </div>
        ';
    }

    private function getOrderConfirmationPlainTemplateEn(): string
    {
        return '
        {% set currencyIsoCode = order.currency.isoCode %}
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        Thank you for your order at {{ salesChannel.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.

        Information on your order:

        Pos.   Art.No.			Description			Quantities			Price			Total
        {% for lineItem in order.lineItems %}
        {{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
        {% endfor %}

        {% set delivery = order.deliveries.first %}

        Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
        Net total: {{ order.amountNet|currency(currencyIsoCode) }}
            {% for calculatedTax in order.price.calculatedTaxes %}
                   {% if order.taxStatus is same as(\'net\') %}plus{% else %}including{% endif %} {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
            {% endfor %}
        Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}


        Selected payment type: {{ order.transactions.first.paymentMethod.name }}
        {{ order.transactions.first.paymentMethod.description }}

        Selected shipping type: {{ delivery.shippingMethod.name }}
        {{ delivery.shippingMethod.description }}

        {% set billingAddress = order.addresses.get(order.billingAddressId) %}
        Billing address:
        {{ billingAddress.company }}
        {{ billingAddress.name }}
        {{ billingAddress.street }}
        {{ billingAddress.zipcode }} {{ billingAddress.city }}
        {{ billingAddress.country.name }}

        Shipping address:
        {{ delivery.shippingOrderAddress.company }}
        {{ delivery.shippingOrderAddress.name }}
        {{ delivery.shippingOrderAddress.street }}
        {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
        {{ delivery.shippingOrderAddress.country.name }}

        {% if billingAddress.vatId %}
        Your VAT-ID: {{ billingAddress.vatId }}
        In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.
        {% endif %}

        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        If you have any questions, do not hesitate to contact us.

        However, in case you have purchased without a registration or a customer account, you do not have this option.';
    }

    private function getOrderConfirmationHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">

            {% set currencyIsoCode = order.currency.isoCode %}
            您好 {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br>
            <br>
            感谢您在 {{ salesChannel.name }} 下单（订单号：{{order.orderNumber}}），下单时间 {{ order.orderDateTime|date }}。<br>
            <br>
            <strong>订单信息：</strong><br>
            <br>

            <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
                <tr>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>Pos.</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>商品名称</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>数量</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>单价</strong></td>
                    <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>金额</strong></td>
                </tr>

                {% for lineItem in order.lineItems %}
                <tr>
                    <td style="border-bottom:1px solid #cccccc;">{{ loop.index }} </td>
                    <td style="border-bottom:1px solid #cccccc;">
                      {{ lineItem.label|u.wordwrap(80) }}<br>
                      {% if lineItem.payload.productNumber is defined %}商品编号：{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
                    </td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
                    <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
                </tr>
                {% endfor %}
            </table>

            {% set delivery = order.deliveries.first %}
            <p>
                <br>
                <br>
                运费：{{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
                净额合计：{{ order.amountNet|currency(currencyIsoCode) }}<br>
                    {% for calculatedTax in order.price.calculatedTaxes %}
                        {% if order.taxStatus is same as(\'net\') %}另加{% else %}含{% endif %} {{ calculatedTax.taxRate }}% 增值税 {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
                    {% endfor %}
                <strong>总额合计：{{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
                <br>

                <strong>支付方式：</strong> {{ order.transactions.first.paymentMethod.name }}<br>
                {{ order.transactions.first.paymentMethod.description }}<br>
                <br>

                <strong>配送方式：</strong> {{ delivery.shippingMethod.name }}<br>
                {{ delivery.shippingMethod.description }}<br>
                <br>

                {% set billingAddress = order.addresses.get(order.billingAddressId) %}
                <strong>账单地址：</strong><br>
                {{ billingAddress.company }}<br>
                {{ billingAddress.name }}<br>
                {{ billingAddress.street }} <br>
                {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
                {{ billingAddress.country.name }}<br>
                <br>

                <strong>收货地址：</strong><br>
                {{ delivery.shippingOrderAddress.company }}<br>
                {{ delivery.shippingOrderAddress.name }}<br>
                {{ delivery.shippingOrderAddress.street }} <br>
                {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
                {{ delivery.shippingOrderAddress.country.name }}<br>
                <br>
                {% if billingAddress.vatId %}
                    您的增值税号：{{ billingAddress.vatId }}
                    验证通过且您从欧盟境外
                    下单，将免税发货。 <br>
                {% endif %}
                <br/>
                您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
                </br>
                如有疑问，欢迎随时联系我们。

            </p>
            <br>
            </div>
        ';
    }

    private function getOrderConfirmationPlainTemplateZhCn(): string
    {
        return '
        {% set currencyIsoCode = order.currency.isoCode %}
        您好 {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

        感谢您在 {{ salesChannel.name }} 下单（订单号：{{order.orderNumber}}），下单时间 {{ order.orderDateTime|date }}。

        订单信息：

        序号 商品编号			商品名称			数量			单价			金额
        {% for lineItem in order.lineItems %}
        {{ loop.index }}     {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
        {% endfor %}

        {% set delivery = order.deliveries.first %}

        运费：{{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
        净额合计：{{ order.amountNet|currency(currencyIsoCode) }}
            {% for calculatedTax in order.price.calculatedTaxes %}
                {% if order.taxStatus is same as(\'net\') %}另加{% else %}含{% endif %} {{ calculatedTax.taxRate }}% 增值税 {{ calculatedTax.tax|currency(currencyIsoCode) }}
            {% endfor %}
        总额合计：{{ order.amountTotal|currency(currencyIsoCode) }}


        支付方式：{{ order.transactions.first.paymentMethod.name }}
        {{ order.transactions.first.paymentMethod.description }}

        配送方式：{{ delivery.shippingMethod.name }}
        {{ delivery.shippingMethod.description }}

        {% set billingAddress = order.addresses.get(order.billingAddressId) %}
        账单地址：
        {{ billingAddress.company }}
        {{ billingAddress.name }}
        {{ billingAddress.street }}
        {{ billingAddress.zipcode }} {{ billingAddress.city }}
        {{ billingAddress.country.name }}

        收货地址：
        {{ delivery.shippingOrderAddress.company }}
        {{ delivery.shippingOrderAddress.name }}
        {{ delivery.shippingOrderAddress.street }}
        {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
        {{ delivery.shippingOrderAddress.country.name }}

        {% if billingAddress.vatId %}
        您的增值税号：{{ billingAddress.vatId }}
        验证通过且您从欧盟境外
        下单，将免税发货。
        {% endif %}

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ path(\'frontend.account.edit-order.page\', { \'orderId\': order.id}) }}
        如有疑问，欢迎随时联系我们。';
    }
}
