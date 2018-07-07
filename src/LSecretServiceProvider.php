<?php
/**
 * Created by PhpStorm.
 * User: crazytang
 * Date: 2018/7/5
 * Time: 14:31
 */

namespace CCSecret;


use Illuminate\Support\ServiceProvider;

class LSecretServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //
    }

    public function register()
    {
        return new LSecret();
    }
}