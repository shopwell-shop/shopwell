/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.8.0
 *
 * Types for the `shopwell:*` modules, which expose the global `Shopwell` object as ordinary
 * imports. `build/vite-plugins/virtual-shopwell-modules` generates their runtime counterpart from
 * the same `shopwell-modules.json`.
 *
 * Generated. Run `composer admin:generate-shopwell-modules` after adding a utility, DAL class, mixin, or store.
 */

/* eslint-disable sw-deprecation-rules/private-feature-declarations -- Intentional public facade. */

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils' {
    import type branch from 'src/core/service/util.service';

    const members: typeof branch;

    export default members;
    export const createId: (typeof members)['createId'];
    export const throttle: (typeof members)['throttle'];
    export const debounce: (typeof members)['debounce'];
    export const flow: (typeof members)['flow'];
    export const get: (typeof members)['get'];
    export const object: (typeof members)['object'];
    export const debug: (typeof members)['debug'];
    export const format: (typeof members)['format'];
    export const dom: (typeof members)['dom'];
    export const string: (typeof members)['string'];
    export const types: (typeof members)['types'];
    export const fileReader: (typeof members)['fileReader'];
    export const sort: (typeof members)['sort'];
    export const array: (typeof members)['array'];
    export const moveItem: (typeof members)['moveItem'];
    export const VueHelper: (typeof members)['VueHelper'];
    export const EventBus: (typeof members)['EventBus'];
    export const genericRuleCondition: (typeof members)['genericRuleCondition'];
    export const unitConversion: (typeof members)['unitConversion'];
    export const extension: (typeof members)['extension'];
    export const mapInheritanceSlotPropsToMeteorProps: (typeof members)['mapInheritanceSlotPropsToMeteorProps'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/createId' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['createId'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/throttle' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['throttle'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/debounce' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['debounce'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/flow' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['flow'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/get' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['get'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/object' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['object'];

    export default member;
    export const deepCopyObject: (typeof member)['deepCopyObject'];
    export const hasOwnProperty: (typeof member)['hasOwnProperty'];
    export const getObjectDiff: (typeof member)['getObjectDiff'];
    export const getArrayChanges: (typeof member)['getArrayChanges'];
    export const cloneDeep: (typeof member)['cloneDeep'];
    export const merge: (typeof member)['merge'];
    export const mergeWith: (typeof member)['mergeWith'];
    export const deepMergeObject: (typeof member)['deepMergeObject'];
    export const get: (typeof member)['get'];
    export const set: (typeof member)['set'];
    export const pick: (typeof member)['pick'];
    export const unset: (typeof member)['unset'];
    export const has: (typeof member)['has'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/debug' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['debug'];

    export default member;
    export const warn: (typeof member)['warn'];
    export const error: (typeof member)['error'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/format' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['format'];

    export default member;
    export const currency: (typeof member)['currency'];
    export const date: (typeof member)['date'];
    export const dateWithUserTimezone: (typeof member)['dateWithUserTimezone'];
    export const fileSize: (typeof member)['fileSize'];
    export const localeName: (typeof member)['localeName'];
    export const md5: (typeof member)['md5'];
    export const toISODate: (typeof member)['toISODate'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/dom' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['dom'];

    export default member;
    export const getScrollbarHeight: (typeof member)['getScrollbarHeight'];
    export const getScrollbarWidth: (typeof member)['getScrollbarWidth'];
    export const copyStringToClipboard: (typeof member)['copyStringToClipboard'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/string' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['string'];

    export default member;
    export const capitalizeString: (typeof member)['capitalizeString'];
    export const camelCase: (typeof member)['camelCase'];
    export const upperFirst: (typeof member)['upperFirst'];
    export const kebabCase: (typeof member)['kebabCase'];
    export const snakeCase: (typeof member)['snakeCase'];
    export const md5: (typeof member)['md5'];
    export const isEmptyOrSpaces: (typeof member)['isEmptyOrSpaces'];
    export const isUrl: (typeof member)['isUrl'];
    export const isValidIp: (typeof member)['isValidIp'];
    export const isValidCidr: (typeof member)['isValidCidr'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/types' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['types'];

    export default member;
    export const isObject: (typeof member)['isObject'];
    export const isPlainObject: (typeof member)['isPlainObject'];
    export const isEmpty: (typeof member)['isEmpty'];
    export const isRegExp: (typeof member)['isRegExp'];
    export const isArray: (typeof member)['isArray'];
    export const isFunction: (typeof member)['isFunction'];
    export const isDate: (typeof member)['isDate'];
    export const isString: (typeof member)['isString'];
    export const isBoolean: (typeof member)['isBoolean'];
    export const isEqual: (typeof member)['isEqual'];
    export const isNumber: (typeof member)['isNumber'];
    export const isUndefined: (typeof member)['isUndefined'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/fileReader' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['fileReader'];

    export default member;
    export const readAsArrayBuffer: (typeof member)['readAsArrayBuffer'];
    export const readAsDataURL: (typeof member)['readAsDataURL'];
    export const readAsText: (typeof member)['readAsText'];
    export const getNameAndExtensionFromFile: (typeof member)['getNameAndExtensionFromFile'];
    export const getNameAndExtensionFromUrl: (typeof member)['getNameAndExtensionFromUrl'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/sort' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['sort'];

    export default member;
    export const afterSort: (typeof member)['afterSort'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/array' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['array'];

    export default member;
    export const flattenDeep: (typeof member)['flattenDeep'];
    export const remove: (typeof member)['remove'];
    export const slice: (typeof member)['slice'];
    export const uniqBy: (typeof member)['uniqBy'];
    export const chunk: (typeof member)['chunk'];
    export const intersectionBy: (typeof member)['intersectionBy'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/moveItem' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['moveItem'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/VueHelper' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['VueHelper'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/EventBus' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['EventBus'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/genericRuleCondition' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['genericRuleCondition'];

    export default member;
    export const getPlaceholderSnippet: (typeof member)['getPlaceholderSnippet'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/unitConversion' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['unitConversion'];

    export default member;
    export const convert: (typeof member)['convert'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/extension' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['extension'];

    export default member;
    export const getExtensionNameByOrigin: (typeof member)['getExtensionNameByOrigin'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:utils/mapInheritanceSlotPropsToMeteorProps' {
    import type branch from 'src/core/service/util.service';

    const member: (typeof branch)['mapInheritanceSlotPropsToMeteorProps'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data' {
    import type branch from 'src/core/data/index';

    const members: typeof branch;

    export default members;
    export const ChangesetGenerator: (typeof members)['ChangesetGenerator'];
    export const Criteria: (typeof members)['Criteria'];
    export const Entity: (typeof members)['Entity'];
    export const EntityCollection: (typeof members)['EntityCollection'];
    export const EntityDefinition: (typeof members)['EntityDefinition'];
    export const EntityFactory: (typeof members)['EntityFactory'];
    export const EntityHydrator: (typeof members)['EntityHydrator'];
    export const Repository: (typeof members)['Repository'];
    export const ErrorResolver: (typeof members)['ErrorResolver'];
    export const FilterFactory: (typeof members)['FilterFactory'];
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/ChangesetGenerator' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['ChangesetGenerator'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/Criteria' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Criteria'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/Entity' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Entity'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/EntityCollection' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityCollection'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/EntityDefinition' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityDefinition'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/EntityFactory' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityFactory'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/EntityHydrator' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['EntityHydrator'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/Repository' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['Repository'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/ErrorResolver' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['ErrorResolver'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:data/FilterFactory' {
    import type branch from 'src/core/data/index';

    const member: (typeof branch)['FilterFactory'];

    export default member;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/notification' {
    const mixin: MixinContainer['notification'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/validation' {
    const mixin: MixinContainer['validation'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/user-settings' {
    const mixin: MixinContainer['user-settings'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/sw-inline-snippet' {
    const mixin: MixinContainer['sw-inline-snippet'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/translate-with-fallback' {
    const mixin: MixinContainer['translate-with-fallback'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/notification-translation' {
    const mixin: MixinContainer['notification-translation'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/salutation' {
    const mixin: MixinContainer['salutation'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/ruleContainer' {
    const mixin: MixinContainer['ruleContainer'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/remove-api-error' {
    const mixin: MixinContainer['remove-api-error'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/position' {
    const mixin: MixinContainer['position'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/placeholder' {
    const mixin: MixinContainer['placeholder'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/listing' {
    const mixin: MixinContainer['listing'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/generic-condition' {
    const mixin: MixinContainer['generic-condition'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/sw-form-field' {
    const mixin: MixinContainer['sw-form-field'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/discard-detail-page-changes' {
    const mixin: MixinContainer['discard-detail-page-changes'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:mixins/rule-between-operator' {
    const mixin: MixinContainer['rule-between-operator'];

    export default mixin;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/cmsPage' {
    const useStore: () => PiniaRootState['cmsPage'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/topBarButton' {
    const useStore: () => PiniaRootState['topBarButton'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/teaserPopover' {
    const useStore: () => PiniaRootState['teaserPopover'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/adminMenu' {
    const useStore: () => PiniaRootState['adminMenu'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/inAppPurchaseCheckout' {
    const useStore: () => PiniaRootState['inAppPurchaseCheckout'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/extensionComponentSections' {
    const useStore: () => PiniaRootState['extensionComponentSections'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/blockOverride' {
    const useStore: () => PiniaRootState['blockOverride'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/extensionEntryRoutes' {
    const useStore: () => PiniaRootState['extensionEntryRoutes'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/extensionSdkModules' {
    const useStore: () => PiniaRootState['extensionSdkModules'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/extensions' {
    const useStore: () => PiniaRootState['extensions'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/error' {
    const useStore: () => PiniaRootState['error'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/context' {
    const useStore: () => PiniaRootState['context'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/adminHelpCenter' {
    const useStore: () => PiniaRootState['adminHelpCenter'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/actionButtons' {
    const useStore: () => PiniaRootState['actionButtons'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/licenseViolation' {
    const useStore: () => PiniaRootState['licenseViolation'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/extensionMainModules' {
    const useStore: () => PiniaRootState['extensionMainModules'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/marketing' {
    const useStore: () => PiniaRootState['marketing'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/sdkLocation' {
    const useStore: () => PiniaRootState['sdkLocation'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/ruleConditionsConfig' {
    const useStore: () => PiniaRootState['ruleConditionsConfig'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/settingsItems' {
    const useStore: () => PiniaRootState['settingsItems'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/shopwellApps' {
    const useStore: () => PiniaRootState['shopwellApps'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/system' {
    const useStore: () => PiniaRootState['system'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/modals' {
    const useStore: () => PiniaRootState['modals'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/sidebar' {
    const useStore: () => PiniaRootState['sidebar'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/menuItem' {
    const useStore: () => PiniaRootState['menuItem'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/notification' {
    const useStore: () => PiniaRootState['notification'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/tabs' {
    const useStore: () => PiniaRootState['tabs'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/session' {
    const useStore: () => PiniaRootState['session'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swCategoryDetail' {
    const useStore: () => PiniaRootState['swCategoryDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swSeoUrl' {
    const useStore: () => PiniaRootState['swSeoUrl'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/shopwellExtensions' {
    const useStore: () => PiniaRootState['shopwellExtensions'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swOrderDetail' {
    const useStore: () => PiniaRootState['swOrderDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swOrder' {
    const useStore: () => PiniaRootState['swOrder'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swShippingDetail' {
    const useStore: () => PiniaRootState['swShippingDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/paymentOverviewCard' {
    const useStore: () => PiniaRootState['paymentOverviewCard'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swProductDetail' {
    const useStore: () => PiniaRootState['swProductDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swProfile' {
    const useStore: () => PiniaRootState['swProfile'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swPromotionDetail' {
    const useStore: () => PiniaRootState['swPromotionDetail'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swFlow' {
    const useStore: () => PiniaRootState['swFlow'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/swBulkEdit' {
    const useStore: () => PiniaRootState['swBulkEdit'];

    export default useStore;
}

/** @experimental stableVersion:v6.8.0 */
declare module 'shopwell:stores/mediaModal' {
    const useStore: () => PiniaRootState['mediaModal'];

    export default useStore;
}
