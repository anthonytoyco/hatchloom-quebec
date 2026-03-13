<?php

namespace App\Events;

use App\Models\ClassifiedPost;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a ClassifiedPost is persisted.
 */
class ClassifiedPostCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ClassifiedPost $classifiedPost)
    {
    }
}
