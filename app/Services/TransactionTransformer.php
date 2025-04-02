<?php


namespace App\Services;

class TransactionTransformer
{
    public static function transform($transaction)
    {


//        'type' => 'string',
//        'attribute' => 'string',
//        'trx_id' => 'string',
//        'user_type' => 'string',
//        'user_id' => 'integer',
//        'merchant_id' => 'integer',
//        'merchant_wallet_id' => 'integer',
//        'wallet_id' => 'integer',
//        'admin_id' => 'integer',
//        'available_balance' => 'double',
//        'payment_gateway_currency_id' => 'integer',
//        'request_amount' => 'double',
//        'request_currency' => 'string',
//        'exchange_rate' => 'double',
//        'percent_charge' => 'double',
//        'fixed_charge' => 'double',
//        'total_charge' => 'double',
//        'total_payable' => 'double',
//        'receive_amount'    => 'double',
//        'receiver_type'    => 'string',
//        'receiver_id' => 'integer',
//        'payment_currency' => 'string',
//        'sending_purpose_id' => 'integer',
//        'source_of_fund_id' => 'integer',
//        'remark' => 'string',
//        'details' => 'object',
//        'status' => 'integer',
//        'reject_reason' => 'string',
//        'callback_ref' => 'string',
//        'created_at'           => 'datetime',
//        'updated_at'           => 'datetime',

        return [
            // Core Transaction Details (Most Important)
            'transaction_id' => $transaction->id,
            'transaction_code' => $transaction->transaction_code ?? null,
            'transaction_type' => $transaction->type ?? null,
            'transaction_amount' => number_format($transaction->request_amount, 2, '.', ','), // Standard Money Format
            'transaction_charges' => number_format($transaction->total_charge ?? 0, 2, '.', ','),
            'currency' => $transaction->payment_currency ?? 'KES',
            'transaction_status' => ucfirst($transaction->status), // Capitalized Status
            // Timestamps
            'transaction_date' => $transaction->created_at->toDateString(),
            'created_timestamp' => $transaction->created_at->toDateTimeString(),
            // Additional Identifiersphp
            'wallet_account' => $transaction->wallet_account,
            'running_balance' => number_format($transaction->available_balance ?? 0, 2, '.', ','),
            'transaction_reference' => $transaction->transaction_reference ?? null,
            // Description & Status Codes
            'transaction_description' => $transaction->transaction_description ?? null,
            'result_code' => $transaction->result_code ?? null,
            'result_description' => $transaction->result_description ?? null,
            'reversal_status' => $transaction->reversal_status ?? 'Not Reversed',
            // Payment Details (Grouped for clarity)
            'payment_details' => [
                'party_B_account_number' => $transaction->party_B_account_number ?? '07*******7', // Masked for security
                'party_B_account_name' => $transaction->party_B_account_name ?? null,
                'channel_name' => $transaction->channel_name ?? 'AIRTEL MONEY',
                'channel_transaction_reference' => $transaction->channel_transaction_reference ?? null,
            ],
        ];
    }
}


