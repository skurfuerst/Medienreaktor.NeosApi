<?php

declare(strict_types=1);

namespace Medienreaktor\NeosApi\Features\Users;

use Medienreaktor\NeosApi\Features\Users\Dto\Account as AccountDto;
use Medienreaktor\NeosApi\Features\Users\Dto\AssignableRole;
use Medienreaktor\NeosApi\Features\Users\Dto\AssignableRoles;
use Medienreaktor\NeosApi\Features\Users\Dto\NewUser;
use Medienreaktor\NeosApi\Features\Users\Dto\User as UserDto;
use Medienreaktor\NeosApi\Features\Users\Dto\UserEnvelope;
use Medienreaktor\NeosApi\Features\Users\Dto\UserPatch;
use Medienreaktor\NeosApi\Features\Users\Dto\Users;
use Medienreaktor\NeosApi\Features\Users\Response\UserCreated;
use Medienreaktor\NeosApi\Features\Users\Response\UserExists;
use Medienreaktor\NeosApi\Features\Users\Response\UserNotFound;
use Medienreaktor\NeosApi\Features\Users\Response\UserRejected;
use Medienreaktor\NeosApi\Framework\Dto\Success;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Exception\NoSuchRoleException;
use Neos\Flow\Security\Policy\PolicyService;
use Neos\Flow\Security\Policy\Role;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Model\UserId;
use Neos\Neos\Domain\Service\UserService;
use Neos\OpenApi\Attributes\Operation;
use Neos\OpenApi\Attributes\RequestBody;
use Neos\Party\Domain\Model\ElectronicAddress;

/**
 * Backend user administration - the resource behind the Studio's user
 * administration, replacing the classic Users backend module.
 *
 * Authorization is split by operation in Policy.yaml: the read side
 * (Api.Users.Read: index, show) is granted to every editor - the listing
 * doubles as the name roster for collaboration presence. The write side plus
 * the assignable-role catalog (Api.Users.Write: create, update, delete,
 * roles) is granted to administrators only, matching the classic backend
 * module. The Studio mirrors that with the accountPermissions "users" flag
 * from /me. The OAuth scope each operation needs is declared on the operation
 * itself, which is also what publishes it in the document.
 *
 * Lockout guards: the acting administrator cannot delete or deactivate their
 * own user, nor drop their own Administrator role - otherwise a single
 * mis-click locks the last admin out of user administration entirely.
 *
 * Every operation validates before it mutates. Flow flushes in-memory entity
 * changes at the end of the request even when the operation answers a
 * failure, so a late rejection would otherwise leave a half-written user
 * behind - which is why the role, email and lockout checks all run before the
 * first setter.
 */
class UsersApi
{
    #[Flow\Inject]
    protected UserService $userService;

    #[Flow\Inject]
    protected PolicyService $policyService;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    #[Operation(
        path: '/api/users',
        method: 'GET',
        summary: 'All users',
        description: 'Every backend user, unpaginated. Doubles as the name roster for collaboration presence, '
            . 'which is why it is readable by every editor rather than administrators only.',
        operationId: 'listUsers',
        security: ['oauth2' => ['neos.read']],
    )]
    public function index(): Users
    {
        $currentUserId = $this->currentUserId();

        $users = [];
        foreach ($this->userService->getUsers() as $user) {
            $users[] = $this->serializeUser($user, $currentUserId);
        }

        return new Users(users: $users);
    }

    /**
     * Declared before {@see self::show()} on purpose: `/api/users/roles` and
     * `/api/users/{userId}` are both GET, and routes are generated in the
     * order the operations are declared, so the literal one has to come first
     * to win.
     */
    #[Operation(
        path: '/api/users/roles',
        method: 'GET',
        summary: 'Assignable roles',
        description: 'All non-abstract Flow roles, for the admin role picker. Administrators only.',
        operationId: 'listAssignableRoles',
        security: ['oauth2' => ['neos.read']],
    )]
    public function roles(): AssignableRoles
    {
        $roles = array_filter(
            $this->policyService->getRoles(),
            static fn (Role $role) => !$role->isAbstract()
        );
        usort($roles, static fn (Role $a, Role $b) => strcasecmp($a->getLabel(), $b->getLabel()));

        return new AssignableRoles(roles: array_map(
            static fn (Role $role) => new AssignableRole(
                identifier: $role->getIdentifier(),
                label: $role->getLabel(),
                packageKey: $role->getPackageKey(),
            ),
            array_values($roles),
        ));
    }

    #[Operation(
        path: '/api/users/{userId}',
        method: 'GET',
        summary: 'One user',
        operationId: 'getUser',
        security: ['oauth2' => ['neos.read']],
    )]
    public function show(string $userId): UserEnvelope|UserNotFound
    {
        $user = $this->findUser($userId);

        return $user === null
            ? new UserNotFound($userId)
            : new UserEnvelope($this->serializeUser($user, $this->currentUserId()));
    }

    #[Operation(
        path: '/api/users',
        method: 'POST',
        summary: 'Create a user',
        description: 'Administrators only. Creates the user with one account on the default authentication provider.',
        operationId: 'createUser',
        security: ['oauth2' => ['neos.write']],
    )]
    public function create(#[RequestBody] NewUser $newUser): UserCreated|UserRejected|UserExists
    {
        $username = trim($newUser->username);
        if ($username === '') {
            return UserRejected::invalidUsername();
        }
        if ($newUser->password === '') {
            return UserRejected::invalidPassword();
        }
        if (trim($newUser->firstName) === '' || trim($newUser->lastName) === '') {
            return UserRejected::invalidName();
        }
        if ($this->userService->getUser($username) !== null) {
            return new UserExists($username);
        }

        $roleIdentifiers = null;
        if ($newUser->roles !== null) {
            $roleIdentifiers = $this->normalizeRoles($newUser->roles);
            if ($roleIdentifiers instanceof UserRejected) {
                return $roleIdentifiers;
            }
        }
        $email = $newUser->email !== null ? trim($newUser->email) : null;
        if (!self::isValidEmail($email)) {
            return UserRejected::invalidEmail();
        }

        $user = $this->userService->createUser(
            $username,
            $newUser->password,
            trim($newUser->firstName),
            trim($newUser->lastName),
            $roleIdentifiers,
        );

        if ($email !== null && $email !== '') {
            self::setPrimaryEmail($user, $email);
            $this->userService->updateUser($user);
        }

        $this->persistenceManager->persistAll();

        return new UserCreated(new UserEnvelope($this->serializeUser($user, $this->currentUserId())));
    }

    #[Operation(
        path: '/api/users/{userId}',
        method: 'PATCH',
        summary: 'Update a user',
        description: 'Administrators only. Absent members stay unchanged. `email: ""` removes the address, '
            . '`roles` replaces the role set on every account, `password` is an administrative reset. '
            . 'Self-lockout is refused (`cannot_demote_self`, `cannot_deactivate_self`).',
        operationId: 'updateUser',
        security: ['oauth2' => ['neos.write']],
    )]
    public function update(string $userId, #[RequestBody] UserPatch $patch): UserEnvelope|UserNotFound|UserRejected
    {
        $user = $this->findUser($userId);
        if ($user === null) {
            return new UserNotFound($userId);
        }
        $currentUserId = $this->currentUserId();
        $isCurrentUser = $currentUserId !== null && $user->getId()->value === $currentUserId;

        if (($patch->firstName !== null && trim($patch->firstName) === '')
            || ($patch->lastName !== null && trim($patch->lastName) === '')
        ) {
            return UserRejected::invalidName();
        }
        $email = $patch->email !== null ? trim($patch->email) : null;
        if (!self::isValidEmail($email)) {
            return UserRejected::invalidEmail();
        }
        $roleIdentifiers = null;
        if ($patch->roles !== null) {
            $roleIdentifiers = $this->normalizeRoles($patch->roles);
            if ($roleIdentifiers instanceof UserRejected) {
                return $roleIdentifiers;
            }
            if ($isCurrentUser
                && !in_array('Neos.Neos:Administrator', $roleIdentifiers, true)
                && $this->userService->currentUserIsAdministrator()
            ) {
                return UserRejected::cannotDemoteSelf();
            }
        }
        if ($patch->password !== null && $patch->password === '') {
            return UserRejected::invalidPassword();
        }
        if ($patch->active === false && $isCurrentUser) {
            return UserRejected::cannotDeactivateSelf();
        }

        if ($patch->firstName !== null) {
            $user->getName()->setFirstName(trim($patch->firstName));
        }
        if ($patch->lastName !== null) {
            $user->getName()->setLastName(trim($patch->lastName));
        }

        if ($email !== null) {
            if ($email === '') {
                $primaryAddress = $user->getPrimaryElectronicAddress();
                if ($primaryAddress !== null) {
                    $user->removeElectronicAddress($primaryAddress);
                }
            } else {
                self::setPrimaryEmail($user, $email);
            }
        }

        if ($roleIdentifiers !== null) {
            foreach ($user->getAccounts() as $account) {
                /** @var Account $account */
                $this->userService->setRolesForAccount($account, $roleIdentifiers);
            }
        }

        if ($patch->password !== null) {
            $this->userService->setUserPassword($user, $patch->password);
        }

        if ($patch->active !== null && $patch->active !== $user->isActive()) {
            if ($patch->active) {
                $this->userService->activateUser($user);
            } else {
                $this->userService->deactivateUser($user);
            }
        }

        $this->userService->updateUser($user);
        $this->persistenceManager->persistAll();

        return new UserEnvelope($this->serializeUser($user, $currentUserId));
    }

    #[Operation(
        path: '/api/users/{userId}',
        method: 'DELETE',
        summary: 'Delete a user',
        description: 'Administrators only. Deletes the user, their accounts and their personal workspaces '
            . 'including pending changes. Deleting yourself is refused (`cannot_delete_self`).',
        operationId: 'deleteUser',
        security: ['oauth2' => ['neos.write']],
    )]
    public function delete(string $userId): Success|UserNotFound|UserRejected
    {
        $user = $this->findUser($userId);
        if ($user === null) {
            return new UserNotFound($userId);
        }
        $currentUserId = $this->currentUserId();
        if ($currentUserId !== null && $user->getId()->value === $currentUserId) {
            return UserRejected::cannotDeleteSelf();
        }

        $this->userService->deleteUser($user);
        $this->persistenceManager->persistAll();

        return new Success();
    }

    private function currentUserId(): ?string
    {
        return $this->userService->getCurrentUser()?->getId()->value;
    }

    /**
     * A user id that resolves to nothing - malformed or merely unknown - is
     * the same answer either way, so both come back as null. UserId accepts
     * any string, which is why the documented `invalid_user_id` has never
     * been reachable in practice.
     */
    private function findUser(string $userId): ?User
    {
        try {
            $id = UserId::fromString($userId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->userService->findUserById($id);
    }

    /**
     * Normalize (Neos.Neos-relative short names allowed, duplicates removed)
     * and validate assignable role identifiers: each role must exist and must
     * not be abstract.
     *
     * @param array<mixed> $roleIdentifiers
     * @return array<string>|UserRejected
     */
    private function normalizeRoles(array $roleIdentifiers): array|UserRejected
    {
        $normalized = [];
        foreach ($roleIdentifiers as $roleIdentifier) {
            if (!is_string($roleIdentifier) || trim($roleIdentifier) === '') {
                return UserRejected::rolesNotAList();
            }
            $roleIdentifier = trim($roleIdentifier);
            if (!str_contains($roleIdentifier, ':')) {
                $roleIdentifier = 'Neos.Neos:' . $roleIdentifier;
            }
            try {
                $role = $this->policyService->getRole($roleIdentifier);
            } catch (NoSuchRoleException) {
                return UserRejected::unknownRole($roleIdentifier);
            }
            if ($role->isAbstract()) {
                return UserRejected::abstractRole($roleIdentifier);
            }
            $normalized[$role->getIdentifier()] = true;
        }

        return array_keys($normalized);
    }

    /** An empty string is valid: it means "remove the address". */
    private static function isValidEmail(?string $email): bool
    {
        return $email === null || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private static function setPrimaryEmail(User $user, string $email): void
    {
        $primaryAddress = $user->getPrimaryElectronicAddress();
        if ($primaryAddress !== null) {
            $primaryAddress->setIdentifier($email);

            return;
        }
        $electronicAddress = new ElectronicAddress();
        $electronicAddress->setIdentifier($email);
        $electronicAddress->setType('Email');
        $user->addElectronicAddress($electronicAddress);
        $user->setPrimaryElectronicAddress($electronicAddress);
    }

    private function serializeUser(User $user, ?string $currentUserId): UserDto
    {
        $id = $user->getId()->value;
        $name = $user->getName();

        // The explicitly assigned roles of every account, de-duplicated. The
        // implicit Everybody / AuthenticatedUser roles are omitted as noise -
        // a listing wants "Administrator", "Editor", not framework internals.
        $roles = [];
        $accounts = [];
        foreach ($user->getAccounts() as $account) {
            /** @var Account $account */
            $accounts[] = new AccountDto(
                accountIdentifier: $account->getAccountIdentifier(),
                authenticationProvider: $account->getAuthenticationProviderName(),
                active: $account->isActive(),
            );
            foreach ($account->getRoles() as $role) {
                $roles[$role->getIdentifier()] = true;
            }
        }

        return new UserDto(
            id: $id,
            label: $user->getLabel(),
            firstName: $name?->getFirstName(),
            lastName: $name?->getLastName(),
            fullName: (string)$name,
            email: $user->getPrimaryElectronicAddress()?->getIdentifier(),
            active: $user->isActive(),
            roles: array_keys($roles),
            accounts: $accounts,
            isCurrentUser: $currentUserId !== null && $id === $currentUserId,
        );
    }
}
