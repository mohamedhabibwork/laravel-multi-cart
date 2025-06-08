<?php

namespace HCart\LaravelMultiCart\Tests\Fixtures;

use HCart\LaravelMultiCart\Traits\HasCarts;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasCarts;

    protected $fillable = ['name', 'email'];

    protected $table = 'users';
}
