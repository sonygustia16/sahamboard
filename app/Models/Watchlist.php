<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Watchlist extends Model
{
    protected $fillable = [
        'stock_code',
        'date',
        'target_price',
        'note',
        'entry',
        'entry_lot',
    ];
}
