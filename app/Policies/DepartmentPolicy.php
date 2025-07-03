<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DepartmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'departments.view_own',
            'departments.view_all',
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Department $department): bool
    {
        // Users can view their own department
        if ($user->department_id === $department->id) {
            return true;
        }

        // System administrators and regional managers can view all departments
        if ($user->hasPermissionTo('departments.view_all')) {
            return true;
        }

        // Department managers can view their department and child departments
        if ($user->hasRole('department_manager') && $user->department_id) {
            $userDepartment = $user->department;
            if ($userDepartment && $department->path && $userDepartment->path) {
                return str_starts_with($department->path, $userDepartment->path);
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('departments.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Department $department): bool
    {
        // System administrators can update any department
        if ($user->hasPermissionTo('departments.update_all')) {
            return true;
        }

        // Department managers can update their own department
        if ($user->hasPermissionTo('departments.update_own') && $user->department_id === $department->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Department $department): bool
    {
        // Cannot delete department if it has users
        if ($department->users()->count() > 0) {
            return false;
        }

        // Cannot delete department if it has child departments
        if ($department->children()->count() > 0) {
            return false;
        }

        return $user->hasPermissionTo('departments.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.force_delete');
    }

    /**
     * Determine whether the user can manage users in this department.
     */
    public function manageUsers(User $user, Department $department): bool
    {
        // System administrators can manage users in any department
        if ($user->hasPermissionTo('users.manage_all_departments')) {
            return true;
        }

        // Department managers can manage users in their own department
        if ($user->hasPermissionTo('users.manage_own_department') && $user->department_id === $department->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view analytics for this department.
     */
    public function viewAnalytics(User $user, Department $department): bool
    {
        // Users with global analytics permission
        if ($user->hasPermissionTo('analytics.view_all')) {
            return true;
        }

        // Department managers can view analytics for their department
        if ($user->hasPermissionTo('analytics.view_department') && $user->department_id === $department->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can assign manager to this department.
     */
    public function assignManager(User $user, Department $department): bool
    {
        return $user->hasPermissionTo('departments.assign_manager');
    }
}
