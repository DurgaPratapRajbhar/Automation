# The assistants (agents)

One agent = one job = one file. They write to the customer or to the owner only through the same rules:
an agent **proposes**, the owner says **Yes** (Approvals), the action runs, is checked, and is written to the audit log.
A failure shows under **Problems**; the owner can dismiss it (what was prepared is undone).

| Agent | Answers when | Does |
|---|---|---|
| Support (`SupportAgent`) | everything the others do not take | answers from the Knowledge text, reading the last messages of the chat (`config/bot.php`); when the customer wants the owner it says so, alerts the owner and the bot goes quiet in that chat |
| Orders (`OperationsAgent`) | the message is about buying (a buying word or a product name, in Latin or Hindi script), or answers the Orders assistant | price and stock from the real product list, drafts an order or a booking at OUR prices; asks for the address if the business delivers |
| Cancellation (`CancellationAgent`) | a customer with an open (not delivered) order writes "cancel" (words in `config/operations.php`, Hindi script too; no AI) | tells the customer the shop was asked, alerts the owner, proposes `order.cancel`; the owner's Yes cancels the order, gives the stock back and tells the customer |
| Sales (`SalesAgent`) | the first message of a new person, when the lead questions are on and it is not about buying | asks the owner's questions (up to 3), scores the lead hot / warm / cold, WhatsApp alert for a hot lead |
| Finance (`FinanceAgent`) | watches every customer text with a date or money in it (never answers) | saves dated promises ("kal payment kar dunga"); suggests a reminder for late payments |
| Marketing (`MarketingAgent`) | the owner asks for a campaign message | writes a Meta-ready template from the real products (`{{1}}` = name, STOP line always added); nothing is sent |

Router order: Cancellation, Orders, Sales, Support (`AgentRouter`).
No agent answers in a chat while `bot_paused_until` is in the future: it is set for `bot.human_pause_hours` when the owner replies from the Inbox or the customer asks for the owner.

## Actions (`agent_actions`, one handler class per type)
`order.confirm`, `order.cancel`, `reminder.send` (late payment), `booking.reminder`, `feedback.request`, `report.send` (daily report to the owner's own WhatsApp).
The owner can switch on a standing Yes for booking reminders and feedback (Settings, Orders and alerts).

## Schedules (`routes/console.php`; production runs them in the `scheduler` service)
- 10:00 `agents:propose-reminders` (late payments)
- every hour `agents:propose-booking-followups` (reminder within 24 h before, feedback 2 h after)
- 20:00 `agents:propose-reports`
(business time, `config/promises.timezone`; shops past their trial are skipped)

## Safety rules in the code
- The order total always comes from our prices; a reply that shows a price that is not ours is not sent.
- A booking time is checked by `BookingSlotService` (hours, closed days, how many at once) before it is agreed.
- Free text to a customer works only inside WhatsApp's 24 hours (`CustomerReplyService`); after that it shows under Problems and the owner sends a template from the chat.
- One chat is answered at most 10 messages a minute; a trial shop has a daily limit of AI answers.
- STOP / START are answered with a fixed message, never by the AI; consent is kept with date and source.

## The agents talk to the ai-service
`/qualify`, `/promises`, `/operations`, `/campaign-draft`, `/translate`. The field names are checked on both sides
(`backend/tests/Feature/AiServiceContractTest.php` and `ai-service/tests/test_endpoints.py`).

## Setting up a new server
`php artisan migrate`, `php artisan permissions:sync`, rebuild the backend, queue, scheduler and ai-service images.
