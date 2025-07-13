<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Models\PermissionAudit;

final class KnowledgeArticlePolicy
{
    /**
     * Determine whether the user can view any articles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission([
            'knowledge.view_articles',
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
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'knowledge_view', $article);

            return true;
        }

        // Public articles are viewable by anyone with knowledge.view_articles permission
        if ($article->is_public && $user->hasPermissionTo('knowledge.view_articles')) {
            return true;
        }

        // Department-specific articles
        if ($article->department_id !== null) {
            // Must have department access and knowledge permission
            if (!$user->hasPermissionTo('knowledge.view_articles')) {
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
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            // Audit emergency access without creating a temporary article instance
            $this->auditEmergencyAccess($user, 'knowledge_create', null);

            return true;
        }

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
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'knowledge_edit', $article);

            return true;
        }

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
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'knowledge_delete', $article);

            return true;
        }

        // Must have delete permission
        if (!$user->hasPermissionTo('knowledge.delete_articles')) {
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
        if (!$user->hasPermissionTo('knowledge.approve_articles')) {
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
        // Check emergency access first
        if ($user->hasEmergencyAccess()) {
            $this->auditEmergencyAccess($user, 'knowledge_view_analytics', $article);

            return true;
        }

        // Users with analytics permission can view metrics
        if (!$user->hasPermissionTo('knowledge.view_analytics')) {
            return false;
        }

        // Authors can view metrics for their own articles
        if ($article->author_id === $user->id) {
            return true;
        }

        // System administrators can view all metrics
        if ($user->hasRole('system_administrator')) {
            return true;
        }

        // Department managers can view metrics for their department articles
        if ($article->department_id !== null) {
            return $user->hasDepartmentAccess($article->department_id);
        }

        return false;
    }

    /**
     * Audit emergency access usage.
     */
    private function auditEmergencyAccess(User $user, string $action, ?KnowledgeArticle $article = null): void
    {
        // Resolve emergency access ID gracefully (null if none active)
        $emergencyAccessId = $user->getActiveEmergencyAccess()?->getKey();

        // Treat articles with an ID of 0 (or null) as temporary placeholders
        $isRealArticle = $article && $article->getKey() !== 0;

        PermissionAudit::create([
            'user_id' => $user->getKey(),
            'action' => 'emergency_access_used',
            'old_values' => null,
            'new_values' => [
                'action' => $action,
                'article_id' => $isRealArticle ? $article->getKey() : null,
                'article_title' => $isRealArticle ? $article->title : null,
                'department_id' => $isRealArticle ? $article->department_id : null,
                'emergency_access_id' => $emergencyAccessId,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_by' => $user->getKey(),
            'reason' => 'Emergency access used for knowledge article operation',
            'created_at' => now(),
        ]);
    }
}
