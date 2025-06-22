<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'roles.view_all',
            'roles.view_department'
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        // System administrators can view all roles
        if ($user->hasPermissionTo('roles.view_all')) {
            return true;
        }

        // Department managers can view roles they can assign
        if ($user->hasPermissionTo('roles.view_department')) {
            $userMaxHierarchy = $user->roles()->max('hierarchy_level') ?? 0;
            return $role->hierarchy_level <= $userMaxHierarchy;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('roles.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role): bool
    {
        // Only system administrators can update roles
        if (!$user->hasPermissionTo('roles.update')) {
            return false;
        }

        // Cannot update roles with equal or higher hierarchy level
        $userMaxHierarchy = $user->roles()->max('hierarchy_level') ?? 0;
        return $role->hierarchy_level < $userMaxHierarchy;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role): bool
    {
        // Only system administrators can delete roles
        if (!$user->hasPermissionTo('roles.delete')) {
            return false;
        }

        // Cannot delete roles with equal or higher hierarchy level
        $userMaxHierarchy = $user->roles()->max('hierarchy_level') ?? 0;
        return $role->hierarchy_level < $userMaxHierarchy;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roles.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roles.force_delete');
    }

    /**
     * Determine whether the user can assign this role to users.
     */
    public function assign(User $user, Role $role): bool
    {
        // Check basic assignment permission
        if (!$user->hasAnyPermission(['roles.assign_all', 'roles.assign_department'])) {
            return false;
        }

        // Cannot assign roles with equal or higher hierarchy level
        $userMaxHierarchy = $user->roles()->max('hierarchy_level') ?? 0;
        return $role->hierarchy_level < $userMaxHierarchy;
    }

    /**
     * Determine whether the user can revoke this role from users.
     */
    public function revoke(User $user, Role $role): bool
    {
        // Check basic revocation permission
        if (!$user->hasAnyPermission(['roles.revoke_all', 'roles.revoke_department'])) {
            return false;
        }

        // Cannot revoke roles with equal or higher hierarchy level
        $userMaxHierarchy = $user->roles()->max('hierarchy_level') ?? 0;
        return $role->hierarchy_level < $userMaxHierarchy;
    }

    /**
     * Determine whether the user can manage permissions for this role.
     */
    public function managePermissions(User $user, Role $role): bool
    {
        // Only system administrators can manage role permissions
        if (!$user->hasPermissionTo('roles.manage_permissions')) {
            return false;
        }

        // Cannot manage permissions for roles with equal or higher hierarchy level
        $userMaxHierarchy = $user->roles()->max('hierarchy_level') ?? 0;
        return $role->hierarchy_level < $userMaxHierarchy;
    }
}
