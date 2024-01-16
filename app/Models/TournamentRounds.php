<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentRounds extends Model
{
    use HasFactory;
    protected $table = 'tournament_rounds';
    protected $fillable = [
        'tournament_id',
        'user_id',
        'type',
        'round_no',
        'status',
        "team_type"
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
    public function matches(){
        return $this->hasMany(TournamentMatches::class,'round_id','id');
    }
}
