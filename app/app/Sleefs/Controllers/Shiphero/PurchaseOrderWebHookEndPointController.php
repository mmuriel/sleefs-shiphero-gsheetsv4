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

        $itemsRegistered = array();


        //Genera las actualizaciones
        foreach ($listFeed->getEntries() as $entry) {
           $record = $entry->getValues();
           if ($record['id'] == $po->purchase_order->po_id){

                foreach ($poextended->po->results->items as $po_item){


                    if ($record['sku'] == $po_item->sku){

                   		/* Actualiza los registros */
                   		//$record'id' => $po->purchase_order->po_id,
        	            $record['ordered'] = $po_item->quantity; 
                        $record['received'] = $po_item->quantity_received;
                        $record['pending'] = $po_item->quantity - $po_item->quantity_received;
                        $record['status'] = $po_item->fulfillment_status;
                        $record['total'] = $poextended->po->results->total_price;
        	            $entry->update($record);
                        array_push($itemsRegistered,$po_item->sku);
                        break;

                    }

                }
           }
        }

        //Genera los nuevos registros
        foreach ($poextended->po->results->items as $po_item){

            $alreadyAdded = false;
            foreach($itemsRegistered as $itemRegistered){

                if ($po_item->sku == $itemRegistered){
                    $alreadyAdded = true;
                    break;
                }
            }

            if (!$alreadyAdded){

            $listFeed->insert([

                'id' => $po->purchase_order->po_id,
                'po' => $poextended->po->results->po_number,
                'sku' => $po_item->sku,
                'status' => $po_item->fulfillment_status,
                'ordered' => $po_item->quantity,
                'received' => $po_item->quantity_received,
                'pending' => $po_item->quantity - $po_item->quantity_received,
                'total' => $poextended->po->results->total_price,

                ]);

            }

        }

        //$cellFeed = $worksheet->getCellFeed(); // Indices númericos
        //$arrCellFeed = $cellFeed->toArray();
        //print_r($arrCellFeed);
    
    	
        

        //return response($spreadsheet->);



		return response()->json($po);
		//return response()->json(["code"=>200,"Message" => "Success"]);



	}
	

}