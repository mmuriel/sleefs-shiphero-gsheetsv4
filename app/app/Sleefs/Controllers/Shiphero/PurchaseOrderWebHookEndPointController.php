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


use Sleefs\Models\Shiphero\PurchaseOrder;
use Sleefs\Models\Shiphero\PurchaseOrderItem;

Class PurchaseOrderWebHookEndPointController extends Controller {
	

	public function __invoke(){



        /*
            0.  Se inicializan los objetos necesarios para gestionar la peticion que está dividida
                en 3 partes:

                1. Registro de los datos de la PO en el libro "POS" del spreadsheet
                2. Registro de los datos de la PO en el libro "Orders" del spreadsheet
                3. Registro de los datos de la PO en el libro "Qty-ProductType" del spreadsheet
        */

        $debug = array(false,false,true);//Define que funciones se ejecutan y cuales no.


		$po = json_decode(file_get_contents('php://input'));

		//print_r(json_decode($entityBody));
		$clogger = new \Sleefs\Helpers\CustomLogger("sleefs.log");
		$clogger->writeToLog ("Procesando: ".json_encode($po),"INFO");

		/* Genera la PO extendida */
		Shiphero::setKey('8c072f53ec41629ee14c35dd313a684514453f31');
        $poextended = Shiphero::getPO($po->purchase_order->po_id);

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
        $wsCtrlLocker =  new GoogleSpreadsheetFileLocker();
        $wsCtrlUnLocker =  new GoogleSpreadsheetFileUnLocker();

        $worksheets = $spreadsheet->getWorksheetFeed()->getEntries();
        $index = $wsCtrlIndex->getWSIndex($worksheets,'Control');
        $worksheet = $worksheets[$index];
        $cellFeed = $worksheet->getCellFeed();
        $cell = $cellFeed->getCell(1,1);
        
        if ($cell->getContent()=='locked'){

            return response()->json(["code"=>204,"Message" => "Not available system"]);

        }
        //Realiza el bloqueo del documento
        $resLock = $wsCtrlLocker->lockFile($spreadsheet,$index);        
        /*

            1. Almacena los registros el libro "POS" del documento en google spreadsheets

        */

        if ($debug[0] == true){

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
        }

        /*

            2. Almacena el registro el libro "Orders" del documento en google spreadsheets

        */
    	

        if ($debug[1] == true){

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
        }
        
        /*

            3.  Registra la Orden en la DB, recalcula los valores por ProductType
                y registro los datos "Qty-ProductType"

        */

        if ($debug[2] == true){

            //var_dump($poextended);
            //echo "\n===============\n";
            //var_dump($po);
            $arrProductType = array();


            //3.1. Define si la orden ya ha sido registrada en la DB
            $poDb = PurchaseOrder::where('po_id','=',$po->purchase_order->po_id)->first();
            if ($poDb == null){
                $poDb = new PurchaseOrder();
                $poDb->po_id = $po->purchase_order->po_id;
                $poDb->po_number = $poextended->po->results->po_number;
                $poDb->po_date = $poextended->po->results->po_date;
                $poDb->fulfillment_status = $poextended->po->results->fulfillment_status;
                $poDb->save();

                //3.2. Se registran los PO Items
                for ($i = 0; $i < count($po->purchase_order->line_items);$i++){

                    $itemExt = $poextended->po->results->items[$i];
                    $itemShort = $po->purchase_order->line_items[$i];
                    $variant = Variant::where('sku','=',$itemExt->sku)->first();
                    $prdTypeItem = 'nd';


                    if ($variant!=null && is_object($variant)){
                        $prdTypeItem = $variant->product->product_type;
                    }

                    if (!isset($arrProductType[$prdTypeItem])){
                        $arrProductType[$prdTypeItem] = 1;
                    }

                    $itm = new PurchaseOrderItem();
                    $itm->idpo = $poDb->id;
                    $itm->sku = $itemExt->sku;
                    $itm->shid = $itemShort->id;
                    $itm->quantity = $itemExt->quantity;
                    $itm->quantity_received = $itemExt->quantity_received;
                    $itm->name = $itemExt->product_name;
                    $itm->idmd5 = md5($itemExt->sku.'-'.$poDb->po_id);
                    $itm->product_type = $prdTypeItem;
                    $itm->qty_pending = ((int)$itemExt->quantity - (int)$itemExt->quantity_received);

                    $itm->save();

                }
            }
            else {

                $poDb->po_number = $poextended->po->results->po_number;
                $poDb->fulfillment_status = $poextended->po->results->fulfillment_status;
                $poDb->save();

                for ($i = 0; $i < count($po->purchase_order->line_items);$i++){

                    $itemExt = $poextended->po->results->items[$i];
                    $itemShort = $po->purchase_order->line_items[$i];
                    $itm = PurchaseOrderItem::where('idmd5','=',md5($itemExt->sku.'-'.$poDb->po_id))->first();
                    if ($itm == null){

                        $variant = Variant::where('sku','=',$itemExt->sku)->first();
                        $prdTypeItem = 'nd';


                        if ($variant!=null && is_object($variant)){
                            $prdTypeItem = $variant->product->product_type;
                        }

                        $itm = new PurchaseOrderItem();
                        $itm->idpo = $poDb->id;
                        $itm->sku = $itemExt->sku;
                        $itm->shid = $itemShort->id;
                        $itm->quantity = $itemExt->quantity;
                        $itm->quantity_received = $itemExt->quantity_received;
                        $itm->name = $itemExt->product_name;
                        $itm->idmd5 = md5($itemExt->sku.'-'.$poDb->po_id);
                        $itm->product_type = $prdTypeItem;
                        $itm->qty_pending = ((int)$itemExt->quantity - (int)$itemExt->quantity_received);
                        $itm->save();
                    }
                    else{

                        $variant = Variant::where('sku','=',$itemExt->sku)->first();
                        $prdTypeItem = 'nd';
                        if ($variant!=null && is_object($variant)){
                            $prdTypeItem = $variant->product->product_type;
                        }
                        $itm->quantity = $itemExt->quantity;
                        $itm->quantity_received = $itemExt->quantity_received;
                        $itm->name = $itemExt->product_name;
                        $itm->product_type = $prdTypeItem;
                        $itm->qty_pending = ((int)$itemExt->quantity - (int)$itemExt->quantity_received);
                        $itm->save();
                    }

                    if (!isset($arrProductType[$prdTypeItem])){
                        $arrProductType[$prdTypeItem] = 1;
                    }

                }
            }


        }


        // 3.3 Registra en el archivo de hoja de cálculo los nuevos valores para los ProductType
        
        //Define los valores desde la DB
        $arrPrdTypeKeys = array_keys($arrProductType);
        foreach ($arrPrdTypeKeys as $prdType){

            $arrProductType[$prdType] = \DB::table('sh_purchaseorder_items')->select(\DB::raw('sum(qty_pending) as total'))->where('product_type','=',$prdType)->first()->total;

        }


        //Registra propiamente dicho en la hoja de calculo
        $worksheetOrders = $worksheets[2];
        $listFeedOrders = $worksheetOrders->getListFeed(); // Trae los registros con indice asociativo (nombre la columna)
            
        foreach ($arrProductType as $key=>$val){

            $alreadyAdded = false;
            //Genera las actualizaciones
            foreach ($listFeedOrders->getEntries() as $entry) {
               $record = $entry->getValues();
               if ($record['type'] == $key){

                    $record['qty'] = $val;
                    $entry->update($record);
                    $alreadyAdded = true;
                    break;
               }
            }
            //Genera el nuevo registro
            if (!$alreadyAdded){
            $listFeedOrders->insert([
                'type' => $key,
                'qty' => $val,
                ]);
            }


        }

        /*
        $resUnLock = $wsCtrlUnLocker->unLockFile($spreadsheet,$index); 
        return "";
        */

        /*

            4.  Genera la respuesta al servidor de shiphero
                y bloquea la hoja de cáculo

        */

        //Realiza el bloqueo del documento
        $resUnLock = $wsCtrlUnLocker->unLockFile($spreadsheet,$index);
		return response()->json(["code"=>200,"Message" => "Success"]);



	}
	

}