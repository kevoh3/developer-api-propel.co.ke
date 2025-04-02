<?php

namespace App\Models;
use App\Constants\GlobalConst;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use App\Constants\PaymentGatewayConst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionStage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $table = 'transaction_staging';
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
        'receive_amount' => 'double',
        'receiver_type' => 'string',
        'receiver_id' => 'integer',
        'payment_currency' => 'string',
        'sending_purpose_id' => 'integer',
        'source_of_fund_id' => 'integer',
        'remark' => 'string',
        'details' => 'object',
        'status' => 'integer',
        'reject_reason' => 'string',
        'callback_ref' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

}
