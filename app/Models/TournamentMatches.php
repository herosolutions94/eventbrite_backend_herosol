<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentMatches extends Model
{
    use HasFactory;
    protected $table = 'tournament_matches';
    protected $fillable = [
        'round_id',
        'team1',
        'team2',
        'winner',
        'looser',
        'team1_score',
        'team2_score',
        'status',
    ];

    public function round()
    {
        return $this->hasMany(TournamentRounds::class,'round_id','id');
    }
    public function team_1()
    {
        return $this->belongsTo(Team::class,'team1','id');
    }
    public function team_2()
    {
        return $this->belongsTo(Team::class,'team2','id');
    }
    public function winner_row()
    {
        return $this->belongsTo(Team::class,'winner','id');
    }
    public function looser_row()
    {
        return $this->belongsTo(Team::class,'looser','id');
    }
}
