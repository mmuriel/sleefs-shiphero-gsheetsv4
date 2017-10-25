<?php

namespace Sleefs\Controllers\Shopify;

use App\Http\Controllers\Controller;

class ProductUpdateController extends Controller {

	public function __invoke(){

		$prd = json_decode(file_get_contents('php://input'));
		$clogger = new \Sleefs\Helpers\CustomLogger("sleefs.log");
		$clogger->writeToLog ("Procesando: ".json_encode($prd),"INFO");



		return response()->json(["code"=>200,"Message" => "Success"]);

	}	

}