<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedFilter extends Model
{
    protected $fillable = [
        'name', 'op_previous', 'previous', 'op_frequency', 'frequency', 'op_value', 'value',
    ];
}
