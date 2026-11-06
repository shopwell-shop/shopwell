/* eslint-env node */
module.exports = {
    extends: [
        'eslint:recommended',
        'plugin:@typescript-eslint/recommended',
        'plugin:@typescript-eslint/stylistic',
        'plugin:playwright/recommended',
    ],
    parser: '@typescript-eslint/parser',
    plugins: ['@typescript-eslint'],
    root: true,
    overrides: [
        {
            // The fixtures build the entities a test needs and assert that the setup
            // succeeded. They run outside a test block by design, so the rule that keeps
            // assertions inside tests cannot apply to them.
            files: ['fixtures/**/*.ts'],
            rules: {
                'playwright/no-standalone-expect': 'off',
            },
        },
    ],
    rules: {
        quotes: [
            'error',
            'single',
            { allowTemplateLiterals: true, avoidEscape: true },
        ],
        'no-console': [
            'error',
            {
                allow: [
                    'warn',
                    'error',
                ],
            },
        ],
        'comma-dangle': [
            'error',
            'always-multiline',
        ],
        'no-unused-vars': 'warn',
        '@typescript-eslint/no-unused-vars': 'warn',
        'playwright/expect-expect': 'off',
        'playwright/no-skipped-test': [
            'error',
            { allowConditional: true },
        ],
    },
};
