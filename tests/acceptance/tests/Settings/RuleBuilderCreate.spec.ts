import { getLanguageCode, test } from '@fixtures/AcceptanceTest';

test(
    'As an admin user, I want to create a rule',
    { tag: '@Rule' },
    async ({ Translate, AdminRuleDetail, AdminRuleListing, ShopAdmin, IdProvider, TestDataService }) => {
        const { id: uniqueId } = IdProvider.getIdPair();
        const { id: taxId, name: taxName } = await TestDataService.createTaxRate();
        const { id: ruleTagId, name: ruleTag } = await TestDataService.createTag(`Test tag - ${uniqueId}`);

        const dateFormatter = new Intl.DateTimeFormat(getLanguageCode(), {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            timeZone: 'UTC',
        });

        const testConfig = {
            ruleName: `Test rule - ${uniqueId}`,
            ruleTypes: [
                'Price',
                'Shipping',
                'Payment',
                'Flow Builder',
            ],
            rulePriority: 1,
            ruleDescription: 'This is a test rule, created to test the Rule Builder.',
            ruleTag,
            taxId,
            taxName,
            customerName: 'Schmitz-Rimpler',
            fromDate: '2025-01-14T08:30:00',
            toDate: '2025-01-15T16:45:00',
            quantity: 5,
            isAdminOrder: false,
            stock: 10,
        };

        const rule = await TestDataService.createBasicRule({
            name: testConfig.ruleName,
            priority: testConfig.rulePriority,
            description: testConfig.ruleDescription,
            moduleTypes: {
                types: testConfig.ruleTypes.map((type) => type.toLowerCase().split(' ')[0]),
            },
            tags: [
                {
                    id: ruleTagId,
                    name: testConfig.ruleTag,
                },
            ],
            conditions: [
                {
                    type: 'orContainer',
                    children: [
                        {
                            type: 'andContainer',
                            children: [
                                {
                                    type: 'cartLineItemGoodsTotal',
                                    value: {
                                        count: testConfig.quantity,
                                        operator: '>=',
                                    },
                                    children: [
                                        {
                                            type: 'orContainer',
                                            children: [
                                                {
                                                    type: 'andContainer',
                                                    children: [
                                                        {
                                                            type: 'cartLineItemActualStock',
                                                            value: {
                                                                stock: testConfig.stock,
                                                                operator: '>=',
                                                            },
                                                        },
                                                    ],
                                                },
                                            ],
                                        },
                                    ],
                                },
                                {
                                    type: 'dateRange',
                                    value: {
                                        toDate: testConfig.toDate,
                                        useTime: false,
                                        fromDate: testConfig.fromDate,
                                    },
                                },
                                {
                                    type: 'orContainer',
                                    children: [
                                        {
                                            type: 'customerName',
                                            value: {
                                                name: testConfig.customerName,
                                                operator: '=',
                                            },
                                        },
                                    ],
                                },
                            ],
                        },
                        {
                            type: 'andContainer',
                            children: [
                                {
                                    type: 'cartLineItemTaxation',
                                    value: {
                                        taxIds: [testConfig.taxId],
                                        operator: '=',
                                    },
                                },
                                {
                                    type: 'timeRange',
                                    value: {
                                        toTime: testConfig.toDate.split('T')[1].substring(0, 5),
                                        fromTime: testConfig.fromDate.split('T')[1].substring(0, 5),
                                    },
                                },
                                {
                                    type: 'orContainer',
                                    children: [
                                        {
                                            type: 'orderCreatedByAdmin',
                                            value: {
                                                shouldOrderBeCreatedByAdmin: testConfig.isAdminOrder,
                                            },
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            ],
        });

        await test.step('Validate rule in listing', async () => {
            await ShopAdmin.goesTo(AdminRuleListing.url(uniqueId));

            await ShopAdmin.expects(AdminRuleListing.gridCell.getByText(testConfig.ruleName)).toBeVisible();
        });

        await test.step('Validate general data', async () => {
            await ShopAdmin.goesTo(AdminRuleDetail.url(rule.id));

            await ShopAdmin.expects(AdminRuleDetail.header).toHaveText(testConfig.ruleName);
            await ShopAdmin.expects(AdminRuleDetail.nameInput).toHaveValue(testConfig.ruleName);
            await ShopAdmin.expects(AdminRuleDetail.priorityInput).toHaveValue(testConfig.rulePriority.toString());
            await ShopAdmin.expects(AdminRuleDetail.descriptionInput).toHaveValue(testConfig.ruleDescription);

            for (const ruleType of [
                Translate('administration:rule:types.price'),
                Translate('administration:rule:types.shipping'),
                Translate('administration:rule:types.payment'),
                Translate('administration:rule:types.flowBuilder'),
            ]) {
                await ShopAdmin.expects(AdminRuleDetail.typeItem.getByText(ruleType)).toBeVisible();
            }

            await ShopAdmin.expects(AdminRuleDetail.tagItem.getByText(testConfig.ruleTag)).toBeVisible();
        });

        await test.step('Validate conditions', async () => {
            await ShopAdmin.expects(AdminRuleDetail.conditionORContainer.first()).toBeVisible();

            await ShopAdmin.expects(
                AdminRuleDetail.conditionSelectField.getByText(Translate('administration:rule:conditions.quantity')),
            ).toBeVisible();
            await ShopAdmin.expects(AdminRuleDetail.conditionLineItemGoodsTotalOperator).toHaveText(
                Translate('administration:rule:conditions.greaterThanEquals'),
            );
            await ShopAdmin.expects(AdminRuleDetail.conditionLineItemGoodsTotalValue).toHaveValue(
                testConfig.quantity.toString(),
            );
            await AdminRuleDetail.conditionLineItemGoodsTotalFilter.click();

            await ShopAdmin.expects(AdminRuleDetail.conditionFilterModal).toBeVisible();
            await ShopAdmin.expects(
                AdminRuleDetail.conditionSelectField.getByText(Translate('administration:rule:conditions.inStock')),
            ).toBeVisible();
            await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemInStockOperator).toHaveText(
                Translate('administration:rule:conditions.greaterThanEquals'),
            );
            await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemInStockValue).toHaveValue(
                testConfig.stock.toString(),
            );
            await AdminRuleDetail.conditionFilterModalCloseButtonX.click();

            await ShopAdmin.expects(
                AdminRuleDetail.conditionSelectField.getByText(Translate('administration:rule:conditions.dateRange')),
            ).toBeVisible();
            await ShopAdmin.expects(AdminRuleDetail.conditionDateRangeOperator.first()).toHaveText(
                Translate('administration:rule:conditions.withoutTime'),
            );
            await ShopAdmin.expects(AdminRuleDetail.conditionDateRangeDateFieldFirst).toHaveValue(
                dateFormatter.format(new Date(`${testConfig.fromDate}Z`)),
            );
            await ShopAdmin.expects(AdminRuleDetail.conditionDateRangeDateFieldSecond).toHaveValue(
                dateFormatter.format(new Date(`${testConfig.toDate}Z`)),
            );

            await ShopAdmin.expects(
                AdminRuleDetail.conditionSelectField.getByText(Translate('administration:rule:conditions.customerName')),
            ).toBeVisible();
            await ShopAdmin.expects(AdminRuleDetail.conditionCustomerNameOperator).toHaveText(
                Translate('administration:rule:conditions.equals'),
            );
            await ShopAdmin.expects(AdminRuleDetail.conditionCustomerNameValue).toHaveValue(testConfig.customerName);

            await ShopAdmin.expects(
                AdminRuleDetail.conditionSelectField.getByText(Translate('administration:rule:conditions.taxRate')),
            ).toBeVisible();
            await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemTaxationMatchOperator).toHaveText(
                Translate('administration:rule:conditions.any'),
            );
            await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemTaxationOperator).toHaveText(
                Translate('administration:rule:conditions.isOneOf'),
            );
            await ShopAdmin.expects(AdminRuleDetail.conditionCartLineItemTaxationValue).toHaveText(testConfig.taxName);

            await ShopAdmin.expects(
                AdminRuleDetail.conditionSelectField.getByText(Translate('administration:rule:conditions.timeRange')),
            ).toBeVisible();
            await ShopAdmin.expects(AdminRuleDetail.conditionTimeRangeValueFirst).toHaveValue(
                testConfig.fromDate.split('T')[1].substring(0, 5),
            );
            await ShopAdmin.expects(AdminRuleDetail.conditionTimeRangeValueSecond).toHaveValue(
                testConfig.toDate.split('T')[1].substring(0, 5),
            );

            await ShopAdmin.expects(
                AdminRuleDetail.conditionSelectField.getByText(Translate('administration:rule:conditions.adminOrder')),
            ).toBeVisible();
            await ShopAdmin.expects(AdminRuleDetail.conditionOrderCreatedByAdminValue).toHaveValue(
                Translate('administration:rule:conditions.no'),
            );
        });
    },
);
