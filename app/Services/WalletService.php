<?php
namespace App\Services;

use App\Models\UserWallet;
use Illuminate\Validation\ValidationException;

class WalletService
{
    protected $channels = [
        "Propel" => "PropelFin",
        "01" => "KCB",
        "02" => "Standard Chartered Bank KE",
        "03" => "Absa Bank",
        "07" => "NCBA",
        "10" => "Prime Bank",
        "11" => "Cooperative Bank",
        "12" => "National Bank",
        "14" => "M-Oriental",
        "16" => "Citibank",
        "18" => "Middle East Bank",
        "19" => "Bank of Africa",
        "23" => "Consolidated Bank",
        "25" => "Credit Bank",
        "31" => "Stanbic Bank",
        "35" => "ABC Bank",
        "36" => "Choice Microfinance Bank",
        "43" => "Eco Bank",
        "50" => "Paramount Universal Bank",
        "51" => "Kingdom Bank",
        "53" => "Guaranty Bank",
        "54" => "Victoria Commercial Bank",
        "55" => "Guardian Bank",
        "57" => "I&M Bank",
        "61" => "HFC Bank",
        "63" => "DTB",
        "65" => "Mayfair Bank",
        "66" => "Sidian Bank",
        "68" => "Equity Bank",
        "70" => "Family Bank",
        "72" => "Gulf African Bank",
        "74" => "First Community Bank",
        "75" => "DIB Bank",
        "76" => "UBA",
        "78" => "KWFT Bank",
        "89" => "Stima Sacco",
        "97" => "Telcom Kenya",
        "63902" => "MPesa",
        "63903" => "AirtelMoney",
        "00" => "SasaPay",
    ];
    /**
     * Get the wallet balance for a given wallet account.
     *
     * @param string $walletAccount
     * @return string|null
     */
    public  function getWalletBalance(string $walletAccount): ?string
    {
        // Find the Developer API credentials by wallet_account
        $wallet = UserWallet::where('wallet_account', $walletAccount)->first();

        if (!$wallet) {
            return null; // Return null if the account doesn't exist
        }
        return number_format($wallet->balance, 2, '.', '');
    }
    public  function checkBalanceOrFail(string $walletAccount, float $amount): void
    {
        $wallet = UserWallet::where('wallet_account', $walletAccount)->first();

        if (!$wallet) {
            throw ValidationException::withMessages([
                'walletNumber' => ['Wallet account does not exist.']
            ]);
        }
        if ($wallet->balance < $amount) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient balance.']
            ]);
        }
    }
    public function deductAmount(UserWallet $wallet, float $amount)
    {
        $wallet->balance -= $amount;
        $wallet->save(); // This is inside the transaction, so it will be rolled back if needed.
    }
    public function increaseAmount(UserWallet $wallet, float $amount)
    {
        $wallet->balance += $amount;
        $wallet->save(); // This is inside the transaction, so it will be rolled back if needed.
    }
    public  function getChannelName(string $channelCode): ?string
    {
        return $this->channels[$channelCode] ?? null;
    }

}
