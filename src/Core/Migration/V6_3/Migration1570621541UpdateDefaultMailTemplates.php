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
class Migration1570621541UpdateDefaultMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570621541;
    }

    public function update(Connection $connection): void
    {
        // implement update
        $defaultLangId = $this->fetchLanguageId('en-GB', $connection);
        $zhCnLangId = $this->fetchLanguageId('zh-CN', $connection);

        // update order confirmation
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_ORDER_CONFIRM);
        if ($templateId !== null) {
            if ($defaultLangId !== $zhCnLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getOrderConfirmationHtmlTemplateEn(),
                    $this->getOrderConfirmationPlainTemplateEn()
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getOrderConfirmationHtmlTemplateEn(),
                    $this->getOrderConfirmationPlainTemplateEn()
                );
            }

            if ($zhCnLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $zhCnLangId,
                    $this->getOrderConfirmationHtmlTemplateZhCn(),
                    $this->getOrderConfirmationPlainTemplateZhCn()
                );
            }
        }

        // update customer registration
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER);
        if ($templateId !== null) {
            if ($defaultLangId !== $zhCnLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getRegistrationHtmlTemplateEn(),
                    $this->getRegistrationPlainTemplateEn()
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getRegistrationHtmlTemplateEn(),
                    $this->getRegistrationPlainTemplateEn()
                );
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $this->getRegistrationHtmlTemplateZhCn(),
                $this->getRegistrationPlainTemplateZhCn()
            );
        }

        // update password change
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE);
        if ($templateId !== null) {
            if ($defaultLangId !== $zhCnLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getPasswordChangeHtmlTemplateEn(),
                    $this->getPasswordChangePlainTemplateEn()
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getPasswordChangeHtmlTemplateEn(),
                    $this->getPasswordChangePlainTemplateEn()
                );
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $this->getPasswordChangeHtmlTemplateZhCn(),
                $this->getPasswordChangePlainTemplateZhCn()
            );
        }

        // update newsletter register
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, 'newsletterRegister');
        if ($templateId !== null) {
            if ($defaultLangId !== $zhCnLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getRegisterTemplate_HTML_EN(),
                    $this->getRegisterTemplate_PLAIN_EN(),
                    '{{ salesChannel.translated.name }}'
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getRegisterTemplate_HTML_EN(),
                    $this->getRegisterTemplate_PLAIN_EN(),
                    '{{ salesChannel.translated.name }}'
                );
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $this->getRegisterTemplate_HTML_ZH_CN(),
                $this->getRegisterTemplate_PLAIN_ZH_CN(),
                '{{ salesChannel.translated.name }}'
            );
        }

        // update newsletter opt in
        $templateId = $this->fetchSystemMailTemplateIdFromType($connection, 'newsletterDoubleOptIn');
        if ($templateId !== null) {
            if ($defaultLangId !== $zhCnLangId) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    $defaultLangId,
                    $this->getOptInTemplate_HTML_EN(),
                    $this->getOptInTemplate_PLAIN_EN(),
                    '{{ salesChannel.translated.name }}'
                );
            }

            if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $this->updateMailTemplateTranslation(
                    $connection,
                    $templateId,
                    Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    $this->getOptInTemplate_HTML_EN(),
                    $this->getOptInTemplate_PLAIN_EN(),
                    '{{ salesChannel.translated.name }}'
                );
            }

            $this->updateMailTemplateTranslation(
                $connection,
                $templateId,
                $zhCnLangId,
                $this->getOptInTemplate_HTML_ZH_CN(),
                $this->getOptInTemplate_PLAIN_ZH_CN(),
                '{{ salesChannel.translated.name }}'
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
            'enLangId' => $langId,
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

        $sqlString = 'UPDATE `mail_template_translation` SET ' . $sqlString . 'WHERE `mail_template_id`= :templateId AND `language_id` = :enLangId AND `updated_at` IS NULL';

        $connection->executeStatement($sqlString, $sqlParams);
    }

    private function getOrderConfirmationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.translated.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
<br>
Thank you for your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.<br>
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
          {{ lineItem.label|wordwrap(80) }}<br>
          Art. No.: {{ lineItem.payload.productNumber|wordwrap(80) }}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery =order.deliveries.first %}
<p>
    <br>
    <br>
    Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
    Net total: {{ order.amountNet|currency(currencyIsoCode) }}<br>
    {% if order.taxStatus is same as(\'net\') %}
        {% for calculatedTax in order.cartPrice.calculatedTaxes %}
            plus {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
        <strong>Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
    {% endif %}
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
    {{ billingAddress.firstName }} {{ billingAddress.lastName }}<br>
    {{ billingAddress.street }} <br>
    {{ billingAddress.zipcode }} {{ billingAddress.city }}<br>
    {{ billingAddress.country.name }}<br>
    <br>

    <strong>Shipping address:</strong><br>
    {{ delivery.shippingOrderAddress.company }}<br>
    {{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}<br>
    {{ delivery.shippingOrderAddress.street }} <br>
    {{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}<br>
    {{ delivery.shippingOrderAddress.country.name }}<br>
    <br>
    {% if billingAddress.vatId %}
        Your VAT-ID: {{ billingAddress.vatId }}
        In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.<br>
    {% endif %}

    If you have any questions, do not hesitate to contact us.

</p>
<br>
</div>';
    }

    private function getOrderConfirmationPlainTemplateEn(): string
    {
        return '{% set currencyIsoCode = order.currency.isoCode %}
{{order.orderCustomer.salutation.translated.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

Thank you for your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.

Information on your order:

Pos.   Art.No.			Description			Quantities			Price			Total

{% for lineItem in order.lineItems %}
{{ loop.index }}      {{ lineItem.payload.productNumber|wordwrap(80) }}				{{ lineItem.label|wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery =order.deliveries.first %}

Shipping costs: {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
Net total: {{ order.amountNet|currency(currencyIsoCode) }}
{% if order.taxStatus is same as(\'net\') %}
	{% for calculatedTax in order.cartPrice.calculatedTaxes %}
		plus {{ calculatedTax.taxRate }}% VAT. {{ calculatedTax.tax|currency(currencyIsoCode) }}
	{% endfor %}
	Total gross: {{ order.amountTotal|currency(currencyIsoCode) }}
{% endif %}

Selected payment type: {{ order.transactions.first.paymentMethod.name }}
{{ order.transactions.first.paymentMethod.description }}

Selected shipping type: {{ delivery.shippingMethod.name }}
{{ delivery.shippingMethod.description }}

{% set billingAddress = order.addresses.get(order.billingAddressId) %}
Billing address:
{{ billingAddress.company }}
{{ billingAddress.firstName }} {{ billingAddress.lastName }}
{{ billingAddress.street }}
{{ billingAddress.zipcode }} {{ billingAddress.city }}
{{ billingAddress.country.name }}

Shipping address:
{{ delivery.shippingOrderAddress.company }}
{{ delivery.shippingOrderAddress.firstName }} {{ delivery.shippingOrderAddress.lastName }}
{{ delivery.shippingOrderAddress.street }}
{{ delivery.shippingOrderAddress.zipcode}} {{ delivery.shippingOrderAddress.city }}
{{ delivery.shippingOrderAddress.country.name }}

{% if billingAddress.vatId %}
Your VAT-ID: {{ billingAddress.vatId }}
In case of a successful order and if you are based in one of the EU countries, you will receive your goods exempt from turnover tax.
{% endif %}

If you have any questions, do not hesitate to contact us.

';
    }

    private function getOrderConfirmationHtmlTemplateZhCn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">

{% set currencyIsoCode = order.currency.isoCode %}
您好 {{order.orderCustomer.salutation.translated.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},<br>
<br>
感谢您在 {{ salesChannel.translated.name }} 下单（订单号：{{order.orderNumber}}），下单时间 {{ order.orderDateTime|date }}。<br>
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
          {{ lineItem.label|wordwrap(80) }}<br>
          商品编号：{{ lineItem.payload.productNumber|wordwrap(80) }}
        </td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.quantity }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.unitPrice|currency(currencyIsoCode) }}</td>
        <td style="border-bottom:1px solid #cccccc;">{{ lineItem.totalPrice|currency(currencyIsoCode) }}</td>
    </tr>
    {% endfor %}
</table>

{% set delivery =order.deliveries.first %}
<p>
    <br>
    <br>
    运费：{{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
    净额合计：{{ order.amountNet|currency(currencyIsoCode) }}<br>
    {% if order.taxStatus is same as(\'net\') %}
        {% for calculatedTax in order.cartPrice.calculatedTaxes %}
            另加 {{ calculatedTax.taxRate }}% 增值税 {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
        {% endfor %}
        <strong>总额合计：{{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
    {% endif %}
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

    如有疑问，欢迎随时联系我们。

</p>
<br>
</div>';
    }

    private function getOrderConfirmationPlainTemplateZhCn(): string
    {
        return '{% set currencyIsoCode = order.currency.isoCode %}
您好 {{order.orderCustomer.salutation.translated.letterName }} {{order.orderCustomer.firstName}} {{order.orderCustomer.lastName}},

感谢您在 {{ salesChannel.translated.name }} 下单（订单号：{{order.orderNumber}}），下单时间 {{ order.orderDateTime|date }}。

订单信息：

序号 商品编号			商品名称			数量			单价			金额
{% for lineItem in order.lineItems %}
{{ loop.index }}     {{ lineItem.payload.productNumber|wordwrap(80) }}				{{ lineItem.label|wordwrap(80) }}			{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
{% endfor %}

{% set delivery =order.deliveries.first %}

运费：{{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
净额合计：{{ order.amountNet|currency(currencyIsoCode) }}
{% if order.taxStatus is same as(\'net\') %}
	{% for calculatedTax in order.cartPrice.calculatedTaxes %}
		另加 {{ calculatedTax.taxRate }}% 增值税 {{ calculatedTax.tax|currency(currencyIsoCode) }}
	{% endfor %}
	总额合计：{{ order.amountTotal|currency(currencyIsoCode) }}
{% endif %}

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

如有疑问，欢迎随时联系我们。

';
    }

    private function getRegistrationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                {{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }},<br/>
                <br/>
                thank you for your signing up with our Shop.<br/>
                You will gain access via the email address <strong>{{ customer.email }}</strong> and the password you have chosen.<br/>
                You can change your password anytime.
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateEn(): string
    {
        return '{{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},

                thank you for your signing up with our Shop.
                You will gain access via the email address {{ customer.email }} and the password you have chosen.
                You can change your password anytime.
        ';
    }

    private function getRegistrationHtmlTemplateZhCn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                您好 {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }}，<br/>
                <br/>
                感谢您注册我们的商城。<br/>
                您可以使用邮箱 <strong>{{ customer.email }}</strong> 和您设置的密码登录。<br/>
                您可以随时修改密码。
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateZhCn(): string
    {
        return '您好 {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }}，

                感谢您注册我们的商城。
                您可以使用邮箱 {{ customer.email }} 和您设置的密码登录。
                您可以随时修改密码。
';
    }

    private function getPasswordChangeHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        {{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }},<br/>
        <br/>
        there has been a request to reset you Password in the Shop {{ salesChannel.translated.name }}
        Please confirm the link below to specify a new password.<br/>
        <br/>
        <a href="{{ urlResetPassword }}">Reset passwort</a><br/>
        <br/>
        This link is valid for the next 2 hours. After that you have to request a new confirmation link.<br/>
        If you do not want to reset your password, please ignore this email. No changes will be made.
    </p>
</div>';
    }

    private function getPasswordChangePlainTemplateEn(): string
    {
        return '
        {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},

        there has been a request to reset you Password in the Shop {{ salesChannel.translated.name }}
        Please confirm the link below to specify a new password.

        Reset password: {{ urlResetPassword }}

        This link is valid for the next 2 hours. After that you have to request a new confirmation link.
        If you do not want to reset your password, please ignore this email. No changes will be made.
    ';
    }

    private function getPasswordChangeHtmlTemplateZhCn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        您好 {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }}，<br/>
        <br/>
        有人请求在 {{ salesChannel.translated.name }} 商城重置您的密码。
        请点击下方链接设置新密码。<br/>
        <br/>
        <a href="{{ urlResetPassword }}">重置密码</a><br/>
        <br/>
        该链接在 2 小时内有效，逾期需重新申请。
        如果您不希望重置密码，请忽略此邮件，我们不会做任何修改。
    </p>
</div>';
    }

    private function getPasswordChangePlainTemplateZhCn(): string
    {
        return '
        您好 {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }}，

        有人请求在 {{ salesChannel.translated.name }} 商城重置您的密码。
        请点击下方链接设置新密码。

        重置密码：{{ urlResetPassword }}

        该链接在 2 小时内有效，逾期需重新申请。
        如果您不希望重置密码，请忽略此邮件，我们不会做任何修改。
';
    }

    private function getRegisterTemplate_HTML_EN(): string
    {
        return '<h3>Hello {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}</h3>
                <p>thank you very much for your registration.</p>
                <p>You have successfully subscribed to our newsletter.</p>
        ';
    }

    private function getRegisterTemplate_PLAIN_EN(): string
    {
        return 'Hello {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}

                thank you very much for your registration.

                You have successfully subscribed to our newsletter.
        ';
    }

    private function getRegisterTemplate_HTML_ZH_CN(): string
    {
        return '<h3>您好 {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}</h3>
                <p>感谢您的注册。</p>
                <p>您已成功订阅我们的邮件通讯。</p>
        ';
    }

    private function getRegisterTemplate_PLAIN_ZH_CN(): string
    {
        return '您好 {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}

                感谢您的注册。

                您已成功订阅我们的邮件通讯。
        ';
    }

    private function getOptInTemplate_HTML_EN(): string
    {
        return '<h3>Hello {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}</h3>
                <p>Thank you for your interest in our newsletter!</p>
                <p>In order to prevent misuse of your email address, we have sent you this confirmation email. Confirm that you wish to receive the newsletter regularly by clicking <a href="{{ url }}">here</a>.</p>
                <p>If you have not subscribed to the newsletter, please ignore this email.</p>
        ';
    }

    private function getOptInTemplate_PLAIN_EN(): string
    {
        return 'Hello {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}

                Thank you for your interest in our newsletter!

                In order to prevent misuse of your email address, we have sent you this confirmation email. Confirm that you wish to receive the newsletter regularly by clicking on the link: {{ url }}

                If you have not subscribed to the newsletter, please ignore this email.
        ';
    }

    private function getOptInTemplate_HTML_ZH_CN(): string
    {
        return '<h3>您好 {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}</h3>
                <p>感谢您关注我们的邮件通讯！</p>
                <p>为防止您的邮箱地址被他人冒用，我们向您发送了这封确认邮件。请点击<a href="{{ url }}">此处</a>，确认您希望定期收到邮件通讯。</p>
                <p>如果您并未订阅邮件通讯，请忽略此邮件。</p>
        ';
    }

    private function getOptInTemplate_PLAIN_ZH_CN(): string
    {
        return '您好 {{ newsletterRecipient.firstName }} {{ newsletterRecipient.lastName }}

                感谢您关注我们的邮件通讯！

                为防止您的邮箱地址被他人冒用，我们向您发送了这封确认邮件。请点击以下链接，确认您希望定期收到邮件通讯：{{ url }}

                如果您并未订阅邮件通讯，请忽略此邮件。
        ';
    }
}
