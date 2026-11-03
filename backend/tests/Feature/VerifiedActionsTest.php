<?php

use App\Enums\ActionType;
use App\Enums\AgentName;
use App\Models\AgentAction;
use App\Models\AuditLog;
use App\Repositories\Contracts\ActionRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Services\Actions\ActionHandlerInterface;
use App\Services\Actions\ActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

// A pretend handler: does nothing real, and says what it was told to say
function fakeHandler(bool $verified = true, ?Throwable $fails = null): object
{
    $handler = new class($verified, $fails) implements ActionHandlerInterface
    {
        public int $executed = 0;

        public int $rejected = 0;

        public function __construct(private bool $verified, private ?Throwable $fails) {}

        public function type(): ActionType
        {
            return ActionType::ReminderSend;
        }

        public function execute(AgentAction $action): array
        {
            if ($this->fails) {
                throw $this->fails;
            }
            $this->executed++;

            return ['sent' => true];
        }

        public function rejected(AgentAction $action): void
        {
            $this->rejected++;
        }

        public function verify(AgentAction $action, array $result): bool
        {
            return $this->verified;
        }
    };

    app()->bind(ActionService::class, fn () => new ActionService(
        app(ActionRepositoryInterface::class),
        app(AuditLogRepositoryInterface::class),
        [$handler],
    ));

    return $handler;
}

function proposeReminder(int $workspace): AgentAction
{
    return app(ActionService::class)->propose($workspace, AgentName::Finance, ActionType::ReminderSend, 'Remind Aarav about 1200', ['promise_id' => 1]);
}

function actionOwner(TestCase $test, string $email = 'owner@example.com'): array
{
    $owner = registerOwner($test, $email);

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id']];
}

test('an agent proposes; nothing happens until the owner approves', function () {
    [$headers, $workspace] = actionOwner($this);
    $handler = fakeHandler();

    $action = proposeReminder($workspace);

    expect($action->status->value)->toBe('proposed')->and($handler->executed)->toBe(0);

    $this->getJson('/api/actions', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.summary', 'Remind Aarav about 1200');

    $this->putJson("/api/actions/{$action->id}", ['decision' => 'approve'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'executed');

    expect($handler->executed)->toBe(1);
    $this->getJson('/api/actions', $headers)->assertJsonCount(0, 'data');
    $this->getJson('/api/actions?status=executed', $headers)->assertJsonCount(1, 'data');
});

test('a rejected action is never executed', function () {
    [$headers, $workspace] = actionOwner($this);
    $handler = fakeHandler();
    $action = proposeReminder($workspace);

    $this->putJson("/api/actions/{$action->id}", ['decision' => 'reject'], $headers)->assertOk()->assertJsonPath('data.status', 'rejected');

    expect($handler->executed)->toBe(0);
});

test('an action is decided once', function () {
    [$headers, $workspace] = actionOwner($this);
    $handler = fakeHandler();
    $action = proposeReminder($workspace);

    $this->putJson("/api/actions/{$action->id}", ['decision' => 'approve'], $headers)->assertOk();
    $this->putJson("/api/actions/{$action->id}", ['decision' => 'approve'], $headers)->assertUnprocessable();

    expect($handler->executed)->toBe(1);
});

test('an action that fails, or cannot be checked, is escalated to a person', function () {
    [$headers, $workspace] = actionOwner($this);

    fakeHandler(fails: new RuntimeException('WhatsApp said no'));
    $failing = proposeReminder($workspace);
    $this->putJson("/api/actions/{$failing->id}", ['decision' => 'approve'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'escalated')
        ->assertJsonPath('data.error', 'WhatsApp said no');

    fakeHandler(verified: false);
    $unchecked = proposeReminder($workspace);
    $this->putJson("/api/actions/{$unchecked->id}", ['decision' => 'approve'], $headers)
        ->assertJsonPath('data.status', 'escalated');

    $this->getJson('/api/promises/summary', $headers)->assertJsonPath('data.escalated', 2);
});

test('a type with no handler cannot be approved', function () {
    [$headers, $workspace] = actionOwner($this);
    $action = proposeReminder($workspace); // no handler is registered in this test

    $this->putJson("/api/actions/{$action->id}", ['decision' => 'approve'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'escalated');
});

test('every step is written to the audit log', function () {
    [$headers, $workspace] = actionOwner($this);
    fakeHandler();
    $action = proposeReminder($workspace);
    $this->putJson("/api/actions/{$action->id}", ['decision' => 'approve'], $headers)->assertOk();

    expect(AuditLog::where('subject_type', 'agent_action')->pluck('action')->all())
        ->toBe(['agent_action.proposed', 'agent_action.executed']);
});

test('one business cannot see or decide another business\'s actions', function () {
    [$headers, $workspace] = actionOwner($this);
    [$otherHeaders] = actionOwner($this, 'two@example.com');
    fakeHandler();
    $action = proposeReminder($workspace);

    $this->getJson('/api/actions?status=all', $otherHeaders)->assertJsonCount(0, 'data');
    $this->putJson("/api/actions/{$action->id}", ['decision' => 'approve'], $otherHeaders)->assertNotFound();
});

test('saying no tells the handler, so it can undo what it prepared', function () {
    [$headers, $workspace] = actionOwner($this);
    $handler = fakeHandler();
    $action = proposeReminder($workspace);

    $this->putJson("/api/actions/{$action->id}", ['decision' => 'reject'], $headers)->assertJsonPath('data.status', 'rejected');

    expect($handler->rejected)->toBe(1)->and($handler->executed)->toBe(0);
});

test('a problem can be dismissed, once, and a finished action cannot', function () {
    [$headers, $workspace] = actionOwner($this);
    $handler = fakeHandler(fails: new RuntimeException('WhatsApp said no'));
    $failed = proposeReminder($workspace);
    $this->putJson("/api/actions/{$failed->id}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'escalated');
    $this->getJson('/api/promises/summary', $headers)->assertJsonPath('data.escalated', 1);

    $this->putJson("/api/actions/{$failed->id}", ['decision' => 'reject'], $headers)->assertOk()->assertJsonPath('data.status', 'rejected');

    $this->getJson('/api/promises/summary', $headers)->assertJsonPath('data.escalated', 0);
    expect($handler->rejected)->toBe(1);
    $this->putJson("/api/actions/{$failed->id}", ['decision' => 'reject'], $headers)->assertUnprocessable();
});

test('an action that is already done cannot be dismissed', function () {
    [$headers, $workspace] = actionOwner($this);
    fakeHandler();
    $done = proposeReminder($workspace);
    $this->putJson("/api/actions/{$done->id}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'executed');
    $this->putJson("/api/actions/{$done->id}", ['decision' => 'reject'], $headers)->assertUnprocessable();
});

test('the role form shows plain names for the new groups of permissions', function () {
    [$headers] = actionOwner($this);

    $labels = collect($this->getJson('/api/permissions', $headers)->json('data'))->pluck('label', 'module');

    expect($labels['actions'])->toBe('Approvals (what the assistants ask)')
        ->and($labels['overview'])->toBe('Dashboard numbers')
        ->and($labels['products'])->toBe('Products');
});
