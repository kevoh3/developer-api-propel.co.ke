<?php
namespace App\Services;

use App\Models\DeveloperApiCredential;
use App\Models\UserWallet;
use Illuminate\Validation\ValidationException;

class DeveloperAuthService
{
    /**
     * Check if the given AccountNumber matches the Developer's wallet_account.
     *
     * @param DeveloperApiCredential $data
     * @param string $accountNumber
     * @return int
     */
//    public static function validateAccountNumber(DeveloperApiCredential $data, string $accountNumber): bool
//    {
//        return $data->wallet_account === $accountNumber;
//    }
    public static function validateAccountNumber(DeveloperApiCredential $data, string $accountNumber): ?int
    {
        $wallet = UserWallet::where('wallet_account', $accountNumber)->first();
        if (!$wallet) {
           return null;
        }

        return $data->wallet_account === $accountNumber ? $wallet->id : null;
    }
}
