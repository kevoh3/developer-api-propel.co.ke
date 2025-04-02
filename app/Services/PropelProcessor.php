<?php
    namespace App\Services;

    use App\Constants\PaymentGatewayConst;
    use App\Models\PaymentRequest;
    use App\Models\Transaction;
    use App\Models\UserWallet;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use App\Services\WalletService;

    class PropelProcessor
    {
        protected $walletService;

        public function __construct(WalletService $walletService)
        {
            $this->walletService = $walletService;
        }
        /*
        This is to handle internal fund transfers
        latter will have to update this function to handle different currencies for now its working for thw single currency---kes
        */
    public function process(PaymentRequest $payment,Transaction $transaction_Original)
    {
        Log::info("Processing Proplel transaction for {$payment->account_number}");
        DB::beginTransaction();
        try {
            $wallet = UserWallet::where('wallet_account', $payment->account_number)->first();
            $source_account = UserWallet::where('wallet_account', $payment->merchant_code)->first();
            Log::info('wallet details', ['wallet' => $wallet]);
// First condition: Wallet does not exist
            if (!$wallet) {
                Log::error("Wallet {$payment->account_number} is not found.");
                DB::rollBack();
                return;
            }
            $this->walletService->increaseAmount($wallet, $payment->amount);
            $wallet->refresh();
            Log::info("Amount {$payment->amount} added to wallet {$wallet->wallet_account}");
            $trx_id = 'AO' . $this->generateTransactionCode($wallet->id);
            $transaction = Transaction::create([
                'trx_id' => $trx_id,
                'type' => PaymentGatewayConst::TYPEMONEYIN,
                'attribute' => PaymentGatewayConst::RECEIVED,
                'user_type' => 'MERCHANT',
                'user_id' => $wallet->user_id,
//                'merchant_id' => $wallet->merchant_id,
//                'merchant_wallet_id' => $wallet->id,
                'wallet_id' => $wallet->id,
//                'payment_gateway_currency_id' => 10,
                'available_balance' => $wallet->balance,
                'request_amount' => $payment->amount,
                'request_currency' => $payment->currency,
//                'total_charge' => $payment->total_amount - $payment->amount,
//                'total_payable' => $payment->total_amount,
                'receive_amount' => $payment->amount,
                'exchange_rate' => 1,
                'percent_charge' => 0,
                'sms_charge'=>0.0,
                'fixed_charge'=>0.0,
//                'receiver_type' => 'USER',
//                'receiver_id' => null,
                'payment_currency' => $payment->currency,
                'destination_currency' => $wallet->code,
                'sending_purpose_id' => null,
                'source_of_fund_id' => null,
                'remark' => $payment->transaction_description,
                'details' => json_encode($payment),
                'status' => PaymentGatewayConst::STATUSSUCCESS,
                'callback_ref' => $payment->checkout_reference,
                'part_b_account' => $payment->account_number,
                'part_b_name' => $source_account->wallet_name,
                'party_b_plateform' => $payment->destinationChannel,
                'party_b_transaction_code' => $transaction_Original->$trx_id,
                'transaction_category' => 'INN',
                'payment_request_id' => $payment->id,
//                'payment_type' => $payment->payment_type,
            ]);
            Log::info("Transaction {$transaction->trx_id} created for {$wallet->wallet_account}");
            // Update payment status to processing
            $payment->update(['status' => 'processing']);
            $payment->update(['propel_transaction_code' => $trx_id]);
            // Commit transaction to save wallet deduction, transaction, and payment request update
            $wallet->increment('total_accumulative_money_in', $payment->amount);
            DB::commit();
            $payment->update(['status' => 'completed']);
            $transaction_Original->update(['status' => 2]); // Completed
            Log::info("Payment {$payment->transaction_ref} successfully processed.");
            $senderPhone_number = $source_account->user->full_mobile;
            $sender_name = $source_account->wallet_name??$source_account->user->firstname;
            $receiver_name = $wallet->wallet_name;
//                        $receiver_phone = $receiverWallet->user->full_mobile;

            $receiver_phone = $wallet->notification_number
                ?? $wallet->user->full_mobile
                ?? '0759231969';
//                ?? $receiverWallet->notification_number
//                ?? '0759231969';
            SmsService::sendReceiverNotification($receiver_phone, $sender_name, $payment->amount, $trx_id, $wallet->balance);
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
}
