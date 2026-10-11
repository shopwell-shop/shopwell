<?php declare(strict_types=1);

return [
    'OrderConfirmation' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">

                {% set currencyIsoCode = order.currency.isoCode %}
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br>
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
                          {{ lineItem.label|u.wordwrap(80) }}<br>
                            {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
                                {% for option in lineItem.payload.options %}
                                    {{ option.group }}: {{ option.option }}
                                    {% if lineItem.payload.options|last != option %}
                                        {{ " | " }}
                                    {% endif %}
                                {% endfor %}
                                <br/>
                            {% endif %}
                          {% if lineItem.payload.productNumber is defined %}Prod. No.: {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
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
                    You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                    </br>
                    If you have any questions, do not hesitate to contact us.

                </p>
                <br>
                </div>
            ',
            'plain' => '
                {% set currencyIsoCode = order.currency.isoCode %}
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                Thank you for your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}.

                Information on your order:

                Pos.   Prod. No.			Description			Quantities			Price			Total
                {% for lineItem in order.lineItems %}
                {{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}				{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
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

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                If you have any questions, do not hesitate to contact us.

                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">

                {% set currencyIsoCode = order.currency.isoCode %}
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br>
                <br>
                感谢您在 {{ salesChannel.translated.name }} (订单号： {{order.orderNumber}}) 于 {{ order.orderDateTime|date }}.<br>
                <br>
                <strong>订单信息：</strong><br>
                <br>

                <table width="80%" border="0" style="font-family:Arial, Helvetica, sans-serif; font-size:12px;">
                    <tr>
                        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>序号</strong></td>
                        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>商品</strong></td>
                        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>数量</strong></td>
                        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>单价</strong></td>
                        <td bgcolor="#F7F7F2" style="border-bottom:1px solid #cccccc;"><strong>金额</strong></td>
                    </tr>

                    {% for lineItem in order.lineItems %}
                    <tr>
                        <td style="border-bottom:1px solid #cccccc;">{{ loop.index }} </td>
                        <td style="border-bottom:1px solid #cccccc;">
                          {{ lineItem.label|u.wordwrap(80) }}<br>
                            {% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}
                                {% for option in lineItem.payload.options %}
                                    {{ option.group }}: {{ option.option }}
                                    {% if lineItem.payload.options|last != option %}
                                        {{ " | " }}
                                    {% endif %}
                                {% endfor %}
                                <br/>
                            {% endif %}
                          {% if lineItem.payload.productNumber is defined %}商品编号： {{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}
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
                    运费： {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}<br>
                    不含税总额： {{ order.amountNet|currency(currencyIsoCode) }}<br>
                        {% for calculatedTax in order.price.calculatedTaxes %}
                            {% if order.taxStatus is same as(\'net\') %}另加{% else %}含{% endif %} {{ calculatedTax.taxRate }}% 税 {{ calculatedTax.tax|currency(currencyIsoCode) }}<br>
                        {% endfor %}
                    <strong>含税总额： {{ order.amountTotal|currency(currencyIsoCode) }}</strong><br>
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
                        您的增值税号： {{ billingAddress.vatId }}
                        验证通过且您从欧盟境外
                        下单，将免税发货。 <br>
                    {% endif %}
                    <br/>
                    您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                    </br>
                    如有疑问，随时联系我们。

                </p>
                <br>
                </div>
            ',
            'plain' => '
                {% set currencyIsoCode = order.currency.isoCode %}
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                感谢您在 {{ salesChannel.translated.name }} (订单号： {{order.orderNumber}}) 于 {{ order.orderDateTime|date }}.

                订单信息：

                序号   商品编号			商品名称			数量			单价			金额
                {% for lineItem in order.lineItems %}
                {{ loop.index }}      {% if lineItem.payload.productNumber is defined %}{{ lineItem.payload.productNumber|u.wordwrap(80) }}{% endif %}				{{ lineItem.label|u.wordwrap(80) }}{% if lineItem.payload.options is defined and lineItem.payload.options|length >= 1 %}, {% for option in lineItem.payload.options %}{{ option.group }}: {{ option.option }}{% if lineItem.payload.options|last != option %}{{ " | " }}{% endif %}{% endfor %}{% endif %}				{{ lineItem.quantity }}			{{ lineItem.unitPrice|currency(currencyIsoCode) }}			{{ lineItem.totalPrice|currency(currencyIsoCode) }}
                {% endfor %}

                {% set delivery = order.deliveries.first %}

                运费： {{order.deliveries.first.shippingCosts.totalPrice|currency(currencyIsoCode) }}
                不含税总额： {{ order.amountNet|currency(currencyIsoCode) }}
                    {% for calculatedTax in order.price.calculatedTaxes %}
                        {% if order.taxStatus is same as(\'net\') %}另加{% else %}含{% endif %} {{ calculatedTax.taxRate }}% 税 {{ calculatedTax.tax|currency(currencyIsoCode) }}
                    {% endfor %}
                含税总额： {{ order.amountTotal|currency(currencyIsoCode) }}


                支付方式： {{ order.transactions.first.paymentMethod.name }}
                {{ order.transactions.first.paymentMethod.description }}

                配送方式： {{ delivery.shippingMethod.name }}
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
                您的增值税号： {{ billingAddress.vatId }}
                验证通过且您从欧盟境外
                下单，将免税发货。
                {% endif %}

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                如有疑问，随时联系我们。',
        ],
    ],
    'OrderCancelled' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                 <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.</p>
                </div>
            ',
            'plain' => '

                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新。<br/>
                        <strong>订单最新状态：{{order.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新！
                订单最新状态：{{order.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'OrderOpen' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                        <p>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新。<br/>
                        <strong>订单最新状态：{{order.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新！
                订单最新状态：{{order.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'OrderInProgress' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新。<br/>
                        <strong>订单最新状态：{{order.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新！
                订单最新状态：{{order.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'OrderCompleted' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                        <p>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.stateMachineState.name}}.</strong><br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新。<br/>
                        <strong>订单最新状态：{{order.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的订单状态已更新！
                订单最新状态：{{order.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'DeliveryCancellation' => [
        'en-GB' => [
            'html' => '<div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
                </div>',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                   <br/>
                   <p>
                       {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                       <br/>
                       您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新。<br/>
                       <strong>配送最新状态：{{order.deliveries.first.stateMachineState.name}}。</strong><br/>
                       <br/>
                       您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                       </br>
                       若您未注册、未开通客户账户即下单，则无法使用该功能。
                   </p>
                </div>',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新！
                配送最新状态：{{order.deliveries.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'DeliveryShippedPartially' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                   <br/>
                   <p>
                       {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                       <br/>
                       the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                       <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                       <br/>
                       You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                   </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新。<br/>
                        <strong>配送最新状态：{{order.deliveries.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>',
            'plain' => '
                    {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新！
                配送最新状态：{{order.deliveries.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'DeliveryShipped' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新。<br/>
                        <strong>配送最新状态：{{order.deliveries.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新！
                配送最新状态：{{order.deliveries.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'DeliveryReturnedPartially' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                        <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                        <br/>
                        You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新。<br/>
                        <strong>配送最新状态：{{order.deliveries.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新！
                配送最新状态：{{order.deliveries.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'DeliveryReturned' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                      <p>
                          {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                          <br/>
                          the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                          <strong>The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.</strong><br/>
                          <br/>
                          You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                          </br>
                          However, in case you have purchased without a registration or a customer account, you do not have this option.
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your delivery at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.deliveries.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新。<br/>
                        <strong>配送最新状态：{{order.deliveries.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的配送状态已更新！
                配送最新状态：{{order.deliveries.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'PaymentRefundedPartially' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                        <p>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                            <br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新。<br/>
                        <strong>支付最新状态：{{order.transactions.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新！
                支付最新状态：{{order.transactions.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'PaymentRefunded' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                        <p>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新。<br/>
                        <strong>支付最新状态：{{order.transactions.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新！
                支付最新状态：{{order.transactions.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'PaymentReminded' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                        <p>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新。<br/>
                        <strong>支付最新状态：{{order.transactions.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新！
                支付最新状态：{{order.transactions.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'PaymentOpen' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                        <p>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新。<br/>
                        <strong>支付最新状态：{{order.transactions.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新！
                支付最新状态：{{order.transactions.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'PaymentCancelled' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                        <p>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                       {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                       <br/>
                       您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新。<br/>
                       <strong>支付最新状态：{{order.transactions.first.stateMachineState.name}}。</strong><br/>
                       <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新！
                支付最新状态：{{order.transactions.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'PaymentPaidPartially' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                        <p>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新。<br/>
                        <strong>支付最新状态：{{order.transactions.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新！
                支付最新状态：{{order.transactions.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'PaymentPaid' => [
        'en-GB' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                        <p>
                            {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                            <br/>
                            the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }} has changed.<br/>
                            <strong>The new status is as follows: {{order.transactions.first.stateMachineState.name}}.</strong><br/>
                            <br/>
                            You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                            </br>
                            However, in case you have purchased without a registration or a customer account, you do not have this option.
                        </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                the status of your order at {{ salesChannel.translated.name }} (Number: {{order.orderNumber}}) on {{ order.orderDateTime|date }}  has changed.
                The new status is as follows: {{order.transactions.first.stateMachineState.name}}.

                You can check the current status of your order on our website under "My account" - "My orders" anytime: {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                However, in case you have purchased without a registration or a customer account, you do not have this option.',
        ],
        'zh-CN' => [
            'html' => '
                <div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},<br/>
                        <br/>
                        您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新。<br/>
                        <strong>支付最新状态：{{order.transactions.first.stateMachineState.name}}。</strong><br/>
                        <br/>
                        您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                        </br>
                        若您未注册、未开通客户账户即下单，则无法使用该功能。
                    </p>
                </div>
            ',
            'plain' => '
                {{order.orderCustomer.salutation.letterName }} {{order.orderCustomer.name}},

                您在 {{ salesChannel.translated.name }}（订单号：{{order.orderNumber}}）于 {{ order.orderDateTime|date }} 的支付状态已更新！
                支付最新状态：{{order.transactions.first.stateMachineState.name}}。

                您也可随时在网站的"我的账户"-"我的订单"查看订单状态： {{ rawUrl(\'frontend.account.order.single.page\', { \'deepLinkCode\': order.deepLinkCode}, salesChannel.domains|first.url) }}
                若您未注册、未开通客户账户即下单，则无法使用该功能。',
        ],
    ],
    'customer.group.registration.accepted' => [
        'en-GB' => [
            'html' => '<div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{ customer.salutation.letterName }} {{ customer.name }},<br/>
                        <br/>
                        Your account has been activated for the customer group {{ customerGroup.translated.name }}.<br/>
                        From now on you can shop at the new conditions of this customer group.<br/><br/>

                        Please do not hesitate to contact us at any time if you have any questions.
                    </p>
                </div>',
            'plain' => 'Hello {{ customer.salutation.letterName }} {{ customer.name }},<br/>
Your account has been activated for the customer group {{ customerGroup.translated.name }}.<br/>
From now on you can shop at the new conditions of this customer group.<br/><br/>

Please do not hesitate to contact us at any time if you have any questions.',
        ],
        'zh-CN' => [
            'html' => '<div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{ customer.salutation.letterName }} {{ customer.name }},<br/>
                    <br/>
                    您的账户已开通客户群 {{ customerGroup.translated.name }} 的权限。<br/>
                    即日起，您可以按该客户群的新价格条件下单。<br/>

                    如有疑问，随时联系我们。
                </p>
            </div>',
            'plain' => '{{ customer.salutation.letterName }} {{ customer.name }},<br/>
您的账户已开通客户群 {{ customerGroup.translated.name }} 的权限。
即日起，您可以按该客户群的新价格条件下单。<br/><br/>

如有疑问，随时联系我们。',
        ],
    ],
    'customer.group.registration.declined' => [
        'en-GB' => [
            'html' => '<div style="font-family:arial; font-size:12px;">
                    <br/>
                    <p>
                        {{ customer.salutation.letterName }} {{ customer.name }},<br/>
                        <br/>
                        Thank you for your interest in the conditions for customer group {{ customerGroup.translated.name }}.<br/>
                        Unfortunately we cannot activate your account for this customer group.<br/><br/>

                        If you have any questions, please feel free to contact us by phone or mail.
                    </p>
                </div>',
            'plain' => '{{ customer.salutation.letterName }} {{ customer.name }},<br/>
Thank you for your interest in the conditions for customer group {{ customerGroup.translated.name }}.<br/>
Unfortunately we cannot activate your account for this customer group.

If you have any questions, please feel free to contact us by phone or mail.',
        ],
        'zh-CN' => [
            'html' => '<div style="font-family:arial; font-size:12px;">
                <br/>
                <p>
                    {{ customer.salutation.letterName }} {{ customer.name }},<br/>
                    <br/>
                    感谢您对客户群 {{ customerGroup.translated.name }} 价格条件的关注。<br/>
                    很遗憾，我们无法为该客户群开通您的账户。<br/>

                    如有任何疑问，欢迎随时通过电话或邮件联系我们。
                </p>
            </div>',
            'plain' => '{{ customer.salutation.letterName }} {{ customer.name }},<br/>
感谢您对客户群 {{ customerGroup.translated.name }} 价格条件的关注。
很遗憾，我们无法为该客户群开通您的账户。<br/>

如有任何疑问，欢迎随时通过电话或邮件联系我们。',
        ],
    ],
];
