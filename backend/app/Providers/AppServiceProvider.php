<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;

use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use App\Repositories\Eloquent\RefreshTokenRepository;

use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Repositories\Eloquent\WorkspaceRepository;

use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Eloquent\RoleRepository;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Eloquent\PermissionRepository;

use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Eloquent\MemberRepository;

use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Eloquent\ChannelRepository;

use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Eloquent\AuditLogRepository;

use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Eloquent\ConversationRepository;

use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Eloquent\MessageRepository;

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

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
