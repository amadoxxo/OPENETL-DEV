<?php

use App\Http\Models\User;
use App\Http\Modulos\tickets\tickets\TicketsTicketDaop;
use App\Http\Modulos\tickets\adjuntos\TicketsAdjuntoDaop;
use App\Http\Modulos\companias\procesos\CompaniasProceso;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
