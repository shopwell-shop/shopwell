/** @sw-package fundamentals@framework */
import { buildAssignmentOperations, readOrganizationAssignments } from './user-organization-assignments';

describe('user organization assignments', () => {
    it('reads reporting relationships and scoped positions into the editable draft', () => {
        const draft = readOrganizationAssignments(
            [
                {
                    id: 'membership',
                    organizationUnitId: 'sales',
                    isPrimary: true,
                    isLeader: true,
                    leaderPosition: 10,
                    supervisorMembershipId: 'manager',
                },
            ],
            [{ id: 'position', organizationUnitId: 'sales', positionId: 'accountant', isPrimary: true }],
        );

        expect(draft).toEqual({
            departments: [
                {
                    id: 'membership',
                    organizationUnitId: 'sales',
                    isPrimary: true,
                    isLeader: true,
                    leaderPosition: 10,
                    supervisorMembershipId: 'manager',
                    positionIds: ['accountant'],
                },
            ],
            primaryPosition: 'sales:accountant',
        });
    });

    it('saves department reporting settings without changing retained position identities', () => {
        const memberships = [{ id: 'membership', organizationUnitId: 'sales', isPrimary: true }];
        const positions = [{ id: 'assignment', organizationUnitId: 'sales', positionId: 'manager', isPrimary: true }];
        const draft = readOrganizationAssignments(memberships, positions);
        draft.departments[0].isLeader = true;
        draft.departments[0].leaderPosition = 20;
        draft.departments[0].supervisorMembershipId = 'supervisor';
        const operations = buildAssignmentOperations({
            userId: 'user',
            draft,
            memberships,
            positions,
            createId: () => 'unused',
        });

        expect(operations.departments.payload[0]).toEqual({
            id: 'membership',
            userId: 'user',
            organizationUnitId: 'sales',
            isPrimary: true,
            isLeader: true,
            leaderPosition: 20,
            supervisorMembershipId: 'supervisor',
        });
        expect(operations.positions.payload[0].id).toBe('assignment');
        expect(operations.removedPositions.payload).toEqual([]);
    });

    it('assigns the same position separately in two departments and keeps one primary position', () => {
        const draft = readOrganizationAssignments(
            [
                { id: 'sales-member', organizationUnitId: 'sales', isPrimary: true },
                { id: 'finance-member', organizationUnitId: 'finance', isPrimary: false },
            ],
            [],
        );
        draft.departments.forEach((department) => {
            department.positionIds = ['manager'];
        });
        draft.primaryPosition = 'finance:manager';
        const operations = buildAssignmentOperations({
            userId: 'user',
            draft,
            memberships: [],
            positions: [],
            createId: jest.fn().mockReturnValueOnce('sales-assignment').mockReturnValueOnce('finance-assignment'),
        });

        expect(operations.positions.payload).toEqual([
            { id: 'sales-assignment', userId: 'user', organizationUnitId: 'sales', positionId: 'manager', isPrimary: false },
            {
                id: 'finance-assignment',
                userId: 'user',
                organizationUnitId: 'finance',
                positionId: 'manager',
                isPrimary: true,
            },
        ]);
    });

    it('reuses a persisted membership when the draft has a different identity for the same department', () => {
        const memberships = [{ id: 'persisted-member', organizationUnitId: 'sales', isPrimary: true }];
        const draft = readOrganizationAssignments(
            [{ id: 'draft-member', organizationUnitId: 'sales', isPrimary: true }],
            [],
        );
        draft.departments[0].supervisorMembershipId = 'supervisor';
        const operations = buildAssignmentOperations({
            userId: 'user',
            draft,
            memberships,
            positions: [],
            createId: () => 'unused',
        });

        expect(operations.departments.payload[0].id).toBe('persisted-member');
        expect(operations.departments.payload[0].supervisorMembershipId).toBe('supervisor');
        expect(operations.departments.payload[0].isPrimary).toBe(true);
        expect(operations.removedDepartments.payload).toEqual([]);
    });

    it('deletes removed memberships and their positions in the same request', () => {
        const memberships = [{ id: 'old-member', organizationUnitId: 'old', isPrimary: true }];
        const positions = [{ id: 'old-position', organizationUnitId: 'old', positionId: 'manager', isPrimary: true }];
        const draft = readOrganizationAssignments([{ id: 'new-member', organizationUnitId: 'new', isPrimary: true }], []);
        draft.departments[0].positionIds = ['manager'];
        const operations = buildAssignmentOperations({
            userId: 'user',
            draft,
            memberships,
            positions,
            createId: () => 'new-position',
        });

        expect(operations.removedDepartments.payload).toEqual([{ id: 'old-member' }]);
        expect(operations.removedPositions.payload).toEqual([{ id: 'old-position' }]);
        expect(operations.positions.payload[0].isPrimary).toBe(true);
        expect(operations.positions.payload[0].organizationUnitId).toBe('new');
    });
});
