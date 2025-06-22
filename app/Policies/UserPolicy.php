<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'users.view_own',
            'users.view_department',
            'users.view_all'
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can always view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Check for global view permission
        if ($user->hasPermissionTo('users.view_all')) {
            return true;
        }

        // Check for department-scoped view permission
        if ($user->hasPermissionTo('users.view_department')) {
            return $user->department_id === $model->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own basic profile
        if ($user->id === $model->id && $user->hasPermissionTo('users.edit_own')) {
            return true;
        }

        // Check for global edit permission
        if ($user->hasPermissionTo('users.edit_all')) {
            return true;
        }

        // Check for department-scoped edit permission
        if ($user->hasPermissionTo('users.edit_department')) {
            return $user->department_id === $model->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Check for global delete permission
        if ($user->hasPermissionTo('users.delete_all')) {
            return true;
        }

        // Check for department-scoped delete permission
        if ($user->hasPermissionTo('users.delete_department')) {
            return $user->department_id === $model->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('users.force_delete');
    }

    /**
     * Determine whether the user can assign roles to the model.
     */
    public function assignRole(User $user, User $model): bool
    {
        // Users cannot assign roles to themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Check for role assignment permissions
        if ($user->hasPermissionTo('roles.assign_all')) {
            return true;
        }

        // Department managers can assign roles within their department
        if ($user->hasPermissionTo('roles.assign_department')) {
            return $user->department_id === $model->department_id;
        }

        return false;
    }

    /**
     * Determine whether the user can view personal data of the model.
     */
    public function viewPersonalData(User $user, User $model): bool
    {
        // Users can view their own personal data
        if ($user->id === $model->id) {
            return true;
        }

        // Check for global personal data access
        if ($user->hasPermissionTo('users.view_personal_data_all')) {
            return true;
        }

        // Compliance auditors can view personal data for auditing
        if ($user->hasRole('compliance_auditor')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can impersonate the model.
     */
    public function impersonate(User $user, User $model): bool
    {
        // Users cannot impersonate themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Only system administrators can impersonate
        if ($user->hasPermissionTo('users.impersonate')) {
            // Cannot impersonate users with equal or higher hierarchy level
            $userMaxHierarchy = $user->roles()->max('hierarchy_level') ?? 0;
            $modelMaxHierarchy = $model->roles()->max('hierarchy_level') ?? 0;

            return $userMaxHierarchy > $modelMaxHierarchy;
        }

        return false;
    }
}
