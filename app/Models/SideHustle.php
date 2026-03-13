<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SideHustle extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'title',
        'description',
        'status',
        'hasOpenPositions'
    ];

    public function sandbox()
    {
        return $this->belongsTo(Sandbox::class);
    }

    public function bmc()
    {
        return $this->hasOne(BusinessModelCanvas::class);
    }

    public function team()
    {
        return $this->hasOne(Team::class);
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function classifiedPosts(): HasMany
    {
        return $this->hasMany(ClassifiedPost::class);
    }
}