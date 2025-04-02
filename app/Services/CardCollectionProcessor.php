<?php
    namespace App\Services;

    use App\Models\PaymentRequest;
    use Illuminate\Support\Facades\Log;

    class CardCollectionProcessor
    {
    public function process(PaymentRequest $payment)
    {
    Log::info("Processing Mpesa transaction for {$payment->account_number}");

$payment->update(['status' => 'completed']);
}
}
