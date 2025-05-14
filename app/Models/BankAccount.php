<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bank_name_ar',
        'bank_name_en',
        'account_name_ar',
        'account_name_en',
        'account_number',
        'iban',
        'is_active', // Make sure this is included
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean', // Cast is_active to boolean
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}