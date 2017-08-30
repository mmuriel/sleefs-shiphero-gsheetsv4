<?php

namespace Sleefs\Controllers\Shiphero;

use App\Http\Controllers\Controller;
use \Google\Spreadsheet\DefaultServiceRequest;
use \Google\Spreadsheet\ServiceRequestFactory;
use \mdeschermeier\shiphero\Shiphero;

Class PurchaseOrderWebHookEndPointController extends Controller {
	

	public function __invoke(){


		$po = json_decode(file_get_contents('php://input'));

		//print_r(json_decode($entityBody));
		$clogger = new \Sleefs\Helpers\CustomLogger("sleefs.log");
		$clogger->writeToLog ("Procesando: ".json_encode($po),"INFO");

		/* Genera la PO extendida */
		Shiphero::setKey('8c072f53ec41629ee14c35dd313a684514453f31');
        $poextended = Shiphero::getPO($po->purchase_order->po_id);

        //return response()->json($poextended);

		/* 
			Recupera la hoja de calculo para 
			determinar si es una PO nueva o vieja.
		*/

		$pathGoogleDriveApiKey = app_path('Sleefs/client_secret.json');
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' .$pathGoogleDriveApiKey);

        $gclient = new \Google_Client;
        $gclient->useApplicationDefaultCredentials();
        $gclient->setApplicationName("Sleeves - Shiphero - Sheets v4 - Production");
        $gclient->setScopes(['https://www.googleapis.com/auth/drive','https://spreadsheets.google.com/feeds']);
        if ($gclient->isAccessTokenExpired()) {
            $gclient->refreshTokenWithAssertion();
        }
        $accessToken = $gclient->fetchAccessTokenWithAssertion()["access_token"];
        ServiceRequestFactory::setInstance(
            new DefaultServiceRequest($accessToken)
        );

        $spreadSheetService = new \Google\Spreadsheet\SpreadsheetService();
        $ssfeed = $spreadSheetService->getSpreadsheetFeed();

        $spreadsheet = (new \Google\Spreadsheet\SpreadsheetService)
        ->getSpreadsheetFeed()
        ->getByTitle('Sleefs - Shiphero - Purchase Orders');
        //->getById('https://docs.google.com/spreadsheets/d/17IiATPBE1GAIxDW-3v4xSG3yr0QOhxr1bQCordZJqds/');
        //->getById('17IiATPBE1GAIxDW-3v4xSG3yr0QOhxr1bQCordZJqds');
        $worksheets = $spreadsheet->getWorksheetFeed()->getEntries();
        $worksheet = $worksheets[0];
        $listFeed = $worksheet->getListFeed(); // Trae los registros con indice asociativo (nombre la columna)
        /** @var ListEntry */
        $alreadyAdded = false;
        foreach ($listFeed->getEntries() as $entry) {
           $record = $entry->getValues();
           if ($record['id'] == $po->purchase_order->po_id){

           		$alreadyAdded = true;

           		/* Actualiza los registros */
           		//$record'id' => $po->purchase_order->po_id,
	            $record['ponumber'] = $poextended->po->results->po_number;
	            $record['status'] = $po->purchase_order->status;
	            $record['createddate'] = $poextended->po->results->created_at;
	            $record['expecteddate'] = $poextended->po->results->po_date;
	            $record['vendor'] = $poextended->po->results->vendor_name;
	            $record['totalcost'] = $poextended->po->results->total_price;
	            $entry->update($record);
           		break;
           }
        }
        //$cellFeed = $worksheet->getCellFeed(); // Indices númericos
        //$arrCellFeed = $cellFeed->toArray();
        //print_r($arrCellFeed);
    
    	if (!$alreadyAdded){

    		$listFeed->insert([

            'id' => $po->purchase_order->po_id,
            'ponumber' => $poextended->po->results->po_number,
            'status' => $po->purchase_order->status,
            'createddate' => $poextended->po->results->created_at,
            'expecteddate' => $poextended->po->results->po_date,
            'vendor' => $poextended->po->results->vendor_name,
            'totalcost' => $poextended->po->results->total_price,

        	]);

    	}
        

        //return response($spreadsheet->);



		//return response($entityBody);
		return response()->json(["code"=>200,"Message" => "Success"]);



	}
	

}