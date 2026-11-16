/** @sw-package fundamentals@framework */
import type { OrganizationAssignments } from '../../types/user-organization.types';

type Membership = {
    id: string;
    organizationUnitId: string;
    isPrimary: boolean;
    isLeader?: boolean;
    leaderPosition?: number;
    supervisorMembershipId?: string | null;
};

type PositionAssignment = {
    id: string;
    organizationUnitId: string;
    positionId: string;
    isPrimary: boolean;
};

type AssignmentInput = {
    userId: string;
    draft: OrganizationAssignments;
    memberships: Membership[];
    positions: PositionAssignment[];
    createId: () => string;
};

/** @private */
export function readOrganizationAssignments(
    memberships: Membership[],
    positions: PositionAssignment[],
): OrganizationAssignments {
    const primaryPosition = positions.find((position) => position.isPrimary);

    return {
        departments: memberships.map((membership) => ({
            id: membership.id,
            organizationUnitId: membership.organizationUnitId,
            isPrimary: membership.isPrimary,
            isLeader: membership.isLeader ?? false,
            leaderPosition: membership.leaderPosition ?? 0,
            supervisorMembershipId: membership.supervisorMembershipId ?? null,
            positionIds: positions
                .filter((position) => position.organizationUnitId === membership.organizationUnitId)
                .map((position) => position.positionId),
        })),
        primaryPosition: primaryPosition ? `${primaryPosition.organizationUnitId}:${primaryPosition.positionId}` : null,
    };
}

/** @private */
export function buildAssignmentOperations(input: AssignmentInput) {
    const { userId, draft, memberships, positions } = input;
    const primaryDepartment = draft.departments.find((department) => department.isPrimary) ?? draft.departments[0];
    const membershipsByDepartment = new Map(memberships.map((membership) => [membership.organizationUnitId, membership]));
    const departments = draft.departments.map((department) => ({
        id: membershipsByDepartment.get(department.organizationUnitId)?.id ?? department.id,
        userId,
        organizationUnitId: department.organizationUnitId,
        isPrimary: department.id === primaryDepartment.id,
        isLeader: department.isLeader,
        leaderPosition: department.leaderPosition,
        supervisorMembershipId: department.supervisorMembershipId,
    }));
    const positionsByScope = new Map(
        positions.map((position) => [`${position.organizationUnitId}:${position.positionId}`, position]),
    );
    const assignments = draft.departments.flatMap((department) =>
        [...new Set(department.positionIds)].map((positionId) => {
            const scope = `${department.organizationUnitId}:${positionId}`;

            return {
                id: positionsByScope.get(scope)?.id ?? input.createId(),
                userId,
                organizationUnitId: department.organizationUnitId,
                positionId,
                isPrimary: scope === draft.primaryPosition,
            };
        }),
    );
    if (assignments.length > 0 && !assignments.some((assignment) => assignment.isPrimary)) {
        assignments[0].isPrimary = true;
    }

    const retainedPositionIds = new Set(assignments.map((assignment) => assignment.id));
    const retainedDepartmentIds = new Set(departments.map((department) => department.id));
    const removedPositions = positions
        .filter((assignment) => !retainedPositionIds.has(assignment.id))
        .map(({ id }) => ({ id }));
    const removedDepartments = memberships
        .filter((membership) => !retainedDepartmentIds.has(membership.id))
        .map(({ id }) => ({ id }));

    return {
        departments: { entity: 'user_organization_unit', action: 'upsert', payload: departments },
        positions: { entity: 'user_position', action: 'upsert', payload: assignments },
        removedPositions: { entity: 'user_position', action: 'delete', payload: removedPositions },
        removedDepartments: { entity: 'user_organization_unit', action: 'delete', payload: removedDepartments },
    };
}
