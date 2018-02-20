<?php
/**
 * Front controller for PaySrc's Notify URI
 */
class PaysrcNotifyModuleFrontController extends ModuleFrontController {
	

	public function initContent() {
		if(isset($_REQUEST['payment_id'])) {
			$paysrc = $this->module;
			$paysrcOrder = $paysrc->updatePaysrcOrderByPayment($_REQUEST['payment_id']);
			if($paysrcOrder)
				Tools::redirect('index.php?controller=order-detail&id_order='.$paysrcOrder->id_order);
		}
		Tools::redirect('index.php');
	}


}

