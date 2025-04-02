<?php

namespace App\Models;

use App\Constants\GlobalConst;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\Currency;
use App\Models\Sacco\Sacco;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Ownable;

class UserWallet extends Model
{
    use HasFactory;

//    use Ownable;
    public $timestamps = true;
    protected $fillable = ['balance', 'status','user_id','currency_id','created_at','updated_at','earning_percentage_p_a','wallet_account','account_type','wallet_name'];

    protected $casts = [
        'id'             => 'integer',
        'user_id'        => 'integer',
        'currency_id'    => 'integer',
        'balance'        => 'double',
        'profit_balance' => 'double',
        'status'         => 'integer',
    ];

    public function scopeAuth($query) {
        return $query->where('user_id',auth()->user()->id);
    }

    public function scopeActive($query) {
        return $query->where("status",true);
    }

//    public function user() {
//        return $this->belongsTo(User::class);
//    }
    public function user()
    {
        if ($this->user_id) {
            return $this->belongsTo(User::class, 'user_id');
        } elseif ($this->sacco_id) {
            return $this->belongsTo(Sacco::class, 'sacco_id');
        }

        return null;
    }

//    public function currency() {
//        return $this->belongsTo(Currency::class);
//    }

//    public function scopeSender($query) {
//        return $query->whereHas('currency',function($q) {
//            $q->where("sender",GlobalConst::ACTIVE);
//        });
//    }
//    public function getStringStatusAttribute() {
//        $status = $this->status;
//        $data = [
//            'class' => "",
//            'value' => "",
//        ];
//        if($status == PaymentGatewayConst::STATUSSUCCESS) {
//            $data = [
//                'class'     => "badge badge--success",
//                'value'     => __("Active"),
//            ];
//        }else if($status == PaymentGatewayConst::STATUSPENDING) {
//            $data = [
//                'class'     => "badge badge--warning",
//                'value'     => __("Pending"),
//            ];
//        }else if($status == PaymentGatewayConst::STATUSHOLD) {
//            $data = [
//                'class'     => "badge badge--warning",
//                'value'     => __("Hold"),
//            ];
//        }else if($status == PaymentGatewayConst::STATUSREJECTED) {
//            $data = [
//                'class'     => "badge badge--danger",
//                'value'     => __("Rejected"),
//            ];
//        }else if($status == PaymentGatewayConst::STATUSWAITING) {
//            $data = [
//                'class'     => "badge badge--danger",
//                'value'     => __("Waiting"),
//            ];
//        }
//
//        return (object) $data;
//    }
//    public static function generateWalletNumner()
//    {
//        $last_wallet = WalletAccountController::orderBy('wallet_account', 'desc')->first();
//        $wallet_account = $last_wallet ? (int)$last_wallet->wallet_account + 1 : 5001;
//        WalletAccountController::create(['wallet_account'=>$wallet_account]);
//        return $wallet_account;
//    }
}
