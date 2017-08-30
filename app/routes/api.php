<?php

use Illuminate\Http\Request;

use \Sleefs\Controllers\Shiphero\PurchaseOrderWebHookEndPointController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


/*

Route::post('/sh', function (Request $request) {
	
	$entityBody = file_get_contents('php://input');
	//print_r(json_decode($entityBody));
	$clogger = new \Sleefs\Helpers\CustomLogger("sleefs.log");
	$clogger->writeToLog ($entityBody,"INFO");
	return response()->json(["DATA" => json_decode($entityBody)]);
	//return response($entityBody);
	//return response()->json(["code"=>200,"Message" => "Success"]);

});

*/
Route::post('/sh','\Sleefs\Controllers\Shiphero\PurchaseOrderWebHookEndPointController');
    




