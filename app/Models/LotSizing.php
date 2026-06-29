<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LotSizing extends Model
{
    protected $fillable = ['code', 'harga_entry', 'harga_stop_loss', 'jumlah_lot'];
}