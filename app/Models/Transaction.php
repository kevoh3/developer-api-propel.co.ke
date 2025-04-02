<?php

namespace App\Models;

use App\Models\Voucher;
use App\Models\Admin\Admin;
use App\Models\RequestMoney;
use App\Models\SourceOfFound;
use App\Constants\GlobalConst;
use App\Models\SendingPurpose;
use App\Models\Merchant\Merchant;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\PaymentGateway;
use App\Constants\PaymentGatewayConst;
use App\Models\Merchant\SandboxWallet;
use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\PaymentGatewayCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $casts = [
        'id' => 'integer',
        'type' => 'string',
        'attribute' => 'string',
        'trx_id' => 'string',
        'user_type' => 'string',
        'user_id' => 'integer',
        'merchant_id' => 'integer',
        'merchant_wallet_id' => 'integer',
        'wallet_id' => 'integer',
        'admin_id' => 'integer',
        'available_balance' => 'double',
        'payment_gateway_currency_id' => 'integer',
        'request_amount' => 'double',
        'request_currency' => 'string',
        'exchange_rate' => 'double',
        'percent_charge' => 'double',
        'fixed_charge' => 'double',
        'total_charge' => 'double',
        'total_payable' => 'double',
        'receive_amount'    => 'double',
        'receiver_type'    => 'string',
        'receiver_id' => 'integer',
        'payment_currency' => 'string',
        'destination_currency' => 'string',
        'sending_purpose_id' => 'integer',
        'source_of_fund_id' => 'integer',
        'remark' => 'string',
        'details' => 'object',
        'status' => 'integer',
        'reject_reason' => 'string',
        'callback_ref' => 'string',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
//    public function merchant()
//    {
//        return $this->belongsTo(Merchant::class,'merchant_id');
//    }
//    public function admin() {
//        return $this->belongsTo(Admin::class);
//    }

    public function tran_creator() {
        if($this->user_id != null) {
            return $this->user();
        }else if($this->merchant_id != null) {
            return $this->merchant();
        }
    }
    public function creator_wallet() {
        if($this->wallet_id != null) {
            return $this->user_wallet();
        }else if($this->merchant_wallet_id != null) {
            return $this->merchant_wallet();
        }else if($this->sandbox_wallet_id != null) {
            return $this->sandbox_wallet();
        }
    }
    public function getRouteKeyName()
    {
        return "trx_id";
    }

    public function user_wallet()
    {
        return $this->belongsTo(UserWallet::class, 'wallet_id');
    }
    public function sandbox_wallet()
    {
        return $this->belongsTo(SandboxWallet::class, 'sandbox_wallet_id');
    }
    public function request_money() {
        return $this->belongsTo(RequestMoney::class);
    }
    public function voucher() {
        return $this->belongsTo(Voucher::class);
    }
    public function getCreatorAttribute() {
        if($this->user_type == GlobalConst::USER) {
            return $this->user;
        }else if($this->user_type == GlobalConst::ADMIN) {
            return $this->admin;
        }
    }

    public function receiver_info() {
        return $this->belongsTo(User::class,'receiver_id');
    }

    public function getReceiverAttribute() {
        if($this->receiver_type == GlobalConst::USER) {
            return $this->receiver_info;
        }
    }

    public function getCreatorWalletAttribute() {
        if($this->user_type == GlobalConst::USER) {
            return $this->user_wallet;
        }else if($this->user_type == GlobalConst::ADMIN) { //  if user type ADMIN wallet_id is user wallet id. Because admin has no wallet.
            return $this->user_wallet;
        }
    }

    public function getStringStatusAttribute() {
        $status = $this->status;
        $data = [
            'class' => "",
            'value' => "",
        ];
        if($status == PaymentGatewayConst::STATUSSUCCESS) {
            $data = [
                'class'     => "badge badge--success",
                'value'     => __("Success"),
            ];
        }else if($status == PaymentGatewayConst::STATUSPENDING) {
            $data = [
                'class'     => "badge badge--warning",
                'value'     => __("Pending"),
            ];
        }else if($status == PaymentGatewayConst::STATUSHOLD) {
            $data = [
                'class'     => "badge badge--warning",
                'value'     => __("Hold"),
            ];
        }else if($status == PaymentGatewayConst::STATUSREJECTED) {
            $data = [
                'class'     => "badge badge--danger",
                'value'     => __("Rejected"),
            ];
        }else if($status == PaymentGatewayConst::STATUSWAITING) {
            $data = [
                'class'     => "badge badge--danger",
                'value'     => __("Waiting"),
            ];
        }

        return (object) $data;
    }
    public function scopeAuthMerchant($query) {
        $query->where("merchant_id",auth()->user()->id);
    }
    public function scopeWithdraw($query) {
        return $query->where("type",PaymentGatewayConst::TYPEWITHDRAW);
    }
    public function scopeMoneyOut($query) {
        return $query->where('type',PaymentGatewayConst::TYPEWITHDRAW);
    }

    public function gateway_currency() {
        return $this->belongsTo(PaymentGatewayCurrency::class,'payment_gateway_currency_id');
    }

    public function scopePending($query) {
        return $query->where('status',PaymentGatewayConst::STATUSPENDING);
    }

    public function scopeComplete($query) {
        return $query->where('status',PaymentGatewayConst::STATUSSUCCESS);
    }

    public function scopeReject($query) {
        return $query->where('status',PaymentGatewayConst::STATUSREJECTED);
    }

    public function scopeAddMoney($query) {
        return $query->where('type',PaymentGatewayConst::TYPEADDMONEY);
    }

    public function scopeChartData($query) {
        return $query->select([
            DB::raw("DATE(created_at) as date"),
            DB::raw('COUNT(*) as total')
        ])
        ->groupBy('date')
        ->pluck('total');
    }
    public function scopeTransferMoney($query) {
        return $query->where("type",PaymentGatewayConst::TYPETRANSFERMONEY);
    }

    public function scopeMakePayment($query) {
        return $query->where("type",PaymentGatewayConst::TYPEMAKEPAYMENT);
    }
    public function scopeThisMonth($query) {
        return $query->whereBetween('created_at',[now()->startOfMonth(),now()->endOfMonth()]);
    }

    public function scopeThisYear($query) {
        return $query->whereBetween('created_at',[now()->startOfYear(),now()->endOfYear()]);
    }

    public function scopeYearChartData($query) {
        return $query->select([
            DB::raw('sum(total_charge) as total, YEAR(created_at) as year, MONTH(created_at) as month'),
        ])->groupBy('year','month')->pluck('total','month');
    }

    public function scopeAuth($query) {
        return $query->where('user_type',GlobalConst::USER)->where('user_id',auth()->user()->id);
    }

    public function scopeMoneyTransfer($query) {
        return $query->where('type',PaymentGatewayConst::TYPETRANSFERMONEY);
    }

    public function scopeSearch($query,$data) {
        return $query->where("trx_id","like","%".$data."%");
    }
    public function scopeToday($query) {
        return $query->whereDate('created_at',now()->today());
    }

    public function scopeMonthly($query) {
        return $query->whereMonth('created_at',now()->month());
    }
    public function isAuthUser() {
        if($this->user_id === auth()->user()->id) return true;
        return false;
    }
}
