<?php

namespace App\Providers;

use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\KnowledgeSourceRepositoryInterface;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\RoleTemplateRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\ChannelRepository;
use App\Repositories\Eloquent\ConversationRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\KnowledgeSourceRepository;
use App\Repositories\Eloquent\MemberRepository;
use App\Repositories\Eloquent\MessageRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\RefreshTokenRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\RoleTemplateRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WorkspaceRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );

        $this->app->bind(
            RefreshTokenRepositoryInterface::class,
            RefreshTokenRepository::class
        );

        $this->app->bind(
            WorkspaceRepositoryInterface::class,
            WorkspaceRepository::class
        );

        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );

        $this->app->bind(
            PermissionRepositoryInterface::class,
            PermissionRepository::class
        );

        $this->app->bind(
            MemberRepositoryInterface::class,
            MemberRepository::class
        );

        $this->app->bind(
            ChannelRepositoryInterface::class,
            ChannelRepository::class
        );

        $this->app->bind(
            AuditLogRepositoryInterface::class,
            AuditLogRepository::class
        );

        $this->app->bind(
            ConversationRepositoryInterface::class,
            ConversationRepository::class
        );

        $this->app->bind(
            MessageRepositoryInterface::class,
            MessageRepository::class
        );

        $this->app->bind(
            KnowledgeSourceRepositoryInterface::class,
            KnowledgeSourceRepository::class
        );

        // Tests me network nahi; chalu app me asli ai-service call
        $this->app->bind(
            \App\Services\Contracts\KnowledgeIndexerInterface::class,
            $this->app->environment('testing')
                ? \App\Services\Ai\NullKnowledgeIndexer::class
                : \App\Services\Ai\HttpKnowledgeIndexer::class
        );

        $this->app->bind(
            \App\Services\Contracts\AiResponderInterface::class,
            $this->app->environment('testing')
                ? \App\Services\Ai\NullAiResponder::class
                : \App\Services\Ai\HttpAiResponder::class
        );

        $this->app->bind(
            CustomerRepositoryInterface::class,
            CustomerRepository::class
        );

        $this->app->bind(
            RoleTemplateRepositoryInterface::class,
            RoleTemplateRepository::class
        );

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super admin passes every permission check; others go through Spatie roles
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);
    }
}
