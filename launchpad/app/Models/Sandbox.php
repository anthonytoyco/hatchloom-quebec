<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sandbox extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'title',
        'description'
    ];

    public function sideHustle()
    {
        return $this->hasOne(SideHustle::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}