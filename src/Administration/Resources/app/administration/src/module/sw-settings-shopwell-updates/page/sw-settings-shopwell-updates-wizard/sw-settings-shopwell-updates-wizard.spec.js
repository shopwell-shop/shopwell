/**
 * @sw-package framework
 */
import createWrapper from './sw-settings-shopwell-updates-wizard.spec/create-wrapper';
import useSession from 'src/app/composables/use-session';
import useSnackbar from 'src/app/composables/use-snackbar';

jest.mock('src/app/composables/use-snackbar', () => ({
    __esModule: true,
    default: jest.fn(),
}));

describe('module/sw-settings-shopwell-updates/page/sw-settings-shopwell-updates-wizard', () => {
    let wrapper;
    const mockSnackbar = {
        addSnackbar: jest.fn(),
        removeSnackbar: jest.fn(),
    };

    beforeEach(async () => {
        Shopwell.Application.view.deleteReactive = () => {};
        useSession().currentLocale.value = null;
        Shopwell.Store.get('context').app.config.version = '6.4.17.2';
        mockSnackbar.addSnackbar.mockClear();
        jest.mocked(useSnackbar).mockReturnValue(mockSnackbar);
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('shows a critical banner when the Shopwell license check fails', async () => {
        const licenseBanner = wrapper.get('.sw-settings-shopwell-updates-wizard__license-banner');

        expect(licenseBanner.attributes('variant')).toBe('critical');

        wrapper.vm.licenseValid = true;
        await flushPromises();

        expect(wrapper.find('.sw-settings-shopwell-updates-wizard__license-banner').exists()).toBe(false);
    });

    it('should disable the button if the license check fails', async () => {
        const button = wrapper.findByText('button', 'sw-settings-shopwell-updates.infos.startUpdate');

        expect(button.attributes('aria-disabled')).toBe('true');

        await button.trigger('click');

        expect(wrapper.vm.updateModalShown).toBe(false);
    });

    it('should show the correct error message, when theme deactivation fails', async () => {
        const stopUpdateProcessSpy = jest.spyOn(wrapper.vm, 'stopUpdateProcess');
        const createNotificationWarningSpy = jest.spyOn(wrapper.vm, 'createNotificationWarning');

        wrapper.vm.deactivateExtensions(0);
        await flushPromises();

        expect(stopUpdateProcessSpy).toHaveBeenCalled();
        expect(createNotificationWarningSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                message: expect.stringContaining('sw-extension.errors.messageDeactivationFailedThemeAssignment'),
            }),
        );
    });

    it('deactivate extensions success', async () => {
        wrapper.vm.updateService.deactivateExtensions = () => {
            return Promise.resolve({
                offset: 0,
                total: 0,
            });
        };

        const redirectSpy = jest.fn();
        wrapper.vm.redirectToPage = redirectSpy;

        await wrapper.vm.deactivateExtensions(0);

        expect(redirectSpy).toHaveBeenCalledWith(`${Shopwell.Context.api.basePath}/shopwell-installer.phar.php`);
    });

    it('deactivate extensions success forwards the admin locale to the recovery tool', async () => {
        useSession().currentLocale.value = 'de-DE';

        wrapper.vm.updateService.deactivateExtensions = () => {
            return Promise.resolve({
                offset: 0,
                total: 0,
            });
        };

        const redirectSpy = jest.fn();
        wrapper.vm.redirectToPage = redirectSpy;

        await wrapper.vm.deactivateExtensions(0);

        expect(redirectSpy).toHaveBeenCalledWith(
            `${Shopwell.Context.api.basePath}/shopwell-installer.phar.php?language=de-DE`,
        );
    });

    it('buildRecoveryUrl appends the admin locale as the language parameter', () => {
        useSession().currentLocale.value = 'en-GB';

        expect(wrapper.vm.buildRecoveryUrl()).toBe(
            `${Shopwell.Context.api.basePath}/shopwell-installer.phar.php?language=en-GB`,
        );
    });

    it('buildRecoveryUrl omits the language parameter when no locale is set', () => {
        useSession().currentLocale.value = null;

        expect(wrapper.vm.buildRecoveryUrl()).toBe(`${Shopwell.Context.api.basePath}/shopwell-installer.phar.php`);
    });

    it('deactivate extensions success loops to disable all', async () => {
        wrapper.vm.updateService.deactivateExtensions = (offset) => {
            if (offset === 0) {
                return Promise.resolve({
                    offset: 1,
                    total: 2,
                });
            }

            return Promise.resolve({
                offset: 1,
                total: 1,
            });
        };

        const redirectSpy = jest.fn();
        wrapper.vm.redirectToPage = redirectSpy;

        const updateCallSpy = jest.spyOn(wrapper.vm.updateService, 'deactivateExtensions');

        await wrapper.vm.deactivateExtensions(0);
        await flushPromises();

        expect(redirectSpy).toHaveBeenCalledWith(`${Shopwell.Context.api.basePath}/shopwell-installer.phar.php`);
        expect(updateCallSpy).toHaveBeenCalledTimes(2);
    });

    it('download recovery should disable extensions', async () => {
        const disableExtensionsSpy = jest.spyOn(wrapper.vm, 'deactivateExtensions');

        await wrapper.vm.downloadRecovery();
        expect(wrapper.vm.progressbarValue).toBe(0);

        expect(disableExtensionsSpy).toHaveBeenCalled();
    });

    it('download recovery should on error notification', async () => {
        wrapper.vm.updateService.downloadRecovery = () => Promise.reject(new Error('error'));

        const createNotificationErrorSpy = jest.spyOn(wrapper.vm, 'createNotificationError');

        await wrapper.vm.downloadRecovery();
        await flushPromises();

        expect(wrapper.vm.progressbarValue).toBe(0);
        expect(createNotificationErrorSpy).toHaveBeenCalled();
    });

    it('start update should download recovery', async () => {
        const downloadRecoverySpy = jest.spyOn(wrapper.vm, 'downloadRecovery');

        await wrapper.vm.startUpdateProcess();
        expect(downloadRecoverySpy).toHaveBeenCalled();

        expect(wrapper.emitted('update-started')).toBeTruthy();
        expect(wrapper.emitted('update-started')).toHaveLength(1);
    });

    it('shows the installed and the latest version in the version card', async () => {
        const versionCard = wrapper.get('.sw-settings-shopwell-updates-version');

        expect(versionCard.get('.sw-settings-shopwell-updates-version__current-version').text()).toBe('6.4.17.2');
        expect(versionCard.get('.sw-settings-shopwell-updates-version__new-version').text()).toBe('6.4.18.0');
        expect(versionCard.get('.sw-settings-shopwell-updates-version__changelog-link').attributes('href')).toBe(
            'https://github.com/shopwell-shop/shopwell/releases/',
        );
    });

    it('click on update button', async () => {
        wrapper.vm.updateService.deactivateExtensions = () => {
            return Promise.resolve({
                offset: 1,
                total: 1,
            });
        };
        wrapper.vm.licenseValid = true;

        expect(wrapper.vm.updatePossible).toBe(true);
        expect(wrapper.vm.updaterIsRunning).toBe(false);
        expect(wrapper.vm.updateModalShown).toBe(false);

        await flushPromises();

        await wrapper.get('.sw-settings-shopwell-updates-wizard__start-update').trigger('click');
        await flushPromises();

        expect(wrapper.vm.updateModalShown).toBe(true);

        expect(wrapper.find('.sw-settings-shopwell-updates-check__start-update').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-shopwell-updates-cli-method__command').text()).toContain(
            'shopwell-cli project upgrade',
        );
        expect(wrapper.get('.sw-settings-shopwell-updates-cli-method__install-link').attributes('href')).toBe(
            'https://developer.shopwell.cn/docs/products/tools/cli/',
        );

        await wrapper.get('.sw-settings-shopwell-updates-check__start-update-backup-checkbox input').setChecked(true);

        const redirectSpy = jest.fn();
        wrapper.vm.redirectToPage = redirectSpy;

        await wrapper.get('.sw-settings-shopwell-updates-check__start-update-button').trigger('click');

        expect(wrapper.emitted('update-started')).toBeTruthy();
        expect(wrapper.emitted('update-started')).toHaveLength(1);

        await flushPromises();

        expect(redirectSpy).toHaveBeenCalledWith(`${Shopwell.Context.api.basePath}/shopwell-installer.phar.php`);
    });

    it('still shows the update but disables the web installer when auto updates are disabled', async () => {
        wrapper.vm.licenseValid = true;
        wrapper.vm.autoUpdateEnabled = false;
        await flushPromises();

        expect(wrapper.find('.sw-settings-shopwell-updates-version').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-shopwell-updates-cli-method__command').text()).toContain(
            'shopwell-cli project upgrade',
        );
        expect(wrapper.get('.sw-settings-shopwell-updates-wizard__start-update').attributes('aria-disabled')).toBe('true');
        expect(wrapper.vm.updateButtonTooltipMessage).toBe('sw-settings-shopwell-updates.infos.autoUpdateDisabled');
    });

    it('disables the web installer on cluster setups', async () => {
        wrapper.vm.licenseValid = true;
        wrapper.vm.clusterSetup = true;
        await flushPromises();

        expect(wrapper.get('.sw-settings-shopwell-updates-wizard__start-update').attributes('aria-disabled')).toBe('true');
        expect(wrapper.vm.updateButtonTooltipMessage).toBe('sw-settings-shopwell-updates.infos.clusterSetupDisabled');
    });

    it('recommends the Shopwell CLI inside the version card', async () => {
        const versionCard = wrapper.get('.sw-settings-shopwell-updates-wizard__version-card');

        expect(versionCard.get('.sw-settings-shopwell-updates-methods-headline').text()).toBe(
            'sw-settings-shopwell-updates.versionCard.methodsHeadline',
        );

        const cliMethod = versionCard.get('.sw-settings-shopwell-updates-cli-method');

        expect(cliMethod.text()).toContain('sw-settings-shopwell-updates.methodModal.cliDescription');
        expect(cliMethod.get('.sw-settings-shopwell-updates-cli-method__command').text()).toContain(
            'shopwell-cli project upgrade',
        );
        expect(cliMethod.get('.sw-settings-shopwell-updates-cli-method__install-link').attributes('href')).toBe(
            'https://developer.shopwell.cn/docs/products/tools/cli/',
        );
    });

    it('shows the update status as a status indicator badge in the version card header', async () => {
        const badge = wrapper.get('.sw-settings-shopwell-updates-version__status-badge');

        expect(badge.text()).toContain('sw-settings-shopwell-updates.versionCard.badgeUpdateAvailable');
        expect(badge.attributes('status-indicator')).toBeDefined();
        expect(wrapper.vm.updateStatusBadgeVariant).toBe('attention');

        wrapper.vm.updateInfo = { version: null, changelog: null };
        await flushPromises();

        expect(wrapper.vm.updateStatusBadgeVariant).toBe('positive');
        expect(wrapper.vm.updateStatusBadgeLabel).toBe('sw-settings-shopwell-updates.versionCard.badgeUpToDate');
    });

    it('shows only the version card with an up-to-date state when no update is available', async () => {
        wrapper.vm.updateInfo = { version: null, changelog: null };
        await flushPromises();

        const versionCard = wrapper.get('.sw-settings-shopwell-updates-wizard__version-card');
        const upToDateState = versionCard.get('.sw-settings-shopwell-updates-up-to-date');

        expect(upToDateState.find('.mt-empty-state__icon').exists()).toBe(true);
        expect(upToDateState.text()).toContain('sw-settings-shopwell-updates.versionCard.upToDateTitle');
        expect(upToDateState.text()).toContain('sw-settings-shopwell-updates.versionCard.upToDateDescription');
        expect(versionCard.find('.sw-settings-shopwell-updates-version').exists()).toBe(false);
        expect(versionCard.find('.sw-settings-shopwell-updates-methods-headline').exists()).toBe(false);
        expect(versionCard.find('.sw-settings-shopwell-updates-cli-method').exists()).toBe(false);
        expect(versionCard.find('.sw-settings-shopwell-updates-method-divider').exists()).toBe(false);
        expect(versionCard.find('.sw-settings-shopwell-updates-web-installer').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-shopwell-updates-extensions').exists()).toBe(false);
    });

    it('shows a checkmark on the copy button after copying and reverts after a delay', async () => {
        jest.useFakeTimers();

        try {
            jest.spyOn(Shopwell.Utils.dom, 'copyStringToClipboard').mockResolvedValue();
            const copyButton = wrapper.get('.sw-settings-shopwell-updates-cli-method__copy-button');

            await copyButton.trigger('click');
            await jest.advanceTimersByTimeAsync(0);

            expect(copyButton.find('[data-testid="mt-icon__regular-checkmark"]').exists()).toBe(true);

            await jest.advanceTimersByTimeAsync(2000);

            expect(copyButton.find('[data-testid="mt-icon__regular-checkmark"]').exists()).toBe(false);
            expect(copyButton.find('[data-testid="mt-icon__regular-copy"]').exists()).toBe(true);
        } finally {
            jest.useRealTimers();
        }
    });

    it('leaves the loading state and notifies when the update check fails', async () => {
        const notificationSpy = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        wrapper.unmount();
        wrapper = await createWrapper({
            checkForUpdates: () => Promise.reject(new Error('update API unreachable')),
        });
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-settings-shopwell-updates.notifications.checkFailed',
            }),
        );
    });

    it('disables the update methods and hides the extensions card when the compatibility check fails', async () => {
        const notificationSpy = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        wrapper.unmount();
        wrapper = await createWrapper({
            checkLicense: () => Promise.resolve({ isValid: true }),
            extensionCompatibility: () => Promise.reject(new Error('store unreachable')),
        });
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-settings-shopwell-updates.notifications.checkFailed',
            }),
        );

        const startUpdateButton = wrapper.get('.sw-settings-shopwell-updates-wizard__start-update');

        expect(startUpdateButton.attributes('aria-disabled')).toBe('true');
        expect(wrapper.vm.updateButtonTooltipMessage).toBe('sw-settings-shopwell-updates.notifications.checkFailed');
        expect(wrapper.find('.sw-settings-shopwell-updates-extensions').exists()).toBe(false);
    });

    it('offers the web installer as a second update method behind a divider', async () => {
        const versionCard = wrapper.get('.sw-settings-shopwell-updates-wizard__version-card');

        expect(versionCard.find('.sw-settings-shopwell-updates-method-divider').exists()).toBe(true);

        const webInstaller = versionCard.get('.sw-settings-shopwell-updates-web-installer');

        expect(webInstaller.text()).toContain('sw-settings-shopwell-updates.versionCard.webInstallerTitle');
        expect(webInstaller.find('.sw-settings-shopwell-updates-wizard__start-update').exists()).toBe(true);
    });

    it('copies the CLI command to the clipboard and shows a snackbar', async () => {
        const copySpy = jest.spyOn(Shopwell.Utils.dom, 'copyStringToClipboard').mockResolvedValue();

        await wrapper.get('.sw-settings-shopwell-updates-cli-method__copy-button').trigger('click');
        await flushPromises();

        expect(copySpy).toHaveBeenCalledWith('shopwell-cli project upgrade');
        expect(mockSnackbar.addSnackbar).toHaveBeenCalledWith({
            message: 'global.sw-field.notification.notificationCopySuccessMessage',
            variant: 'success',
        });
    });

    it('shows extension deactivation options when incompatible extensions are installed', async () => {
        wrapper.vm.updateModalShown = true;
        await flushPromises();

        expect(wrapper.find('sw-radio-field-stub').exists()).toBe(false);

        wrapper.vm.extensions = [{ statusName: 'incompatible' }];
        await flushPromises();

        expect(wrapper.find('sw-radio-field-stub').exists()).toBe(true);
    });

    it('disables continue until a backup is confirmed', async () => {
        wrapper.vm.updateModalShown = true;
        await flushPromises();

        expect(wrapper.get('.sw-settings-shopwell-updates-check__start-update-button').attributes('disabled')).toBeDefined();

        await wrapper.get('.sw-settings-shopwell-updates-check__start-update-backup-checkbox input').setChecked(true);

        expect(
            wrapper.get('.sw-settings-shopwell-updates-check__start-update-button').attributes('disabled'),
        ).toBeUndefined();
    });
});
