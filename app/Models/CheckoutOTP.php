<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CheckoutOTP extends Model
{
    use HasFactory;

    protected $table = 'merchants_checkout_otp';
    public $timestamps = false; // Since the original Django model has `date_created`, we manage timestamps manually

    protected $fillable = [
        'user_wallet_id', 'mobile_number', 'checkout_id', 'retry_count', 'otp', 'date_created'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->checkout_id = Str::uuid();
            $model->otp = random_int(100000, 999999);
        });
    }

    public function userWallet()
    {
        return $this->belongsTo(UserWallet::class, 'user_wallet_id');
    }

    public function __toString()
    {
        return "Confirmation Code '{$this->otp}' sent to {$this->mobile_number}";
    }
}
