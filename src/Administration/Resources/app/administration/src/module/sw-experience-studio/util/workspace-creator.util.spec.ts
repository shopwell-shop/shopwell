import { getWorkspaceCreatorName } from './workspace-creator.util';

describe('workspace creator names', () => {
    it.each([
        { creator: { name: 'Ada Lovelace', username: 'ada' }, expected: 'Ada Lovelace' },
        { creator: { name: 'Ada Lovelace', username: 'ada' }, expected: 'Ada Lovelace' },
        { creator: { name: 'Ada', username: 'ada' }, expected: 'Ada' },
        { creator: { name: 'Lovelace', username: 'ada' }, expected: 'Lovelace' },
        { creator: { name: '', username: ' ada ' }, expected: 'ada' },
        { creator: { name: '', username: '' }, expected: '' },
        { creator: null, expected: '' },
        { creator: undefined, expected: '' },
    ])('formats $creator as "$expected"', ({ creator, expected }) => {
        expect(getWorkspaceCreatorName({ createdBy: creator })).toBe(expected);
    });
});
