<?php


namespace Sleefs\Models\Shiphero;

Class PurchaseOrderItem {

	private $id,$sku,$created_at,$price,$fulfillment_status,$vendor_sku,$product_name,$quantity_received,$quantity;
	
	public function __construct($id,$sku,$created_at,$price,$fulfillment_status,$vendor_sku,$product_name,$quantity_received,$quantity,$id_po){

		$this->id_po = $id_po;
		$this->id = $id;
		$this->status = $status;
		$this->created_at = $created_at;
		$this->vendor_name = $vendor_name;
		$this->vendor_id = $vendor_id;
		$this->payment_method = $payment_method;
		$this->tax = $tax;
		$this->discount = $discount;
		$this->subtotal = $subtotal;
		$this->shipping_price = $shipping_price;
		$this->total_price = $total_price;
		$this->po_date = $po_date;

	}


}