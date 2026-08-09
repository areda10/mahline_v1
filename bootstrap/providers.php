<?php

//use App\Providers\AppServiceProvider;
/*
return [
    AppServiceProvider::class,
];
*/

return [
    App\Providers\AppServiceProvider::class,

    /*
    |--------------------------------------------------------------------------
    | MAHLINE Framework
    |--------------------------------------------------------------------------
    */

    App\Core\Database\Providers\DatabaseMacroServiceProvider::class,
    /*App\Core\Providers\CoreServiceProvider::class,
    
    App\Core\Providers\DomainServiceProvider::class,*/
];