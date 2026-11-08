import fs from 'fs';
import path from 'path';

const moduleDirectory = path.resolve(__dirname, '..');
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

describe('Experience Studio installed icon contract', () => {
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
