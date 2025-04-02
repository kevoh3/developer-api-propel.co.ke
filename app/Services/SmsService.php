<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send SMS
     *
     * @param string $to The recipient's phone number(s)
     * @param string $message The message to send
     * @param string|null $from The sender ID (optional)
     * @return array
     */
    public static function sendSms(string $to, string $message, string $from = 'PROPELFIN'): array
    {
        $cleanedInput = preg_replace('/\s+/', '', $to);
        // Extract the last 9 digits
        $lastNineDigits = substr($cleanedInput, -9);
        // Prepend +254 to the extracted digits
        $formattedNumber = '254' . $lastNineDigits;
        $url = 'https://api.tililtech.com/sms/v3/sendsms';
        $payload = [
            'api_key'       =>env('TILIL_API_KEY'),
            'service_id'    => 0,
            'mobile'        => $formattedNumber,
            'response_type' => 'json',
            'shortcode'     => $from,
            'message'       => $message,
            //'date_send'     => now()->format('Y-m-d H:i:s'), // Current timestamp
        ];

        try {
            $response = Http::post($url, $payload);

            if ($response->successful()) {
                return [
                    'status'  => 'success',
                    'message' => 'SMS sent successfully.',
                    'data'    => $response->json(),
                ];
            } else {
                return [
                    'status'  => 'error',
                    'message' => 'Failed to send SMS.',
                    'error'   => $response->body(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'status'  => 'error',
                'message' => 'An error occurred while sending SMS.',
                'error'   => $e->getMessage(),
            ];
        }
    }
    /**
     * Send money transfer notification to the sender.
     */
    public static function sendSenderNotification(string $to, string $receiverName, float $amount, string $transactionId, float $walletBalance): array
    {
        $message = "You have successfully sent KES " . number_format($amount, 2) .
            " to $receiverName on " . now()->format('d M Y, h:i A') . ". " .
            "Transaction ID: $transactionId. " .
            "Your new wallet balance is KES " . number_format($walletBalance, 2) . ". " .
            "Thank you for using PropelFin.";

        return self::sendSms($to, $message);
    }
    public static function sendPayerNotification( string $to,
                                                  string $receiverName,
                                                  float $amount,
                                                  string $transactionId,
                                                  float $walletBalance,
                                                  string $thirdPartyName,
                                                  string $thirdPartyTransactionCode): array
    {
        $thirdPartyName = $thirdPartyName ? strtoupper($thirdPartyName) : '';
        $message = "You have successfully paid KES " . number_format($amount, 2) .
            " to $thirdPartyName "."$receiverName on " . now()->format('d M Y, h:i A') . ". " .
            "Transaction ID: $transactionId. " .
            "(Ref: $thirdPartyTransactionCode). " .
            "Your new wallet balance is KES " . number_format($walletBalance, 2) . ". " .
            "Thank you for using PropelFin.";

        return self::sendSms($to, $message);
    }
    public static function sendSenderExternalNotification( string $to,
                                                  string $receiverName,
                                                  float $amount,
                                                  string $transactionId,
                                                  float $walletBalance,
                                                  string $thirdPartyName,
                                                  string $thirdPartyTransactionCode): array
    {
        $thirdPartyName = $thirdPartyName ? strtoupper($thirdPartyName) : '';
        $message = "You have successfully sent KES " . number_format($amount, 2) .
            " to $thirdPartyName "."$receiverName on " . now()->format('d M Y, h:i A') . ". " .
            "Transaction ID: $transactionId. " .
            "(Ref: $thirdPartyTransactionCode). " .
            "Your new wallet balance is KES " . number_format($walletBalance, 2) . ". " .
            "Thank you for using PropelFin.";

        return self::sendSms($to, $message);
    }
    public static function sendReceiverExternalNotification( string $to,
                                                           string $sender_name,
                                                           float $amount,
                                                           string $transactionId,
                                                           string $sender_account,
                                                           string $thirdPartyTransactionCode): array
    {
        $sender_name = $sender_name ? strtoupper($sender_name) : '';
        $message = "You have  received KES " . number_format($amount, 2) .
            " from $sender_name "."$sender_account on " . now()->format('d M Y, h:i A') . ". " .
            "Transaction ID: $transactionId. " .
            "(Ref: $thirdPartyTransactionCode). " .
            "PropelFin Pay Easy";
        return self::sendSms($to, $message);
    }
    /**
     * Send money receipt notification to the receiver.
     */
    public static function sendReceiverNotification(string $to, string $senderName, float $amount, string $transactionId, float $walletBalance): array
    {
        $message = "You have received KES " . number_format($amount, 2) .
            " from $senderName on " . now()->format('d M Y, h:i A') . ". " .
            "Transaction ID: $transactionId. " .
            "Your new wallet balance is KES " . number_format($walletBalance, 2) . ". " .
            "Thank you for using PropelFin.";

        return self::sendSms($to, $message);
    }
    public static function sendSaccoManagerNotification(string $to, string $name, string $saccoName, string $username, string $password): array
    {
        $message = "Hi $name, you have been created as the Sacco Manager for $saccoName. " .
            "Use the following credentials to log in: " .
            "Username: $username, Password: $password. " .
            "Please log in and update your password. Thank you for using PropelFin.";

        return self::sendSms($to, $message);
    }
    public static function sendSaccoMemberNotification(string $to, string $name, string $saccoName, string $username, string $password): array
    {
//        $message = "Hi $name, you have been added to $saccoName. " .
//            "Use the following credentials to log in: " .
//            "Username: $username, Password: $password. " .
//            "Please log in and update your password. Thank you for using PropelFin.";

        $message = "Hi $name, you have been added to $saccoName. " .
            "Use the following credentials to log in to propel.co.ke and update your password: " .
            "Username: $username, Password: $password.  Thank you for using PropelFin.";

        return self::sendSms($to, $message);
    }
    public static function sendSaccoExixstingUserNotification(string $to, string $name, string $saccoName): array
    {
        $message = "Hi $name, you have been added to $saccoName. " .
            "Login to access PropelConnect: Thank you for using PropelFin.";

        return self::sendSms($to, $message);
    }

    public static function sendLoadWalletNotification(
        string $to,
        string $senderName,
        float $amount,
        string $transactionId,
        float $walletBalance,
        string $thirdPartyName,
        string $thirdPartyTransactionCode
    ): array {
        $message = "Your wallet has been loaded with KES " . number_format($amount, 2) .
            " by $senderName on " . now()->format('d M Y, h:i A') . ". " .
            "Transaction ID: $transactionId. " .
            "Third-Party: $thirdPartyName (Ref: $thirdPartyTransactionCode). " .
            "New wallet balance: KES " . number_format($walletBalance, 2) . ". " .
            "Thank you for using PropelFin.";

        return self::sendSms($to, $message);
    }
    public static function sendInvestmentNotification(
        string $to,
        string $investorName,
        float $investmentAmount,
        string $investmentId,
        float $newInvestmentValue,
        string $investmentPlatformName,
        string $platformTransactionCode
    ): array {
        $message = "Your investment of KES " . number_format($investmentAmount, 2) .
            " has been successfully received From $investorName on " . now()->format('d M Y, h:i A') . ". " .
            "Transaction ID: $investmentId. " .
            "Platform: $investmentPlatformName (Ref: $platformTransactionCode). " .
            "New investment value: KES " . number_format($newInvestmentValue, 2) . ". " .
            "Thank you for choosing to invest with us.";

        return self::sendSms($to, $message);
    }
    public static  function sendRequestMoneySsm($to,$amount,$name,$account,$link)
    {

        $message="You have received a request to pay KES ". number_format($amount, 2)." to ".$name. " Account ".$account.". To proceed click the link: ".$link;
        return self::sendSms($to, $message);
    }
}
