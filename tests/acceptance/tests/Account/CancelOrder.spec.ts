import { test as base } from '@fixtures/AcceptanceTest';

const test = base.extend<{ OrderLabels: Record<string, string> }>({
    OrderLabels: async ({ AdminApiContext, DefaultSalesChannel, TestDataService, ShopCustomer }, use) => {
        const headers = { 'sw-language-id': DefaultSalesChannel.salesChannel.languageId };
        const statesResponse = await AdminApiContext.post('search/state-machine-state', {
            headers,
            data: {
                filter: [{ type: 'equalsAny', field: 'technicalName', value: ['open', 'cancelled'] }],
                associations: { stateMachine: {} },
            },
        });
        await ShopCustomer.expects(statesResponse.ok()).toBeTruthy();
        const { data: states } = (await statesResponse.json()) as {
            data: { technicalName: string; translated: { name: string }; stateMachine: { technicalName: string } }[];
        };
        const labels = Object.fromEntries(
            states.map((state) => [`${state.stateMachine.technicalName}.${state.technicalName}`, state.translated.name]),
        );
        const paymentMethod = await TestDataService.getPaymentMethod();
        const shippingMethod = await TestDataService.getShippingMethod();
        const paymentResponse = await AdminApiContext.get(`payment-method/${paymentMethod.id}`, { headers });
        const shippingResponse = await AdminApiContext.get(`shipping-method/${shippingMethod.id}`, { headers });
        await ShopCustomer.expects(paymentResponse.ok()).toBeTruthy();
        await ShopCustomer.expects(shippingResponse.ok()).toBeTruthy();
        labels.paymentMethod = (await paymentResponse.json()).data.translated.name;
        labels.shippingMethod = (await shippingResponse.json()).data.translated.name;
        await use(labels);
    },
});

test(
    'Customers are able to cancel orders in storefront account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, OrderLabels }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        const untouchedOrder = await TestDataService.createOrder([{ product: product, quantity: 1 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());

        const untouchedOrderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(untouchedOrder.orderNumber);
        await ShopCustomer.expects(untouchedOrderItemLocators.orderStatus).toContainText(OrderLabels['order.state.open']);

        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(OrderLabels['order.state.open']);
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderCancelButton);
        await ShopCustomer.expects(StorefrontAccountOrder.dialogOrderCancel).toBeFocused();
        await ShopCustomer.presses(StorefrontAccountOrder.dialogOrderCancelButton);
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText(
            OrderLabels['order_delivery.state.open'],
        );
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText(
            OrderLabels['order_transaction.state.open'],
        );
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText(OrderLabels.paymentMethod);
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText(OrderLabels.shippingMethod);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(OrderLabels['order.state.cancelled']);
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText(OrderLabels['order.state.open']);
        // ensure other order is unaffected
        await ShopCustomer.expects(untouchedOrderItemLocators.orderStatus).toContainText(OrderLabels['order.state.open']);
    },
);

test(
    'Customers are able to cancel orders on the final checkout page in storefront account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, StorefrontCheckoutOrderEdit, OrderLabels }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(OrderLabels['order.state.open']);
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);
        await ShopCustomer.presses(StorefrontCheckoutOrderEdit.orderCancelButton);
        await ShopCustomer.expects(StorefrontCheckoutOrderEdit.dialogOrderCancel).toBeFocused();
        await ShopCustomer.presses(StorefrontCheckoutOrderEdit.dialogOrderCancelButton);
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText(
            OrderLabels['order_delivery.state.open'],
        );
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText(
            OrderLabels['order_transaction.state.open'],
        );
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText(OrderLabels.paymentMethod);
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText(OrderLabels.shippingMethod);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(OrderLabels['order.state.cancelled']);
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText(OrderLabels['order.state.open']);
    },
);

test(
    'Customers are not able to cancel orders on the final checkout page in storefront account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, StorefrontCheckoutOrderEdit, OrderLabels }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(OrderLabels['order.state.open']);
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);
        await ShopCustomer.expects(StorefrontCheckoutOrderEdit.orderCancelButton).not.toBeVisible();
    },
);

test(
    'Customers are not able to cancel orders in storefront account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, OrderLabels }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText(OrderLabels['order.state.open']);
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.expects(orderItemLocators.orderCancelButton).not.toBeVisible();
    },
);
