<?php
/**
 * Front controller for PaySrc's Redirect URI
 */
class PaysrcRedirectModuleFrontController extends ModuleFrontController {
	

	public function initContent() {
		if(isset($_REQUEST['id_order'])) {
			$paysrc = $this->module;
			$paysrcOrder = $paysrc->updatePaysrcOrderByOrderId($_REQUEST['id_order']);
			Tools::redirect('index.php?controller=order-detail&id_order='.$_REQUEST['id_order']);
		}
		Tools::redirect('index.php');
	}


}

