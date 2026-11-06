import { test as base } from '@playwright/test';
import { getLanguageCode } from '@fixtures/AcceptanceTest';
import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

export const CreateCustomField = base.extend<{ CreateCustomField: Task }, FixtureTypes>({
    CreateCustomField: async ({ AdminCustomFieldDetail, InstanceMeta, Translate }, use) => {
        const task = (customFieldName: string, customFieldTypeText: 'Text field' | 'Number field') => {
            return async function CreateCustomField() {
                const customFieldTypeLabel = {
                    'en-GB': { 'Text field': 'Text field', 'Number field': 'Number field' },
                    'zh-CN': { 'Text field': '文本字段', 'Number field': '数字字段' },
                }[getLanguageCode()][customFieldTypeText];
                await AdminCustomFieldDetail.newCustomFieldButton.click();
                if (satisfies(InstanceMeta.version, '<6.7')) {
                    await AdminCustomFieldDetail.customFieldTypeSelectionList.selectOption(customFieldTypeLabel);
                } else {
                    // Meteor < 5.8 does not link the label to the select input, so its accessible name falls back to the placeholder.
                    const dialog = AdminCustomFieldDetail.newCustomFieldDialog;
                    const customFieldTypeSelectionList = dialog
                        .getByRole('textbox', { name: Translate('administration:customField:detail.type'), exact: true })
                        .or(
                            dialog.getByRole('textbox', {
                                name: Translate('administration:customField:detail.select'),
                                exact: true,
                            }),
                        );

                    await (
                        await AdminCustomFieldDetail.getSelectFieldListitem(
                            customFieldTypeSelectionList,
                            customFieldTypeLabel,
                        )
                    ).click();
                }
                await AdminCustomFieldDetail.customFieldTechnicalNameInput.fill(customFieldName);
                await AdminCustomFieldDetail.customFieldLabelEnglishGBInput.fill(customFieldName);
                await AdminCustomFieldDetail.customFieldAddButton.click();
            };
        };
        await use(task);
    },
});
