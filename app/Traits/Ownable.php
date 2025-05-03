<?php
namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait Ownable
{

    public function isOwner($userIdField = 'user_id')
    {
        return $this->$userIdField == Auth::id();
    }
}
