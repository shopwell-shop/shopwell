import { appendFileSync } from 'node:fs';

export const RELEASE_PACKAGES = [
    'shopwell/platform',
    'shopwell/core',
    'shopwell/administration',
    'shopwell/storefront',
    'shopwell/elasticsearch',
] as const;

type PackageVersion = {
    version?: string;
    version_normalized?: string;
};

type PackageMetadata = {
    packages?: Record<string, PackageVersion[]>;
};

export const normalizedVersion = (version: string): string => version.replace(/^v/, '');

export const hasPublishedVersion = (metadata: PackageMetadata, packageName: string, version: string): boolean => {
    const normalized = normalizedVersion(version);

    return (metadata.packages?.[packageName] ?? []).some(
        (candidate) => candidate.version === version || candidate.version_normalized === normalized,
    );
};

export const missingPublications = (
    metadata: Record<string, PackageMetadata>,
    version: string,
): string[] => RELEASE_PACKAGES.filter((packageName) => !hasPublishedVersion(metadata[packageName] ?? {}, packageName, version));

const fetchMetadata = async (packageName: string): Promise<PackageMetadata> => {
    const url = `https://repo.packagist.org/p2/${packageName}.json?release-check=${Date.now()}`;
    let lastError: unknown;

    for (let attempt = 1; attempt <= 3; attempt += 1) {
        try {
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`${packageName}: Packagist returned HTTP ${response.status}`);
            }

            return (await response.json()) as PackageMetadata;
        } catch (error) {
            lastError = error;

            if (attempt < 3) {
                await new Promise((resolve) => setTimeout(resolve, attempt * 1000));
            }
        }
    }

    throw lastError;
};

const main = async (): Promise<void> => {
    const version = process.argv[2]?.trim() ?? '';
    let alreadyPublished = false;

    if (version !== '') {
        const entries = await Promise.all(
            RELEASE_PACKAGES.map(async (packageName) => [packageName, await fetchMetadata(packageName)] as const),
        );
        const missing = missingPublications(Object.fromEntries(entries), version);
        alreadyPublished = missing.length === 0;

        if (alreadyPublished) {
            console.log(`${version} is already published for all five Shopwell packages; immutable release jobs will be skipped.`);
        } else {
            console.log(`${version} still needs publication for: ${missing.join(', ')}`);
        }
    }

    const output = process.env.GITHUB_OUTPUT;

    if (output === undefined) {
        throw new Error('GITHUB_OUTPUT is required');
    }

    appendFileSync(output, `already-published=${alreadyPublished}\n`, 'utf8');
};

if (import.meta.url === `file://${process.argv[1]}`) {
    await main();
}
