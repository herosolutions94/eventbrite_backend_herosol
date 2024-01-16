<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payments_model extends Model
{
    use HasFactory;
     protected $table = 'payments';
    protected $fillable = [
        'user_id',
        'amount',
        'payment_method_id',
        'customer_id',
        'payment_intent_id',
    ];


}
