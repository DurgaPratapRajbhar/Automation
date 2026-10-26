<?php

namespace App\Enums;

// Ek message ki haalat: customer ka aaya (received), ya hamara bheja jo aage badhta hai sent → delivered → read
enum MessageStatus: string
{
    case Received = 'received';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    // Meta ke webhooks ulte kram me bhi aa jaate hain; status sirf aage badhe, peeche nahi
    public function canMoveTo(self $next): bool
    {
        if ($next === self::Failed) {
            return $this === self::Sent;
        }

        return $this->step() < $next->step();
    }

    private function step(): int
    {
        return match ($this) {
            self::Received, self::Failed => 0,
            self::Sent => 1,
            self::Delivered => 2,
            self::Read => 3,
        };
    }
}
