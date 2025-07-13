<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\AnalyticsPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\KnowledgeArticlePolicy;
use App\Policies\RolePolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Department::class => DepartmentPolicy::class,
        Ticket::class => TicketPolicy::class,
        KnowledgeArticle::class => KnowledgeArticlePolicy::class,
        'Analytics' => AnalyticsPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Register any custom gates here if needed
    }
}
