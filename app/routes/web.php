<?php

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



Route::get('/test',function(){

	return ('Hola error...');
});


/*

	Rutas para Test con Phpunit

*/

Route::post('/tests/curl',function(Request $req){

	$payload = json_decode(file_get_contents('php://input'));
	return "Hola Mundo Post";

});
