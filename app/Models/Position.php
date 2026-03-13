<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'side_hustle_id',
        'title',
        'description',
        'status'
    ];

    public function sideHustle()
    {
        return $this->belongsTo(SideHustle::class);
    }

    public function classifiedPost(): HasOne
    {
        return $this->hasOne(ClassifiedPost::class);
    }
}