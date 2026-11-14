/** @sw-package framework */
import fs from 'fs';
import path from 'path';
import './index';

jest.mock('./acl', () => jest.fn());
jest.mock('./service', () => jest.fn());
jest.mock('./service/extension/approval-workflow.registration', () => jest.fn());

describe('Workflow navigation', () => {
    it('registers participant routes without a plugin', () => {
        const routes = Shopwell.Module.getModuleRegistry().get('sw-workflow')?.routes;
        expect(routes?.get('sw.workflow.tasks')?.meta).toEqual(
            expect.objectContaining({ privilege: 'workflow_participant.viewer' }),
        );
        expect(routes?.get('sw.workflow.instance')?.meta).toEqual(
            expect.objectContaining({ privilege: 'workflow_participant.viewer' }),
        );
    });

    it('keeps publication history within workflow management navigation', () => {
        const routes = Shopwell.Module.getModuleRegistry().get('sw-workflow')?.routes;
        expect(routes?.get('sw.workflow.releases')?.meta).toEqual(
            expect.objectContaining({ parentPath: 'sw.workflow.index' }),
        );
    });

    it('groups workflow tools in an independent top-level domain', () => {
        const navigation = Shopwell.Module.getModuleRegistry().get('sw-workflow')?.manifest.navigation ?? [];
        const root = navigation.find((entry) => entry.id === 'sw-workflow');

        expect(root).toBeDefined();
        expect(root?.parent).toBeUndefined();
        expect(root?.position).toBe(72);
        expect(root?.path).toBeUndefined();
        expect(navigation.filter((entry) => entry.parent === 'sw-workflow')).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    id: 'sw-workflow-tasks',
                    path: 'sw.workflow.tasks',
                    privilege: 'workflow_participant.viewer',
                }),
                expect.objectContaining({
                    id: 'sw-workflow-design',
                    path: 'sw.workflow.index',
                    privilege: 'workflow.viewer',
                }),
            ]),
        );
        expect(navigation.filter((entry) => entry.parent === 'sw-workflow')).toHaveLength(2);
        expect(navigation.some((entry) => entry.parent === 'sw-system')).toBe(false);
    });
});

const moduleDirectory = path.resolve(__dirname);
const iconDirectory = path.join(path.dirname(require.resolve('@shopwell-ag/meteor-icon-kit/package.json')), 'icons');

function getProductionFiles(directory: string): string[] {
    return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const entryPath = path.join(directory, entry.name);

        if (entry.name.includes('.spec')) {
            return [];
        }

        if (entry.isDirectory()) {
            return getProductionFiles(entryPath);
        }

        return /\.(ts|twig)$/.test(entry.name) ? [entryPath] : [];
    });
}

describe('Workflow Studio installed icon contract', () => {
    it('resolves every literal icon to an installed asset', () => {
        const missingIcons: Array<{ file: string; icon: string }> = [];
        let iconCount = 0;

        for (const file of getProductionFiles(moduleDirectory)) {
            const source = fs.readFileSync(file, 'utf8');
            const icons = new Set(
                Array.from(source.matchAll(/['"]((?:regular|solid)-[a-z0-9-]+)['"]/g), ([, icon]) => icon),
            );

            for (const icon of icons) {
                iconCount += 1;
                const separator = icon.indexOf('-');
                const assetPath = path.join(iconDirectory, icon.slice(0, separator), `${icon.slice(separator + 1)}.svg`);

                if (!fs.existsSync(assetPath)) {
                    missingIcons.push({ file: path.relative(moduleDirectory, file), icon });
                }
            }
        }

        expect(iconCount).toBeGreaterThan(0);
        expect(missingIcons).toEqual([]);
    });
});
