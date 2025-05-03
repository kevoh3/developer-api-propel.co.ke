<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    use HasFactory;

    protected $table = 'api_payment_request';

    public $timestamps = false; // Disable Laravel's timestamps since the table manages them

    protected $primaryKey = 'id';
    protected $fillable = [
        'payment_gateway',
        'merchant_code',
        'merchant_id',
        'source_channel',
        'source_channel_code',
        'destination_channel',
        'destination_channel_code',
        'reserve_account_number',
        'reserve_account_name',
        'destination_currency_code',
        'destination_amount',
        'conversion_rate',
        'account_number',
        'receiving_beneficiary_account_number',
        'merchant_application_id',
        'transaction_ref',
        'transaction_ref_controller',
        'currency',
        'amount',
        'processing_fee',
        'total_amount',
        'paid_amount',
        'propel_transaction_charge',
        'transaction_type',
        'transaction_description',
        'transaction_date',
        'payer_account_email',
        'paid',
        'is_reversed',
        'paid_date',
        'checkout_id',
        'order_id',
        'checkout_reference',
        'receiver_account_type',
        'payment_ref',
        'callback_url',
        'remote_user_ip_address',
        'remote_user_agent',
        'source_account_number',
        'source_account_name',
        'foreign_currency',
        'sender_phone_number',
        'sender_name',
        'sender_dob',
        'sender_country_iso',
        'sender_nationality',
        'sender_id_type',
        'sender_id_number',
        'sender_service_provider_name',
        'remittance_purpose',
        'receiver_phone_number',
        'destination_account_number',
        'destination_account_name',
        'propel_transaction_code',
        'third_party_transaction_code',
        'reversal_transaction_code',
        'card_payment_request_id',
        'result_code',
        'result_description',
        'propel_wallet_enabled',
        'mpesa_enabled',
        'remittance_auto_dispatched',
        'card_enabled',
        'is_remittance_transaction',
        'airtel_enabled',
        'payment_url',
        'redirect_url',
        'success_url',
        'failure_url',
        'date_created',
        'MerchantRequestID',
      'TransactionReference',
      'CheckoutRequestID',
      'CustomerMessage',
       'reqStatus'

    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'paid_date' => 'datetime',
        'date_created' => 'datetime',
        'paid' => 'boolean',
        'is_reversed' => 'boolean',
        'propel_wallet_enabled' => 'boolean',
        'mpesa_enabled' => 'boolean',
        'remittance_auto_dispatched' => 'boolean',
        'card_enabled' => 'boolean',
        'is_remittance_transaction' => 'boolean',
        'airtel_enabled' => 'boolean'
    ];
}
