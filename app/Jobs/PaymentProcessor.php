<?php

namespace App\Jobs;

use App\Constants\PaymentGatewayConst;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\UserWallet;
use App\Services\WalletService;
use App\Services\BankProcessor;
use App\Services\MpesaProcessor;
use App\Services\PropelProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentProcessor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $paymentRequest;

    /**
     * Create a new job instance.
     */
    public function __construct(PaymentRequest $paymentRequest)
    {
        $this->paymentRequest = $paymentRequest;
    }

    /**
     * Execute the job.
     */


    public function handle(WalletService $walletService, BankProcessor $bankProcessor, MpesaProcessor $mpesaProcessor, PropelProcessor $propelProcessor)
    {
        Log::info("Processing PaymentProcessor for PaymentRequest ID: " . $this->paymentRequest->id);
        DB::beginTransaction();
        try {
            $payment = $this->paymentRequest;
            $wallet = UserWallet::where('wallet_account', $payment->merchant_code)->first();

            Log::info('wallet details', ['wallet' => $wallet]);
// First condition: Wallet does not exist
            if (!$wallet) {
                Log::error("Wallet {$payment->source_account_number} is not found.");
                DB::rollBack();
                return;
            }
// Second condition: Wallet exists but status is not 1 (active)
            if ($wallet->status !== 1) {
                Log::error("Wallet {$payment->source_account_number} is inactive.");
                DB::rollBack();
                return;
            }
// If both checks pass, proceed with your logic
            // Check balance and deductclear
            if ($wallet->balance < $payment->total_amount) {
                Log::error("Insufficient funds for {$wallet->wallet_account}");
                DB::rollBack();
                return;
            }
            // Deduct amount from wallet
            $walletService->deductAmount($wallet, $payment->total_amount);
            $wallet->refresh();
            Log::info("Amount {$payment->total_amount} deducted from wallet {$wallet->wallet_account}");
            $trx_id = 'AO' . $this->generateTransactionCode($wallet->id);
            $transaction = Transaction::create([
                'trx_id' => $trx_id,
                'type' => PaymentGatewayConst::TYPEMONEYOUT,
                'attribute' => PaymentGatewayConst::SEND,
                'user_type' => 'MERCHANT',
                'user_id' => $wallet->user_id,
//                'merchant_id' => $wallet->merchant_id,
//                'merchant_wallet_id' => $wallet->id,
                'wallet_id' => $wallet->id,
//                'payment_gateway_currency_id' => 10,
                'available_balance' => $wallet->balance,
                'request_amount' => $payment->amount,
                'request_currency' => $payment->currency,
                'total_charge' => $payment->total_amount - $payment->amount,
                'total_payable' => $payment->total_amount,
                'receive_amount' => $payment->amount,
                'exchange_rate' => $payment->conversion_rate,
                'percent_charge' => 0,
                'sms_charge'=>0.0,
                'fixed_charge'=>$payment->propel_transaction_charge,
//                'receiver_type' => 'USER',
//                'receiver_id' => null,
                'payment_currency' => $payment->currency,
                'destination_currency' => $wallet->code,
                'sending_purpose_id' => null,
                'source_of_fund_id' => null,
                'remark' => $payment->transaction_description,
                'details' => json_encode($payment),
                'status' => PaymentGatewayConst::STATUSPENDING,
                'callback_ref' => $payment->checkout_reference,
                'part_b_account' => $payment->merchant_code,
                'part_b_name' => $payment->part_b_name,
                'party_b_plateform' => $payment->destination_channel,
                'transaction_category' => 'OUT',
                'payment_request_id' => $payment->id,

//                'payment_type' => $payment->payment_type,
            ]);
            Log::info("Transaction {$transaction->trx_id} created for {$wallet->wallet_account}");
            // Update payment status to processing
            $payment->update(['status' => 'processing']);
            $payment->update(['propel_transaction_code' => $trx_id]);
            // Increment wallet's total_accumulative_money_out by the total_payable value
            $wallet->increment('total_accumulative_money_out', $payment->total_amount);
            // Commit transaction to save wallet deduction, transaction, and payment request update
            DB::commit();
            // **Send to appropriate processor**
            switch ($payment->destination_channel_code) {
                case '63902':
                    $mpesaProcessor->process($payment,$transaction);
                    break;
                case 'Bank':
                    $bankProcessor->process($payment);
                    break;
                case 'Propel':
                    $propelProcessor->process($payment,$transaction);
                    break;
                default:
                    Log::error("Unknown payment channel: " . $payment->destination_channel);
                    return;
            }
//            // Update transaction and payment status as completed
//            $payment->update(['status' => 'completed']);
//            $transaction->update(['status' => 2]); // Completed
//            Log::info("Payment {$payment->transaction_ref} successfully processed.");
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback if any error occurs
            Log::error("Payment Processing Failed: " . $e->getMessage());
        }
    }

    function generateTransactionCode($wallet_id)
    {
        $timestamp = time(); // Current timestamp
        $unique_number = $timestamp . $wallet_id; // Concatenate timestamp and wallet ID
        return $this->base62Encode($unique_number); // Convert to Base62
    }

    function base62Encode($number)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($characters);
        $result = '';

        while ($number > 0) {
            $result = $characters[$number % $base] . $result;
            $number = intdiv($number, $base);
        }

        return $result;
    }

    function base62Decode($encoded)
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $base = strlen($characters);
        $length = strlen($encoded);
        $number = 0;

        for ($i = 0; $i < $length; $i++) {
            $number = $number * $base + strpos($characters, $encoded[$i]);
        }

        return $number;
    }

}
