<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyManagement extends Model
{
    // Baris ini yang paling penting untuk mengunci nama tabel di database agar tidak error
    protected $table = 'money_managements';

    protected $fillable = ['total_modal', 'maks_risiko_persen'];
}