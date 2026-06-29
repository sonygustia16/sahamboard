<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyManagementHolding extends Model
{
    protected $table = 'money_management_holdings';

    protected $fillable = [
        'stock_code',
        'allocation',
        'pnl',
    ];
}
