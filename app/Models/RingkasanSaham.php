<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RingkasanSaham extends Model
{
    protected $table = 'ringkasan_saham';

    // Sesuaikan jika tabel Anda tidak punya kolom created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'date',
        'stock_code',
        'previous',
        'frequency',
        'value',
    ];

    /**
     * Scope: filter by stock code (LIKE search), identik dengan logika lama.
     */
    public function scopeStockCode($query, $stockCode)
    {
        if (!empty($stockCode)) {
            $query->where('stock_code', 'LIKE', '%' . $stockCode . '%');
        }
        return $query;
    }

    /**
     * Scope: filter by date range, identik dengan logika lama (BETWEEN / >= / <=).
     */
    public function scopeDateRange($query, $startDate, $finishDate)
    {
        if (!empty($startDate) && !empty($finishDate)) {
            $query->whereBetween('date', [$startDate, $finishDate]);
        } elseif (!empty($startDate)) {
            $query->where('date', '>=', $startDate);
        } elseif (!empty($finishDate)) {
            $query->where('date', '<=', $finishDate);
        }
        return $query;
    }

    /**
     * Scope: filter operator dinamis (=, >, <) untuk previous/frequency/value.
     * $operator divalidasi whitelist supaya aman (tidak ada SQL injection lewat operator).
     */
    public function scopeNumericFilter($query, $column, $value, $operator = '=')
    {
        $allowedOperators = ['=', '>', '<', '>=', '<='];
        if (!in_array($operator, $allowedOperators, true)) {
            $operator = '=';
        }

        if ($value !== null && $value !== '') {
            $query->where($column, $operator, $value);
        }
        return $query;
    }
}
