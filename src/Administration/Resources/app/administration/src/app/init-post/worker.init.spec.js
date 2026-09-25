/**
 * @sw-package framework
 */
import WorkerNotificationFactory from 'src/core/factory/worker-notification.factory';
import { createPinia, setActivePinia } from 'pinia';
import initializeWorker from './worker.init';

describe('src/app/init-post/worker.init.ts', () => {
    let loggedIn = false;
    let config = {};
    let loginListeners = [];

    beforeAll(() => {
        Shopwell.Service().register('loginService', () => {
            return {
                isLoggedIn: () => {
                    return loggedIn;
                },

                addOnLoginListener: (listener) => {
                    loginListeners.push(listener);
                },
                getBearerAuthentication: () => {
                    return 'jest';
                },
                refreshToken: () => {
                    return Promise.resolve();
                },

                addOnTokenChangedListener: () => {},
                addOnLogoutListener: () => {},
            };
        });

        Shopwell.Service().register('configService', () => {
            return {
                getConfig: () => {
                    return Promise.resolve(config);
                },
            };
        });
    });

    beforeEach(() => {
        const registry = WorkerNotificationFactory.getRegistry();
        registry.clear();

        WorkerNotificationFactory.resetHelper();

        setActivePinia(createPinia());
    });

    afterEach(() => {
        loggedIn = false;
        config = {};
        loginListeners = [];
    });

    it('should not initialize if not logged in', () => {
        loggedIn = false;

        initializeWorker();

        expect(loginListeners).toHaveLength(1);
    });

    // @deprecated tag:v6.8.0 - The tests will be removed with registerThumbnailMiddleware.
    it.deprecated('v6.8.0.0').each([
        'Shopwell\\Core\\Framework\\DataAbstractionLayer\\Indexing\\MessageQueue\\IndexerMessage',
        'Shopwell\\Elasticsearch\\Framework\\Indexing\\IndexingMessage',
        'Shopwell\\Core\\Content\\Media\\Message\\GenerateThumbnailsMessage',
        'Shopwell\\Core\\Checkout\\Promotion\\DataAbstractionLayer\\PromotionIndexingMessage',
        'Shopwell\\Core\\Content\\ProductStream\\DataAbstractionLayer\\ProductStreamIndexingMessage',
        'Shopwell\\Core\\Content\\Category\\DataAbstractionLayer\\CategoryIndexingMessage',
        'Shopwell\\Core\\Content\\Media\\DataAbstractionLayer\\MediaIndexingMessage',
        'Shopwell\\Core\\System\\SalesChannel\\DataAbstractionLayer\\SalesChannelIndexingMessage',
        'Shopwell\\Core\\Content\\Rule\\DataAbstractionLayer\\RuleIndexingMessage',
        'Shopwell\\Core\\Content\\Product\\DataAbstractionLayer\\ProductIndexingMessage',
        'Shopwell\\Elasticsearch\\Framework\\Indexing\\ElasticsearchIndexingMessage',
        'Shopwell\\Core\\Content\\ImportExport\\Message\\ImportExportMessage',
        'Shopwell\\Core\\Content\\Flow\\Indexing\\FlowIndexingMessage',
        'Shopwell\\Core\\Content\\Newsletter\\DataAbstractionLayer\\NewsletterRecipientIndexingMessage',
    ])('should register thumbnail middleware "%s"', async (name) => {
        loggedIn = true;

        config = {
            adminWorker: {
                enableQueueStatsWorker: false,
            },
        };

        initializeWorker();
        const helper = WorkerNotificationFactory.initialize();

        const createMock = jest.fn(() => {
            return Promise.resolve('jest-id');
        });

        helper.go({
            queue: [{ name, size: 1 }],
            $root: {
                $t: (msg) => msg,
            },
            notification: {
                create: createMock,
            },
        });

        await flushPromises();

        expect(loginListeners).toHaveLength(0);
        expect(createMock).toHaveBeenCalledTimes(1);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with messageQueueNotification.
    it.deprecated('v6.8.0.0')('should update thumbnail middleware notifications', async () => {
        loggedIn = true;

        config = {
            adminWorker: {
                enableQueueStatsWorker: false,
            },
        };

        initializeWorker();
        const helper = WorkerNotificationFactory.initialize();

        const createMock = jest.fn(() => {
            return Promise.resolve('jest-id');
        });

        const updateMock = jest.fn(() => {
            return Promise.resolve();
        });

        // First run should create notification
        helper.go({
            queue: [
                {
                    name: 'Shopwell\\Core\\Framework\\DataAbstractionLayer\\Indexing\\MessageQueue\\IndexerMessage',
                    size: 1,
                },
            ],
            $root: {
                $t: (msg) => msg,
            },
            notification: {
                create: createMock,
                update: updateMock,
            },
        });
        await flushPromises();
        expect(createMock).toHaveBeenCalledTimes(1);

        // Second run should update notification
        helper.go({
            queue: [
                {
                    name: 'Shopwell\\Core\\Framework\\DataAbstractionLayer\\Indexing\\MessageQueue\\IndexerMessage',
                    size: 0,
                },
            ],
            $root: {
                $t: (msg) => msg,
            },
            notification: {
                create: createMock,
                update: updateMock,
            },
        });
        await flushPromises();
        expect(updateMock).toHaveBeenCalledTimes(1);
    });

    it('should update config if logged in', async () => {
        loggedIn = true;

        config = {
            version: 'jest',
            adminWorker: {
                enableQueueStatsWorker: false,
            },
        };

        initializeWorker();
        await flushPromises();

        expect(loginListeners).toHaveLength(0);
        expect(Shopwell.Store.get('context').app.config.version).toBe('jest');
    });
});
