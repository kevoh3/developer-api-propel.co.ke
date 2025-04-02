<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $table = 'currencies';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'admin_id', 'country', 'name', 'code', 'symbol', 'type', 'flag',
        'rate', 'sender', 'receiver', 'default', 'status'
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'sender' => 'boolean',
        'receiver' => 'boolean',
        'default' => 'boolean',
        'status' => 'boolean',
    ];
}
