<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyManagementSetting extends Model
{
    protected $table = 'money_management_settings';

    protected $fillable = [
        'total_capital',
        'max_risk_per_stock',
        'max_positions',
    ];

    /**
     * Setting ini cuma 1 baris (single-row config).
     * Kalau belum ada, dibuatkan otomatis dengan nilai default 0.
     */
    public static function current(): self
    {
        return static::first() ?? static::create([
            'total_capital' => 0,
            'max_risk_per_stock' => 0,
            'max_positions' => 0,
        ]);
    }
}
