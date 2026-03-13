<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessModelCanvas extends Model
{
    use HasFactory;

    protected $fillable = [
        'side_hustle_id',
        'key_partners',
        'key_activities',
        'key_resources',
        'value_propositions',
        'customer_relationships',
        'channels',
        'customer_segments',
        'cost_structure',
        'revenue_streams',
    ];

    public function sideHustle()
    {
        return $this->belongsTo(SideHustle::class);
    }
}