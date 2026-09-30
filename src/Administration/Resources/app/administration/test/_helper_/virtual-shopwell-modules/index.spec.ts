/**
 * @sw-package framework
 *
 * Proves the `shopwell:*` modules are usable from a spec, and that what they hand out is the very object
 * the global `Shopwell` holds rather than a copy of it.
 */

import { createId, object } from 'shopwell:utils';
import { warn, error } from 'shopwell:utils/debug';
import debugNamespace from 'shopwell:utils/debug';
import { Criteria, EntityCollection } from 'shopwell:data';
import CriteriaClass from 'shopwell:data/Criteria';
import swFormFieldMixin from 'shopwell:mixins/sw-form-field';
import ruleContainerMixin from 'shopwell:mixins/ruleContainer';
import useNotificationStore from 'shopwell:stores/notification';
import useSystemStore from 'shopwell:stores/system';

describe('shopwell:* virtual modules', () => {
    describe('root imports', () => {
        it('export the members of their branch', () => {
            expect(createId).toBe(Shopwell.Utils.createId);
            expect(object).toBe(Shopwell.Utils.object);
            expect(Criteria).toBe(Shopwell.Data.Criteria);
            expect(EntityCollection).toBe(Shopwell.Data.EntityCollection);
        });

        it('export working members', () => {
            expect(createId()).toHaveLength(32);
            expect(new Criteria(1, 25).limit).toBe(25);
        });
    });

    describe('subpaths', () => {
        it('export the members of one namespace, and the namespace as default', () => {
            expect(warn).toBe(Shopwell.Utils.debug.warn);
            expect(error).toBe(Shopwell.Utils.debug.error);
            expect(debugNamespace.warn).toBe(Shopwell.Utils.debug.warn);
        });

        it('export a DAL class as default', () => {
            expect(CriteriaClass).toBe(Shopwell.Data.Criteria);
        });

        it('export a registered mixin as default', () => {
            expect(swFormFieldMixin).toBe(Shopwell.Mixin.getByName('sw-form-field'));
        });

        it('take the registry key verbatim, camelCase included', () => {
            expect(ruleContainerMixin).toBe(Shopwell.Mixin.getByName('ruleContainer'));
        });

        it('export a store as a composable, resolved per call', () => {
            expect(typeof useNotificationStore).toBe('function');
            expect(useNotificationStore()).toBe(Shopwell.Store.get('notification'));
            expect(useSystemStore()).toBe(Shopwell.Store.get('system'));
        });
    });
});
