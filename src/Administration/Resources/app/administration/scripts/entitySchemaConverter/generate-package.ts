import fs from 'fs';
import path from 'path';
import entitySchema from "../../test/_mocks_/entity-schema.json";
import { EntitySchemaConverter } from "./entity-schema-converter";

async function main() {
    // CI_COMMIT_TAG is the variable GitLab provides upstream; GitHub Actions provides
    // GITHUB_REF_NAME. Both end up as the release tag, e.g. v6.7.15.0.
    const gitCommitTag = process.env.CI_COMMIT_TAG ?? process.env.GITHUB_REF_NAME;

    if (!gitCommitTag || typeof gitCommitTag !== 'string') {
        throw new Error('No git commit tag found. Please set the CI_COMMIT_TAG or GITHUB_REF_NAME environment variable.');
    }

    const converter = new EntitySchemaConverter();
    const packageName = '@shopwell-ag/entity-schema-types'
    const folderPackagePath = path.join(__dirname, '../../entity-schema-types');
    const definitionFileName = 'entity-schema-definition.d.ts';
    const packageVersion = gitCommitTag.replace('v6.', '');
    // The package is published to npm on its own, so it has to carry the license
    // and the upstream attribution of the repository it is generated from. Both
    // live in the repository root, seven levels above this script.
    const repositoryRoot = path.join(__dirname, '../../../../../../../');
    const legalFileNames = ['LICENSE', 'NOTICE'];

    // Delete package folder if exists
    if (fs.existsSync(folderPackagePath)) {
        // @ts-ignore
        fs.rmSync(folderPackagePath, { recursive: true });
    }

    // Create new empty package folder
    fs.mkdirSync(folderPackagePath);

    // Create package.json
    fs.writeFileSync(path.join(folderPackagePath, 'package.json'), JSON.stringify({
        name: packageName,
        version: packageVersion,
        description: 'TypeScript definition file for the corresponding entity schema',
        license: 'Apache-2.0',
        types: definitionFileName,
        // npm derives provenance from the publishing workflow and rejects the upload when this
        // does not name that repository.
        repository: {
            type: 'git',
            url: 'git+https://github.com/shopwell-shop/shopwell.git',
        },
        // Scoped packages default to restricted access; this one is public.
        publishConfig: {
            access: 'public',
        },
        files: [definitionFileName, ...legalFileNames],
    }, null, 4));

    // Copy the license and the upstream attribution into the published package
    for (const legalFileName of legalFileNames) {
        fs.copyFileSync(path.join(repositoryRoot, legalFileName), path.join(folderPackagePath, legalFileName));
    }

    // @ts-ignore
    converter.convert(entitySchema, path.join(folderPackagePath, definitionFileName));
}

main();
