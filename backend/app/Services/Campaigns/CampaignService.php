<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\RecipientStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Services\Contacts\PhoneNormalizer;
use App\Services\TemplateMessageService;
use App\Services\WhatsAppTemplateService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

/**
 * A campaign = one approved WhatsApp template sent to many people from the contact list.
 * Flow: create (picks the people, fills each person's blanks) -> start (queues one job per person) -> report.
 */
class CampaignService
{
    private const SAMPLE_MESSAGES = 3;

    private const FAILURES_SHOWN = 20;

    public function __construct(
        private CampaignRepositoryInterface $campaigns,
        private CustomerRepositoryInterface $customers,
        private ChannelRepositoryInterface $channels,
        private ConversationRepositoryInterface $conversations,
        private WhatsAppTemplateService $templates,
        private TemplateMessageService $templateMessages,
        private CampaignParameters $parameters,
        private SendingLimit $sendingLimit,
        private PhoneNormalizer $phones,
    ) {}

    public function list(int $workspaceId, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->campaigns
            ->cursorForWorkspace($workspaceId, $perPage)
            ->through(fn (Campaign $campaign) => $this->present($campaign));
    }

    // What the "new campaign" page needs: connected numbers, the Excel columns of the contacts, and how many can get one
    public function options(int $workspaceId): array
    {
        $columns = [];
        $ready = 0;
        $everyone = $this->customers->forCampaign($workspaceId, null);

        foreach ($everyone as $customer) {
            $ready += $this->leaveOutReason($customer) === null ? 1 : 0;

            foreach (array_keys($customer->data ?? []) as $column) {
                $columns[$column] = true;
            }
        }

        return [
            'channels' => $this->channels->getByWorkspaceId($workspaceId)
                ->where('status', 'connected')
                ->map(fn ($channel) => ['id' => $channel->id, 'name' => $channel->name])
                ->values()
                ->all(),
            'columns' => array_keys($columns),
            'people' => ['ready' => $ready, 'total' => $everyone->count()],
        ];
    }

    // The campaign with its report and the first few messages as they will read
    public function show(int $workspaceId, int $campaignId): array
    {
        $campaign = $this->findOrFail($workspaceId, $campaignId);
        $report = $this->campaigns->report($campaign);

        return [
            ...$this->present($campaign),
            'report' => $report,
            'sending_limit' => $campaign->status === CampaignStatus::Draft->value ? $this->limitFor($campaign, $report['total']) : null,
            'failed_people' => $this->campaigns->failedRecipients($campaign, self::FAILURES_SHOWN)
                ->map(fn ($recipient) => [
                    'name' => $recipient->customer->name,
                    'phone' => $recipient->phone,
                    'error' => $recipient->error ?? $recipient->message_error,
                ])->all(),
            'sample_messages' => $this->campaigns->sampleRecipients($campaign, self::SAMPLE_MESSAGES)
                ->map(fn ($recipient) => [
                    'name' => $recipient->customer->name,
                    'phone' => $recipient->phone,
                    'text' => $this->templateMessages->fillBlanks($campaign->template_body, $recipient->parameters),
                ])->all(),
        ];
    }

    /**
     * $data: channel_id, name, template_name, template_language, parameters (a source per blank),
     * contact_ids (optional: only these people; leave it out for everyone saved with permission)
     */
    public function create(int $workspaceId, int $userId, array $data): array
    {
        if (! $this->channels->findConnectedInWorkspace($workspaceId, $data['channel_id'])) {
            throw new BusinessRuleException(__('campaigns.channel_not_found'), 404);
        }

        // Ask Meta once whether the template is approved and how many blanks it has
        $template = $this->templates->approved($workspaceId, $data['channel_id'], $data['template_name'], $data['template_language']);

        if (count($data['parameters']) !== $template['blanks']) {
            throw new BusinessRuleException(__('templates.params_mismatch', ['count' => $template['blanks']]));
        }

        ['recipients' => $recipients, 'skipped' => $skipped] = $this->pickPeople($workspaceId, $data['parameters'], $data['contact_ids'] ?? null);

        if ($recipients === []) {
            throw new BusinessRuleException(__('campaigns.nobody_to_send'), 422);
        }

        $campaign = DB::transaction(function () use ($workspaceId, $userId, $data, $template, $recipients) {
            $campaign = $this->campaigns->create([
                'workspace_id' => $workspaceId,
                'channel_id' => $data['channel_id'],
                'created_by_user_id' => $userId,
                'name' => $data['name'],
                'template_name' => $data['template_name'],
                'template_language' => $data['template_language'],
                'template_body' => $template['body'],
                'parameters' => array_values($data['parameters']),
                'status' => CampaignStatus::Draft->value,
            ]);

            $this->campaigns->addRecipients($campaign, $recipients);

            return $campaign;
        });

        return [...$this->show($workspaceId, $campaign->id), 'skipped' => $skipped];
    }

    /** Sends the first person's message to a number the business chooses (its own phone), before the real send */
    public function sendTest(int $workspaceId, int $userId, int $campaignId, string $rawPhone): array
    {
        $campaign = $this->findOrFail($workspaceId, $campaignId);

        if ($campaign->status !== CampaignStatus::Draft->value) {
            throw new BusinessRuleException(__('campaigns.test_only_draft'), 422);
        }

        $phone = $this->phones->normalize($rawPhone) ?? throw new BusinessRuleException(__('campaigns.test_bad_phone'), 422);
        $conversation = $this->conversations->findOrCreateForCustomer($workspaceId, $campaign->channel_id, $phone, __('campaigns.test_name'));

        $message = $this->sendTemplate($campaign, $conversation, $this->campaigns->sampleRecipients($campaign, 1)->first()->parameters, $userId);

        return ['to' => $phone, 'status' => $message->status, 'error' => $message->error, 'text' => $message->body];
    }

    public function start(int $workspaceId, int $campaignId): array
    {
        $campaign = $this->findOrFail($workspaceId, $campaignId);

        if ($campaign->status !== CampaignStatus::Draft->value) {
            throw new BusinessRuleException(__('campaigns.not_draft'), 422);
        }

        $this->campaigns->setStatus($campaign, CampaignStatus::Sending);

        // One job per person, spread out so the number is not flooded
        $perSecond = max(1, (int) config('campaigns.messages_per_second'));

        foreach ($this->campaigns->queuedRecipientIds($campaign) as $position => $recipientId) {
            SendCampaignMessage::dispatch($recipientId)->delay(now()->addSeconds(intdiv($position, $perSecond)));
        }

        return $this->show($workspaceId, $campaign->id);
    }

    // Messages already sent stay sent; the rest are not sent
    public function cancel(int $workspaceId, int $campaignId): array
    {
        $campaign = $this->findOrFail($workspaceId, $campaignId);

        if ($campaign->status !== CampaignStatus::Sending->value) {
            throw new BusinessRuleException(__('campaigns.not_sending'), 422);
        }

        $this->campaigns->setStatus($campaign, CampaignStatus::Cancelled);

        return $this->show($workspaceId, $campaign->id);
    }

    /** Sends the message to one person. Called by the queue job; safe to call twice. */
    public function deliver(int $recipientId): void
    {
        $recipient = $this->campaigns->findRecipient($recipientId);

        if (! $recipient || $recipient->status !== RecipientStatus::Queued->value) {
            return;
        }

        $campaign = $recipient->campaign;

        if ($campaign->status !== CampaignStatus::Sending->value) {
            return; // stopped meanwhile
        }

        if ($recipient->customer->opted_out_at) {
            $this->campaigns->updateRecipient($recipient, ['status' => RecipientStatus::Skipped->value, 'error' => __('campaigns.opted_out')]);
        } else {
            $this->send($recipient, $campaign);
        }

        $this->finishIfAllSent($campaign);
    }

    /** The queue gave up on this person (an unexpected error): show it as failed instead of waiting for ever */
    public function markFailed(int $recipientId, string $error): void
    {
        $recipient = $this->campaigns->findRecipient($recipientId);

        if ($recipient && $recipient->status === RecipientStatus::Queued->value) {
            $this->campaigns->updateRecipient($recipient, ['status' => RecipientStatus::Failed->value, 'error' => $error]);
            $this->finishIfAllSent($recipient->campaign);
        }
    }

    // The campaign's template with these values; it was checked with Meta when the campaign was made
    private function sendTemplate(Campaign $campaign, Conversation $conversation, array $parameters, ?int $userId): Message
    {
        return $this->templateMessages->send(
            $conversation,
            $userId,
            $campaign->template_name,
            $campaign->template_language,
            $parameters,
            ['body' => $campaign->template_body, 'blanks' => count($parameters)],
        );
    }

    // What WhatsApp allows this number per day, and whether this campaign is bigger than that
    private function limitFor(Campaign $campaign, int $people): ?array
    {
        $channel = $this->channels->findInWorkspace($campaign->workspace_id, $campaign->channel_id);
        $limit = $channel ? $this->sendingLimit->forChannel($channel) : null;

        return $limit ? [...$limit, 'over_limit' => $limit['per_day'] !== null && $people > $limit['per_day']] : null;
    }

    private function finishIfAllSent(Campaign $campaign): void
    {
        if ($campaign->status === CampaignStatus::Sending->value && ! $this->campaigns->hasQueued($campaign)) {
            $this->campaigns->setStatus($campaign, CampaignStatus::Done);
        }
    }

    private function send(CampaignRecipient $recipient, Campaign $campaign): void
    {
        $conversation = $this->conversations->findOrCreateForCustomer(
            $campaign->workspace_id, $campaign->channel_id, $recipient->phone, $recipient->customer->name,
        );

        $message = $this->sendTemplate($campaign, $conversation, $recipient->parameters, $campaign->created_by_user_id);

        // The message is saved as "failed" with the reason when WhatsApp refused it straight away
        $failed = $message->status === 'failed';

        $this->campaigns->updateRecipient($recipient, [
            'status' => $failed ? RecipientStatus::Failed->value : RecipientStatus::Sent->value,
            'error' => $failed ? $message->error : null,
            'message_id' => $message->id,
        ]);
    }

    /**
     * Who gets the campaign and what goes into their blanks. A person is left out when they have no phone, did not
     * give permission, asked us to stop, or have nothing for one of the blanks (a blank message would be refused).
     *
     * @return array{recipients: array<int, array>, skipped: array<string, int|array>}
     */
    private function pickPeople(int $workspaceId, array $sources, ?array $ids): array
    {
        $recipients = [];
        $reasons = [];
        $emptyValues = [];

        foreach ($this->customers->forCampaign($workspaceId, $ids) as $customer) {
            $reason = $this->leaveOutReason($customer);

            if (! $reason) {
                $values = $this->parameters->valuesFor($sources, $customer);
                $emptyAt = array_search('', $values, true);

                if ($emptyAt === false) {
                    $recipients[] = ['customer_id' => $customer->id, 'phone' => $customer->phone, 'parameters' => $values];

                    continue;
                }

                $reason = 'missing_value';
                $emptyValues[] = ['name' => $customer->name, 'problem' => __('campaigns.missing_value', ['column' => $this->parameters->label(array_values($sources)[$emptyAt])])];
            }

            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        }

        return [
            'recipients' => $recipients,
            'skipped' => ['total' => array_sum($reasons), 'reasons' => $reasons, 'missing_values' => array_slice($emptyValues, 0, 20)],
        ];
    }

    private function leaveOutReason(Customer $customer): ?string
    {
        return match (true) {
            ! $customer->phone => 'no_phone',
            (bool) $customer->opted_out_at => 'opted_out',
            ! $customer->whatsapp_opt_in => 'no_permission',
            default => null,
        };
    }

    private function findOrFail(int $workspaceId, int $campaignId): Campaign
    {
        $campaign = $this->campaigns->findInWorkspace($workspaceId, $campaignId);

        if (! $campaign) {
            throw new BusinessRuleException(__('campaigns.not_found'), 404);
        }

        return $campaign;
    }

    private function present(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'channel_id' => $campaign->channel_id,
            'template_name' => $campaign->template_name,
            'template_language' => $campaign->template_language,
            'template_body' => $campaign->template_body,
            'parameters' => $campaign->parameters,
            'status' => $campaign->status,
            'started_at' => $campaign->started_at,
            'finished_at' => $campaign->finished_at,
            'created_at' => $campaign->created_at,
        ];
    }
}
