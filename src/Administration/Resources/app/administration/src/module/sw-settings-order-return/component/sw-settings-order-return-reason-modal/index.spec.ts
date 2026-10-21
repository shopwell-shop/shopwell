/**
 * @sw-package after-sales
 */

import { flushPromises } from '@vue/test-utils';
import SwSettingsOrderReturnReasonModal from './index';

type ReasonEntity = {
    id?: string;
    label?: string | null;
    position?: number;
    active?: boolean;
};

type RepositoryMock = {
    create: jest.Mock;
    save: jest.Mock;
};

type ReasonModalVm = {
    reason: ReasonEntity | null;
    label: string;
    position: number;
    active: boolean;
    isSaving: boolean;
    isEdit: boolean;
    title: string;
    isSaveDisabled: boolean;
    repositoryFactory: { create: jest.Mock };
    $emit: jest.Mock;
    $t: (key: string) => string;
    createNotificationSuccess: jest.Mock;
    createNotificationError: jest.Mock;
    onClose: () => void;
    onSave: () => void;
};

type ReasonModal = {
    computed: Record<string, (this: ReasonModalVm) => unknown>;
    methods: Record<string, (this: ReasonModalVm, ...args: unknown[]) => unknown>;
};

const modal = SwSettingsOrderReturnReasonModal as unknown as ReasonModal;

function createRepository(save: jest.Mock = jest.fn().mockResolvedValue(undefined)): RepositoryMock {
    return {
        create: jest.fn(() => ({})),
        save,
    };
}

function createVm(reason: ReasonEntity | null = null, repository: RepositoryMock = createRepository()): ReasonModalVm {
    const vm = {
        reason,
        label: reason?.label ?? '',
        position: reason?.position ?? 0,
        active: reason?.active ?? true,
        isSaving: false,
        repositoryFactory: { create: jest.fn(() => repository) },
        $emit: jest.fn(),
        $t: (key: string) => key,
        createNotificationSuccess: jest.fn(),
        createNotificationError: jest.fn(),
    } as unknown as ReasonModalVm;

    Object.entries(modal.computed).forEach(([name, computed]) => {
        Object.defineProperty(vm, name, { get: () => computed.call(vm) });
    });

    Object.entries(modal.methods).forEach(([name, method]) => {
        (vm as unknown as Record<string, unknown>)[name] = (...args: unknown[]) => method.apply(vm, args);
    });

    return vm;
}

describe('src/module/sw-settings-order-return/component/sw-settings-order-return-reason-modal/index', () => {
    it('tells creating and editing apart', () => {
        const created = createVm();
        const edited = createVm({ id: 'reason-1', label: 'damaged', position: 10, active: true });

        expect(created.isEdit).toBe(false);
        expect(edited.isEdit).toBe(true);

        expect(created.title).toBe('sw-settings-order-return.reason.modalTitleCreate');
        expect(edited.title).toBe('sw-settings-order-return.reason.modalTitleEdit');
    });

    it('keeps the button disabled while the label is empty or a save runs', () => {
        const vm = createVm();

        expect(vm.isSaveDisabled).toBe(true);

        vm.label = '   ';
        expect(vm.isSaveDisabled).toBe(true);

        vm.label = 'damaged';
        expect(vm.isSaveDisabled).toBe(false);

        vm.isSaving = true;
        expect(vm.isSaveDisabled).toBe(true);
    });

    it('does not save a reason without a label', async () => {
        const repository = createRepository();
        const vm = createVm(null, repository);

        vm.onSave();
        await flushPromises();

        expect(repository.save).not.toHaveBeenCalled();
        expect(vm.isSaving).toBe(false);
    });

    it('saves a new reason through the repository and reports the success', async () => {
        const repository = createRepository();
        const vm = createVm(null, repository);
        vm.label = '  damaged  ';
        vm.position = 20;
        vm.active = false;

        vm.onSave();
        await flushPromises();

        expect(repository.create).toHaveBeenCalledTimes(1);
        expect(repository.save).toHaveBeenCalledWith({ label: 'damaged', position: 20, active: false });
        expect(vm.createNotificationSuccess).toHaveBeenCalledWith({
            message: 'sw-settings-order-return.reason.notificationSaveSuccess',
        });
        expect(vm.$emit).toHaveBeenCalledWith('saved');
        expect(vm.isSaving).toBe(false);
    });

    it('saves the reason that is edited and not a new one', async () => {
        const repository = createRepository();
        const reason = { id: 'reason-1', label: 'damaged', position: 10, active: true };
        const vm = createVm(reason, repository);

        vm.onSave();
        await flushPromises();

        expect(repository.create).not.toHaveBeenCalled();
        expect(repository.save).toHaveBeenCalledWith(reason);
    });

    it('reports the detail the server refused the save with', async () => {
        const repository = createRepository(
            jest.fn().mockRejectedValue({ response: { data: { errors: [{ detail: 'the position is taken' }] } } }),
        );
        const vm = createVm(null, repository);
        vm.label = 'damaged';

        vm.onSave();
        await flushPromises();

        expect(vm.createNotificationError).toHaveBeenCalledWith({ message: 'the position is taken' });
        expect(vm.$emit).not.toHaveBeenCalledWith('saved');
        expect(vm.isSaving).toBe(false);
    });

    it('falls back to the snippet when the refusal carries no detail', async () => {
        const repository = createRepository(jest.fn().mockRejectedValue({ response: { data: {} } }));
        const vm = createVm(null, repository);
        vm.label = 'damaged';

        vm.onSave();
        await flushPromises();

        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-order-return.reason.notificationSaveError',
        });
    });

    it('does not close itself while a save runs', () => {
        const vm = createVm();

        vm.isSaving = true;
        vm.onClose();
        expect(vm.$emit).not.toHaveBeenCalled();

        vm.isSaving = false;
        vm.onClose();
        expect(vm.$emit).toHaveBeenCalledWith('modal-close');
    });
});
