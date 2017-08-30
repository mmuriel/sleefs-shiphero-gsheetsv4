<?php

namespace Sleefs\Models\Shiphero;

Class PurchaseOrder {

	private $id,$status,$created_at,$vendor_name,$vendor_id,$payment_method,$tax,$discount,$subtotal,$shipping_price,$total_price,$po_date;
	private $items = Array();
	
	public function __construct($id,$status,$created_at,$vendor_name,$vendor_id,$payment_method,$tax,$discount,$subtotal,$shipping_price,$total_price,$po_date){
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

	public function addItem (OrderItem $item){

		array_push($this->items,$item);
		return true;

	}

	public function getItem($iditem){

		foreach ($this->items as $key => $item) {
			# code...
			if ($item->id == $iditem){

				return $item;

			}
		}
	}

}