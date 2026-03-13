<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassifiedPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'position_id',
        'side_hustle_id',
        'author_id',
        'title',
        'content',
        'status',
    ];

    // Valid status transitions per design doc p.20
    private const VALID_TRANSITIONS = [
        'OPEN' => ['FILLED', 'CLOSED'],
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function sideHustle(): BelongsTo
    {
        return $this->belongsTo(SideHustle::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::VALID_TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed);
    }
}
