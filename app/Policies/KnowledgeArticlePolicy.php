<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KnowledgeArticle;
use App\Models\User;

final class KnowledgeArticlePolicy
{
    /**
     * Determine whether the user can view any articles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'knowledge.view',
            'knowledge.create_articles',
            'knowledge.edit_articles',
            'knowledge.approve_articles',
        ]);
    }

    /**
     * Determine whether the user can view the article.
     */
    public function view(User $user, KnowledgeArticle $article): bool
    {
        // Public articles are viewable by anyone with knowledge.view permission
        if ($article->is_public && $user->hasPermissionTo('knowledge.view')) {
            return true;
        }

        // Department-specific articles
        if ($article->department_id !== null) {
            // Must have department access and knowledge permission
            if (! $user->hasPermissionTo('knowledge.view')) {
                return false;
            }

            return $user->hasDepartmentAccess($article->department_id);
        }

        // Draft articles can only be viewed by author or those with edit permissions
        if ($article->status === 'draft') {
            return $article->author_id === $user->id ||
                $this->edit($user, $article);
        }

        return $user->hasPermissionTo('knowledge.view');
    }

    /**
     * Determine whether the user can create articles.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('knowledge.create_articles');
    }

    /**
     * Determine whether the user can update the article.
     */
    public function update(User $user, KnowledgeArticle $article): bool
    {
        return $this->edit($user, $article);
    }

    /**
     * Determine whether the user can edit the article.
     */
    public function edit(User $user, KnowledgeArticle $article): bool
    {
        // System administrators can edit all
        if (
            $user->hasPermissionTo('knowledge.edit_articles') &&
            $user->hasPermissionTo('knowledge.approve_articles')
        ) {
            return true;
        }

        // Authors can edit their own articles
        if (
            $article->author_id === $user->id &&
            $user->hasPermissionTo('knowledge.edit_articles')
        ) {
            return true;
        }

        // Department managers can edit articles in their department
        if (
            $article->department_id !== null &&
            $user->hasPermissionTo('knowledge.edit_articles') &&
            $user->hasDepartmentAccess($article->department_id)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the article.
     */
    public function delete(User $user, KnowledgeArticle $article): bool
    {
        // Only users with approval permissions can delete
        if (! $user->hasPermissionTo('knowledge.approve_articles')) {
            return false;
        }

        // System administrators can delete all
        if ($user->hasRole('system_administrator')) {
            return true;
        }

        // Department managers can delete articles in their department
        if ($article->department_id !== null) {
            return $user->hasDepartmentAccess($article->department_id);
        }

        return false;
    }

    /**
     * Determine whether the user can publish the article.
     */
    public function publish(User $user, KnowledgeArticle $article): bool
    {
        // Must have approval permissions
        if (! $user->hasPermissionTo('knowledge.approve_articles')) {
            return false;
        }

        // System administrators can publish all
        if ($user->hasRole('system_administrator')) {
            return true;
        }

        // Department managers can publish articles in their department
        if ($article->department_id !== null) {
            return $user->hasDepartmentAccess($article->department_id);
        }

        return false;
    }

    /**
     * Determine whether the user can manage categories.
     */
    public function manageCategories(User $user): bool
    {
        return $user->hasPermissionTo('knowledge.manage_categories');
    }

    /**
     * Determine whether the user can view article metrics.
     */
    public function viewMetrics(User $user, KnowledgeArticle $article): bool
    {
        // Authors can view metrics for their own articles
        if ($article->author_id === $user->id) {
            return true;
        }

        // Users with approval permissions can view metrics
        if ($user->hasPermissionTo('knowledge.approve_articles')) {
            // System administrators can view all metrics
            if ($user->hasRole('system_administrator')) {
                return true;
            }

            // Department managers can view metrics for their department articles
            if ($article->department_id !== null) {
                return $user->hasDepartmentAccess($article->department_id);
            }
        }

        return false;
    }
}
