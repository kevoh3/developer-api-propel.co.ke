<?php

namespace App\Traits\User;

use App\Models\WalletAccountController;
use Exception;
use App\Models\UserWallet;
use App\Models\Admin\Currency;
use App\Constants\PaymentGatewayConst;
use App\Models\Merchant\SandboxWallet;
use App\Models\Merchant\DeveloperApiCredential;

trait RegisteredUsers {
    protected function createUserWallets($user) {
        $currencies = Currency::active()->roleHasOne()->pluck("id")->toArray();
        $wallets = [];
//        foreach($currencies as $currency_id) {
//            $wallets[] = [
//                'user_id'       => $user->id,
//                'currency_id'   => $currency_id,
//                'balance'       => 0,
//                'status'        => true,
//                'created_at'    => now(),
//                'wallet_account'=>$wallet_account
//            ];
//        }
        //foreach ($currencies as $currency_id) {
            // Check if the user is a person or business
            if ($user->type === 'personal') {
                // Use the user's full mobile as the wallet account
                $wallet_account = $user->full_mobile;
                $wallet_name=$user->firstname;
                $notification_number=$user->full_mobile;
                // Check for an existing wallet with the same account number
                $existing_wallet = UserWallet::where('wallet_account', $wallet_account)->exists();
                // If a wallet with the same account number exists, generate a numeric account number
                if ($existing_wallet) {
                    $last_wallet = WalletAccountController::orderBy('wallet_account', 'desc')->first();
                    $wallet_account = $last_wallet ? (int)$last_wallet->wallet_account + 1 : 5001;
                    WalletAccountController::create(['wallet_account'=>$wallet_account]);
                }
            } else { // For businesses
                // Generate a numeric account number starting from 5001
                $last_wallet = WalletAccountController::orderBy('wallet_account', 'desc')->first();
                $wallet_account = $last_wallet ? (int)$last_wallet->wallet_account + 1 : 5001;
                WalletAccountController::create(['wallet_account'=>$wallet_account]);

                $wallet_name=$user->company_name;
                $notification_number=$user->full_mobile;
            }
//            $wallets[] = [
//                'user_id'       => $user->id,
//                'currency_id'   => 10,
//                'balance'       => 0,
//                'status'        => true,
//                'created_at'    => now(),
//                'wallet_account'=> $wallet_account,
//                'notification_number'=>$notification_number,
//                'wallet_name'=>$wallet_name,
//                'account_type'=>'user',
//            ];
        //}
        $walletData = [
            'user_id'            => $user->id,
            'currency_id'        => 10,
            'balance'            => 0,
            'status'             => true,
            'created_at'         => now(),
            'wallet_account'     => $wallet_account,
            'notification_number'=> $notification_number,
            'wallet_name'        => $wallet_name,
            'account_type'       => 'user',
        ];

        try{
//            UserWallet::insert($wallets);
//            $user->walletOperators()->attach($walletId, [
//                'role' => $validated['role'],
//                'status' => 1,
//                'created_at' => now(),
//                'updated_at' => now()
//            ]);
            // Insert the wallet and get the ID
            $wallet = UserWallet::create($walletData); // use `create()` if you want to get the model

            // Attach the operator to this wallet
            $user->walletOperators()->attach($wallet->id, [
                'role' => 'admin',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        }catch(Exception $e) {
            // handle error
            throw new Exception("Failed to create wallet! Please try again");
        }
    }
    protected function createDeveloperApi($user) {
        try{
            $controller_acc= $user->id.'_'.'499';
            DeveloperApiCredential::create([
                'user_id'       => $user->id,
                'client_id'         => generate_unique_string("developer_api_credentials","client_id",100),
                'client_secret'     => generate_unique_string("developer_api_credentials","client_secret",100),
                'mode'              => PaymentGatewayConst::ENV_SANDBOX,
                'application_name' =>'Sandbox'.'_'.$controller_acc,
                'controller_acc' => $controller_acc,
                'wallet_account' => 499,
                'status'            => true,
                'created_at'        => now(),
            ]);

            // create developer sandbox wallets
            //$this->createSandboxWallets($user);
        }catch(Exception $e) {
            throw new Exception("Failed to create developer API. Something went wrong!");
        }
    }

    protected function createSandboxWallets($user) {
        if(!$user->developerApi) return false;

        $currencies = Currency::active()->roleHasOne()->pluck("id")->toArray();
        $wallets = [];
        foreach($currencies as $currency_id) {
            $wallets[] = [
                'user_id'   => $user->id,
                'currency_id'   => $currency_id,
                'balance'       => 0,
                'status'        => true,
                'created_at'    => now(),
            ];
        }

        try{
            SandboxWallet::insert($wallets);
        }catch(Exception $e) {
            // handle error
        }
    }
}
