<?php

namespace Sleefs\Controllers\Shiphero;

use App\Http\Controllers\Controller;
use \Google\Spreadsheet\DefaultServiceRequest;
use \Google\Spreadsheet\ServiceRequestFactory;
use \mdeschermeier\shiphero\Shiphero;
use \Sleefs\Helpers\ProductTypeGetter;

use Sleefs\Models\Shopify\Variant;
use Sleefs\Models\Shopify\Product;

use Sleefs\Helpers\Google\SpreadSheets\GoogleSpreadsheetGetWorkSheetIndex;
use Sleefs\Helpers\Google\SpreadSheets\GoogleSpreadsheetFileLocker;
use Sleefs\Helpers\Google\SpreadSheets\GoogleSpreadsheetFileUnLocker;

Class PurchaseOrderWebHookEndPointController extends Controller {
	

	public function __invoke(){


		$po = json_decode(file_get_contents('php://input'));

		//print_r(json_decode($entityBody));
		$clogger = new \Sleefs\Helpers\CustomLogger("sleefs.log");
		$clogger->writeToLog ("Procesando: ".json_encode($po),"INFO");

		/* Genera la PO extendida */
		Shiphero::setKey('8c072f53ec41629ee14c35dd313a684514453f31');
        $poextended = Shiphero::getPO($po->purchase_order->po_id);

        





        /*

            1. Almacena los registros el libro "POS" del documento en google spreadsheets

        */



		/* 
			Recupera la hoja de calculo para 
			determinar si es una PO nueva o vieja.
		*/

		$pathGoogleDriveApiKey = app_path('Sleefs/client_secret.json');
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' .$pathGoogleDriveApiKey);

        $gclient = new \Google_Client;
        $gclient->useApplicationDefaultCredentials();
        $gclient->setApplicationName("Sleeves - Shiphero - Sheets v4 - DEV");
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
        ->getByTitle('Sleefs - Shiphero - Purchase Orders - DEV');


        /*

            Determina si la hoja de cáculo está siendo modificada

        */

        $wsCtrlIndex = new GoogleSpreadsheetGetWorkSheetIndex();
        $worksheets = $spreadsheet->getWorksheetFeed()->getEntries();
        $index = $wsCtrlIndex->getWSIndex($worksheets,'Control');
        $worksheet = $worksheets[$index];
        $cellFeed = $worksheet->getCellFeed();
        $cell = $cellFeed->getCell(1,1);
        if ($cell->getContent()=='locked'){

            return response()->json(["code"=>204,"Message" => "Success"]);

        }

        //Realiza el bloqueo del documento
        $resLock1 = $wsCtrlLocker->lockFile($this->spreadsheet,$index);

        //Verifica que la peticion no delvuelva error en condiciones normales y que devuelva error cuando se induce a eso
        $this->assertEquals(true,$resLock1);
        $this->assertEquals(false,$resLock2);

        //Verifica que el contenido de la celda A1 sea = locked
        $worksheet = $worksheets[$index];
        $cellFeed = $worksheet->getCellFeed();
        $cell = $cellFeed->getCell(1,1);
        $this->assertEquals('locked',$cell->getContent());





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


                    
                    
                    //$product = $variant->product;

                    if ($record['sku'] == $po_item->sku){

                        $variant = Variant::where("sku","=",$po_item->sku)->first();
                        //var_dump($variant->product->product_type);

                   		/* Actualiza los registros */
                   		//$record'id' => $po->purchase_order->po_id,

        	            $record['ordered'] = $po_item->quantity; 
                        $record['po'] = $poextended->po->results->po_number; 
                        $record['received'] = $po_item->quantity_received;
                        $record['pending'] = $po_item->quantity - $po_item->quantity_received;
                        $record['status'] = $po_item->fulfillment_status;
                        $record['total'] = $poextended->po->results->total_price;
                        if (isset($variant->product->product_type))
                            $record['type'] = $variant->product->product_type;
                        else
                            $record['type'] = '';
        	            $entry->update($record);
                        array_push($itemsRegistered,$po_item->sku);
                        break;

                    }

                }
           }
        }
        //return false;
        //Genera los nuevos registros para el TAB: POS, y Genera la información de:
        // 
        // - Total Items
        // - Total recibidos
        //

        $orderTotalItemsReceived = 0;
        $orderTotalItems = 0;


        foreach ($poextended->po->results->items as $po_item){

            // Estos dos valores son utilizados en el siguiente paso
            $orderTotalItemsReceived += (0 + $po_item->quantity_received);
            $orderTotalItems += (0 + $po_item->quantity);


            $alreadyAdded = false;
            foreach($itemsRegistered as $itemRegistered){

                if ($po_item->sku == $itemRegistered){
                    $alreadyAdded = true;
                    break;
                }
            }

            if (!$alreadyAdded){

                $variant = Variant::where("sku","=",$po_item->sku)->first();
                if (isset($variant->product->product_type))
                    $typeToRecord = $variant->product->product_type;
                else
                    $typeToRecord = '';
                $listFeed->insert([

                    'id' => $po->purchase_order->po_id,
                    'po' => $poextended->po->results->po_number,
                    'sku' => $po_item->sku,
                    'status' => $po_item->fulfillment_status,
                    'ordered' => $po_item->quantity,
                    'received' => $po_item->quantity_received,
                    'pending' => $po_item->quantity - $po_item->quantity_received,
                    'total' => $poextended->po->results->total_price,
                    'type' => $typeToRecord,

                    ]);

            }

        }

        /*

            2. Almacena el registro el libro "Orders" del documento en google spreadsheets

        */
    	


        $worksheetOrders = $worksheets[1];
        $listFeedOrders = $worksheetOrders->getListFeed(); // Trae los registros con indice asociativo (nombre la columna)
        /** @var ListEntry */
        $alreadyAdded = false;
        $itemsRegistered = array();


        //Genera las actualizaciones
        foreach ($listFeedOrders->getEntries() as $entry) {
           $record = $entry->getValues();
           if ($record['id'] == $po->purchase_order->po_id){

                /* Actualiza los registros */
                //$record'id' => $po->purchase_order->po_id,
                $record['ponumber'] = $poextended->po->results->po_number; 
                $record['status'] = $poextended->po->results->fulfillment_status;
                $record['expecteddate'] = '';
                $record['vendor'] = $poextended->po->results->vendor_name;
                $record['totalcost'] = $poextended->po->results->total_price;
                $record['totalitems'] = $orderTotalItems;
                $record['itemsreceived'] = $orderTotalItems;
                $record['pendingitems'] = $orderTotalItems - $orderTotalItemsReceived;
                //Registra la actualización
                $entry->update($record);
                $alreadyAdded = true;
                break;

           }
        }

        //Genera el nuevo registro


        if (!$alreadyAdded){

        $listFeedOrders->insert([

            'id' => $poextended->po->results->po_id,
            'ponumber' => $poextended->po->results->po_number,
            'status' => $poextended->po->results->fulfillment_status,
            'createddate' => $poextended->po->results->created_at,
            'expecteddate' => '',
            'vendor' => $poextended->po->results->vendor_name,
            'totalcost' => $poextended->po->results->total_price,
            'totalitems' => $orderTotalItems,
            'itemsreceived' => $orderTotalItemsReceived,
            'pendingitems' => $orderTotalItems - $orderTotalItemsReceived,
            'paid' => 'no',

            ]);

        }

        
        /*

            3. Registra el  

        */

        $worksheetOrders = $worksheets[2];
        $listFeedOrders = $worksheetOrders->getListFeed(); // Trae los registros con indice asociativo (nombre la columna)
        /** @var ListEntry */
        $alreadyAdded = false;
        $itemsRegistered = array();


        //Genera las actualizaciones
        foreach ($listFeedOrders->getEntries() as $entry) {
           $record = $entry->getValues();
           if ($record['id'] == $po->purchase_order->po_id){

                /* Actualiza los registros */
                //$record'id' => $po->purchase_order->po_id,
                $record['ponumber'] = $poextended->po->results->po_number; 
                $record['status'] = $poextended->po->results->fulfillment_status;
                $record['expecteddate'] = '';
                $record['vendor'] = $poextended->po->results->vendor_name;
                $record['totalcost'] = $poextended->po->results->total_price;
                $record['totalitems'] = $orderTotalItems;
                $record['itemsreceived'] = $orderTotalItems;
                $record['pendingitems'] = $orderTotalItems - $orderTotalItemsReceived;
                //Registra la actualización
                $entry->update($record);
                $alreadyAdded = true;
                break;

           }
        }

        //Genera el nuevo registro


        if (!$alreadyAdded){

        $listFeedOrders->insert([

            'id' => $poextended->po->results->po_id,
            'ponumber' => $poextended->po->results->po_number,
            'status' => $poextended->po->results->fulfillment_status,
            'createddate' => $poextended->po->results->created_at,
            'expecteddate' => '',
            'vendor' => $poextended->po->results->vendor_name,
            'totalcost' => $poextended->po->results->total_price,
            'totalitems' => $orderTotalItems,
            'itemsreceived' => $orderTotalItemsReceived,
            'pendingitems' => $orderTotalItems - $orderTotalItemsReceived,
            'paid' => 'no',

            ]);

        }
        


        /*

            4. Genera la respuesta al servidor de shiphero

        */

		//return response()->json($po);
		return response()->json(["code"=>200,"Message" => "Success"]);



	}
	

}