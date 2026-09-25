/**
 * @sw-package framework
 */

import Entity, { assignSetterMethod } from '@shopwell-ag/meteor-admin-sdk/es/_internals/data/Entity';

assignSetterMethod((draft, property, value) => {
    // @ts-expect-error
    Shopwell.Application.view.setReactive(draft as Vue, property, value);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Entity;
