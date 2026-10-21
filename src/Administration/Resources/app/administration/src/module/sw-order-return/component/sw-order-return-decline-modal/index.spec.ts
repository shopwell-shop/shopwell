/**
 * @sw-package after-sales
 */

import SwOrderReturnDeclineModal from './index';

type DeclineVm = {
    reason: string;
    reasonLength: number;
    reasonLimit: number;
    isReasonTooLong: boolean;
    isConfirmDisabled: boolean;
    $emit: jest.Mock;
    onClose: () => void;
    onConfirm: () => void;
};

type DeclineModal = {
    computed: Record<string, (this: DeclineVm) => unknown>;
    methods: Record<string, (this: DeclineVm, ...args: unknown[]) => unknown>;
};

const modal = SwOrderReturnDeclineModal as unknown as DeclineModal;

/**
 * The computeds of the component are plain functions on the options object, so `this` is supplied
 * here - every getter delegates to the real computed instead of repeating its formula.
 */
function createVm(reason: string): DeclineVm {
    const vm = { reason, $emit: jest.fn() } as unknown as DeclineVm;

    Object.entries(modal.computed).forEach(([name, computed]) => {
        Object.defineProperty(vm, name, { get: () => computed.call(vm) });
    });

    Object.entries(modal.methods).forEach(([name, method]) => {
        (vm as unknown as Record<string, unknown>)[name] = (...args: unknown[]) => method.apply(vm, args);
    });

    return vm;
}

describe('src/module/sw-order-return/component/sw-order-return-decline-modal/index', () => {
    it('counts the characters of the reason and knows the limit', () => {
        const vm = createVm('damaged');

        expect(vm.reasonLength).toBe(7);
        expect(vm.reasonLimit).toBe(500);
        expect(vm.isReasonTooLong).toBe(false);
    });

    it('refuses a reason above the limit', () => {
        const vm = createVm('a'.repeat(501));

        expect(vm.isReasonTooLong).toBe(true);
        expect(vm.isConfirmDisabled).toBe(true);
    });

    it('keeps the button disabled without a reason, and with whitespace only', () => {
        expect(createVm('').isConfirmDisabled).toBe(true);
        expect(createVm('   ').isConfirmDisabled).toBe(true);
        expect(createVm('wrong size').isConfirmDisabled).toBe(false);
    });

    it('does not hand a refusal without a reason back to the page', () => {
        const vm = createVm('  ');

        vm.onConfirm();

        expect(vm.$emit).not.toHaveBeenCalled();
    });

    it('hands the trimmed reason back to the page', () => {
        const vm = createVm('  the goods were damaged  ');

        vm.onConfirm();

        expect(vm.$emit).toHaveBeenCalledWith('decline', 'the goods were damaged');
    });

    it('closes itself', () => {
        const vm = createVm('');

        vm.onClose();

        expect(vm.$emit).toHaveBeenCalledWith('modal-close');
    });
});
