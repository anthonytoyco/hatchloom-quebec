<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'student_id',
        'role',
        'joined_at'
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}