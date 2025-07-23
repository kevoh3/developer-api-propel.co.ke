<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Client extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = 'oauth_clients';
    protected $fillable = [
        'id',
        'user_id',
        'name',
        'secret',
        'redirect',
        'personal_access_client',
        'password_client',
        'revoked',
    ];

    protected $hidden = ['secret'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
