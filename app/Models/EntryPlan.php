<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryPlan extends Model
{
    protected $fillable = [
        'stock_code',
        'entry_price',
        'stop_loss',
        'take_profit',
        'plan_date',
        'status',
    ];

    /** Hitung Risk : Reward ratio sederhana, dipakai untuk KPI di view */
    public function riskRewardRatio(): ?float
    {
        $risk = abs($this->entry_price - $this->stop_loss);
        $reward = abs($this->take_profit - $this->entry_price);

        if ($risk <= 0) {
            return null;
        }

        return round($reward / $risk, 2);
    }
}
