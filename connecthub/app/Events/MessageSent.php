<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a Message is persisted in a thread.
 */
class MessageSent
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Message $message)
    {
    }
}
