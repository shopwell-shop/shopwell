import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    RELEASE_PACKAGES,
    hasPublishedVersion,
    missingPublications,
    normalizedVersion,
} from './check-release-publication.ts';

const metadataFor = (packageName: string, version = 'v6.7.15.0') => ({
    packages: {
        [packageName]: [{ version, version_normalized: normalizedVersion(version) }],
    },
});

test('matches Packagist normalized versions for a v-prefixed release', () => {
    const packageName = 'shopwell/platform';

    assert.equal(hasPublishedVersion(metadataFor(packageName), packageName, 'v6.7.15.0'), true);
});

test('reports no missing packages only when the complete split release exists', () => {
    const metadata = Object.fromEntries(RELEASE_PACKAGES.map((packageName) => [packageName, metadataFor(packageName)]));

    assert.deepEqual(missingPublications(metadata, 'v6.7.15.0'), []);
});

test('reports the exact split package that has not reached Packagist', () => {
    const metadata = Object.fromEntries(
        RELEASE_PACKAGES.filter((packageName) => packageName !== 'shopwell/storefront').map((packageName) => [
            packageName,
            metadataFor(packageName),
        ]),
    );

    assert.deepEqual(missingPublications(metadata, 'v6.7.15.0'), ['shopwell/storefront']);
});
