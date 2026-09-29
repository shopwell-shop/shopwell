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
class Migration1588153272UpdateGermanMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1588153272;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $zhCnLangId = $this->fetchLanguageId('zh-CN', $connection);

        if ($zhCnLangId === null) {
            return;
        }

        // update order confirmation email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            $connection,
            $zhCnLangId,
            $this->getOrderConfirmationHTMLTemplateZhCn(),
            $this->getOrderConfirmationPlainTemplateZhCn()
        );

        // update delivery email templates
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            $connection,
            $zhCnLangId,
            $this->getDeliveryCancellationHtmlTemplateZhCn(),
            $this->getDeliveryCancellationPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            $connection,
            $zhCnLangId,
            $this->getDeliveryReturnedHtmlTemplateZhCn(),
            $this->getDeliveryReturnedPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            $connection,
            $zhCnLangId,
            $this->getDeliveryShippedPartiallyHtmlTemplateZhCn(),
            $this->getDeliveryShippedPartiallyPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            $connection,
            $zhCnLangId,
            $this->getDeliveryShippedHTMLTemplateZhCn(),
            $this->getDeliveryShippedPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            $connection,
            $zhCnLangId,
            $this->getDeliveryReturnedPartiallyHTMLTemplateZhCn(),
            $this->getDeliveryReturnedPartiallyPlainTemplateZhCn()
        );

        // update order state email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            $connection,
            $zhCnLangId,
            $this->getOrderStateCancelledHTMLTemplateZhCn(),
            $this->getOrderStateCancelledPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            $connection,
            $zhCnLangId,
            $this->getOrderStateOpenHTMLTemplateZhCn(),
            $this->getOrderStateOpenPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            $connection,
            $zhCnLangId,
            $this->getOrderStateProgressHTMLTemplateZhCn(),
            $this->getOrderStateProgressPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
            $connection,
            $zhCnLangId,
            $this->getOrderStateCompletedHTMLTemplateZhCn(),
            $this->getOrderStateCompletedPlainTemplateZhCn()
        );

        // update payment email template
        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            $connection,
            $zhCnLangId,
            $this->getPaymentRefundPartiallyHTMLTemplateZhCn(),
            $this->getPaymentRefundPartiallyPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            $connection,
            $zhCnLangId,
            $this->getPaymentRemindedHTMLTemplateZhCn(),
            $this->getPaymentRemindedPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            $connection,
            $zhCnLangId,
            $this->getPaymentOpenHTMLTemplateZhCn(),
            $this->getPaymentOpenPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            $connection,
            $zhCnLangId,
            $this->getPaymentPaidHTMLTemplateZhCn(),
            $this->getPaymentPaidPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            $connection,
            $zhCnLangId,
            $this->getPaymentCancelledHTMLTemplateZhCn(),
            $this->getPaymentCancelledPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            $connection,
            $zhCnLangId,
            $this->getPaymentRefundedHTMLTemplateZhCn(),
            $this->getPaymentRefundedPlainTemplateZhCn()
        );

        $this->updateMailTemplate(
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            $connection,
            $zhCnLangId,
            $this->getPaymentPaidPartiallyHTMLTemplateZhCn(),
            $this->getPaymentPaidPartiallyPlainTemplateZhCn()
        );
    }

    public function updateDestructive(Connection $connection): void
    {
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

    private function updateMailTemplate(
        string $mailTemplateType,
        Connection $connection,
        string $zhCnLangId,
        string $getHtmlTemplateZhCn,
        string $getPlainTemplateZhCn
    ): void {
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, $mailTemplateType);

        if ($templateId !== null) {
            $availableEntities = $this->fetchSystemMailTemplateAvailableEntitiesFromType($connection, $mailTemplateType);
            if (!isset($availableEntities['editOrderUrl'])) {
                $availableEntities['editOrderUrl'] = null;
                $sqlStatement = 'UPDATE `mail_template_type` SET `available_entities` = :availableEntities WHERE `technical_name` = :mailTemplateType AND `updated_at` IS NULL';
                $connection->executeStatement($sqlStatement, ['availableEntities' => json_encode($availableEntities, \JSON_THROW_ON_ERROR), 'mailTemplateType' => $mailTemplateType]);
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $getHtmlTemplateZhCn,
                $getPlainTemplateZhCn
            );
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

    /**
     * @return array<string, mixed>
     */
    private function fetchSystemMailTemplateAvailableEntitiesFromType(Connection $connection, string $mailTemplateType): array
    {
        $availableEntities = $connection->executeQuery(
            'SELECT `available_entities` FROM `mail_template_type` WHERE `technical_name` = :mailTemplateType AND updated_at IS NULL;',
            ['mailTemplateType' => $mailTemplateType]
        )->fetchOne();

        if ($availableEntities === false || !\is_string($availableEntities) || json_decode($availableEntities, true, 512, \JSON_THROW_ON_ERROR) === null) {
            return [];
        }

        return json_decode($availableEntities, true, 512, \JSON_THROW_ON_ERROR);
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

    private function getOrderConfirmationHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">

            {% set currencyIsoCode = order.currency.isoCode %}
            您好 {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
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
                {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
                {{ billingAddress.street }} <br>
                {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
                {{ billingAddress.country.name }}<br>
                <br>

                <strong>收货地址：</strong><br>
                {{ delivery.shippingOrderAddress.company }}<br>
                {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
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
                您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
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
        您好 {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

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
        {{ billingAddress.firstName }} {{ billingAddress.lastName }}
        {{ billingAddress.street }}
        {{ billingAddress.zipcode }} {{ billingAddress.city }}
        {{ billingAddress.country.name }}

        收货地址：
        {{ delivery.shippingOrderAddress.company }}
        {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
        {{ delivery.shippingOrderAddress.street }}
        {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
        {{ delivery.shippingOrderAddress.country.name }}

        {% if billingAddress.vatId %}
        您的增值税号：{{ billingAddress.vatId }}
        验证通过且您从欧盟境外
        下单，将免税发货。
        {% endif %}

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        如有疑问，欢迎随时联系我们。';
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
               您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
               </br>
               若您未注册、未开通客户账户即下单，则无法使用该功能。
           </p>
        </div>';
    }

    private function getDeliveryCancellationPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        支付状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
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
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>';
    }

    private function getDeliveryReturnedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
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
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>';
    }

    private function getDeliveryShippedPartiallyPlainTemplateZhCn(): string
    {
        return '
            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        订单状态最新状态：{{order.deliveries.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
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
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
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

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
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
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
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

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getOrderStateCancelledHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getOrderStateCancelledPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新!
        订单状态最新状态：{{order.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getOrderStateOpenHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getOrderStateOpenPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新!
        订单状态最新状态：{{order.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getOrderStateProgressHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getOrderStateProgressPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新!
        订单状态最新状态：{{order.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getOrderStateCompletedHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新.<br/>
                    <strong>订单状态最新状态：{{order.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getOrderStateCompletedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新!
        订单状态最新状态：{{order.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentRefundPartiallyHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentRefundPartiallyPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentRemindedHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentRemindedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentOpenHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentOpenPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentPaidHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentPaidPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentCancelledHTMLTemplateZhCn(): string
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
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentCancelledPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentRefundedHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentRefundedPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }

    private function getPaymentPaidPartiallyHTMLTemplateZhCn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br/>
                    <br/>
                    您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新.<br/>
                    <strong>支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.</strong><br/>
                    <br/>
                    您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
                    </br>
                    若您未注册、未开通客户账户即下单，则无法使用该功能。
                </p>
            </div>
        ';
    }

    private function getPaymentPaidPartiallyPlainTemplateZhCn(): string
    {
        return '
        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

        您在 {{ salesChannel.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新!
        支付状态最新状态：{{order.transactions.first.stateMachineState.name}}.

        您可随时在网站的「我的账户」-「我的订单」查看订单当前状态：{{ rawUrl(\'frontend.account.edit-order.page\', { \'orderId\': order.id}, salesChannel.domains|first.url) }}
        若您未注册、未开通客户账户即下单，则无法使用该功能。';
    }
}
