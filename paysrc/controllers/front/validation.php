<?php

class PaysrcValidationModuleFrontController extends ModuleFrontController
{


	public function postProcess()
	{
		// Checks the cart address was added
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
            Tools::redirect('index.php?controller=order&step=1');

		// Checks the payment module is active
        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == 'paysrc')
            {
                $authorized = true;
                break;
            }
        if (!$authorized)
            die($this->module->getTranslator()->trans('This payment method is not available.', array(), 'Modules.Paysrc.Shop'));

		// Checks the customer
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

		// Get the order values
        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $mailVars = array(
			'{payee_email}' => '',
			'{payee_name}' => '',
        );

		// Change the currency
        $validation = $this->module->validateOrder(
				$cart->id,
				Configuration::get('PAYSRC_OS_WAITING'), 
				$total,
				$this->module->displayName,
				NULL,
				$mailVars,
				(int)$currency->id,
				false,
				$customer->secure_key
			);

		// Generates the payment request
		$paymentRequest = $this->module->createPaymentRequest($total,$currency,$customer);
		if(empty($paymentRequest)) {
        	Tools::redirect('/index.php?controller=order-detail&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
		}
		/*
		$order = new Order( $this->module->currentOrder);
		$order_payment = $order->getOrderPayments();
        $payments = $order->getOrderPaymentCollection();
        foreach ($payments as $payment) {
			if($payment->method == $this->module->displayName) {
				$payment->transaction_id = $paymentRequest['id'];
				break;
			}
		}
		*/
        Tools::redirect('/index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);

	}

}

