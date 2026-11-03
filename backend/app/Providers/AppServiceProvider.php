<?php

namespace App\Providers;

use App\Models\User;
use App\Repositories\Contracts\ActionRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\KnowledgeSourceRepositoryInterface;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PromiseRepositoryInterface;
use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\RoleTemplateRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Repositories\Eloquent\ActionRepository;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\CampaignRepository;
use App\Repositories\Eloquent\ChannelRepository;
use App\Repositories\Eloquent\ConversationRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\KnowledgeSourceRepository;
use App\Repositories\Eloquent\MemberRepository;
use App\Repositories\Eloquent\MessageRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\PromiseRepository;
use App\Repositories\Eloquent\RefreshTokenRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\RoleTemplateRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WorkspaceRepository;
use App\Services\Actions\ActionService;
use App\Services\Actions\BookingReminderHandler;
use App\Services\Actions\FeedbackRequestHandler;
use App\Services\Actions\OrderCancelHandler;
use App\Services\Actions\OrderConfirmHandler;
use App\Services\Actions\ReminderSendHandler;
use App\Services\Actions\ReportSendHandler;
use App\Services\Agents\Finance\HttpPromiseDetector;
use App\Services\Agents\Finance\NullPromiseDetector;
use App\Services\Agents\Finance\PromiseDetectorInterface;
use App\Services\Agents\Marketing\HttpMarketer;
use App\Services\Agents\Marketing\MarketerInterface;
use App\Services\Agents\Marketing\NullMarketer;
use App\Services\Agents\Operations\HttpOperations;
use App\Services\Agents\Operations\NullOperations;
use App\Services\Agents\Operations\OperationsInterface;
use App\Services\Agents\Sales\HttpQualifier;
use App\Services\Agents\Sales\NullQualifier;
use App\Services\Agents\Sales\QualifierInterface;
use App\Services\Ai\HttpAiResponder;
use App\Services\Ai\HttpKnowledgeIndexer;
use App\Services\Ai\NullAiResponder;
use App\Services\Ai\NullKnowledgeIndexer;
use App\Services\Contracts\AiResponderInterface;
use App\Services\Contracts\KnowledgeIndexerInterface;
use App\Services\Translation\HttpTranslator;
use App\Services\Translation\NullTranslator;
use App\Services\Translation\TranslatorInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
            KnowledgeIndexerInterface::class,
            $this->app->environment('testing')
                ? NullKnowledgeIndexer::class
                : HttpKnowledgeIndexer::class
        );

        $this->app->bind(
            AiResponderInterface::class,
            $this->app->environment('testing')
                ? NullAiResponder::class
                : HttpAiResponder::class
        );

        $this->app->bind(
            CustomerRepositoryInterface::class,
            CustomerRepository::class
        );

        $this->app->bind(
            CampaignRepositoryInterface::class,
            CampaignRepository::class
        );

        // Tests make no network call; the app asks the ai-service
        $this->app->bind(
            TranslatorInterface::class,
            $this->app->environment('testing') ? NullTranslator::class : HttpTranslator::class
        );

        $this->app->bind(PromiseRepositoryInterface::class, PromiseRepository::class);
        $this->app->bind(ActionRepositoryInterface::class, ActionRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);

        $this->app->bind(
            MarketerInterface::class,
            $this->app->environment('testing') ? NullMarketer::class : HttpMarketer::class
        );

        $this->app->bind(
            OperationsInterface::class,
            $this->app->environment('testing') ? NullOperations::class : HttpOperations::class
        );

        // One handler class per action type; each is added to this tag when it is built
        $this->app->tag([
            OrderConfirmHandler::class,
            OrderCancelHandler::class,
            ReminderSendHandler::class,
            ReportSendHandler::class,
            BookingReminderHandler::class,
            FeedbackRequestHandler::class,
        ], 'action.handlers');
        $this->app->when(ActionService::class)->needs('$handlers')->giveTagged('action.handlers');

        $this->app->bind(
            PromiseDetectorInterface::class,
            $this->app->environment('testing') ? NullPromiseDetector::class : HttpPromiseDetector::class
        );

        $this->app->bind(
            QualifierInterface::class,
            $this->app->environment('testing') ? NullQualifier::class : HttpQualifier::class
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

        RateLimiter::for('signup', fn (Request $request) => Limit::perHour(5)->by('signup:'.$request->ip()));

        // Website chatbot: limit per API key (all visitors of one site share it), else per IP
        RateLimiter::for('public-ask', fn (Request $request) => Limit::perMinute(60)
            ->by('ask:'.($request->header('X-Api-Key') ?: $request->ip())));
    }
}
