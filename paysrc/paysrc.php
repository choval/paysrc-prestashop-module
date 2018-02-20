<?php
/*
* 2018 PAYSRC / PUBLITAR SRL
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
* @author PAYSRC / PUBLITAR SRL <plugin@paysrc.com>
* @copyright 2018 PAYSRC / PUBLITAR SRL
* @license http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
* International Registered Trademark & Property of PUBLITAR SRL
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}
require_once(_PS_MODULE_DIR_.'paysrc/classes/PaysrcOrder.php');

class PaySrc extends PaymentModule
{
    const PAYSRC_API_URI = 'https://api.paysrc.com/v1';
    const PAYSRC_API_TESTNET_URI = 'https://api.testnet.paysrc.com/v1';
    const PAYSRC_URI = 'https://paysrc.com';
    const PAYSRC_TESTNET_URI = 'https://testnet.paysrc.com';
    const PAYSRC_TOKEN_CHECK = 300;

    protected $_html = '';
    protected $_postErrors = array();

    private $token;
    private $testnet;
    private $inline;
    private $checked;
    private $profile;
    private $valid;
    private $expiration;
    public $usd_currency_id;


    // apiCall variables
    private $api_error_code;
    private $api_error_message;


    // Payment request
    private $payment_request;



    private static function configKeys() {
        $configKeys = [
            'testnet' => 'PAYSRC_TESTNET',
                // If we're running on testnet
            'token'   => 'PAYSRC_TOKEN',
                // The application token
            'checked' => 'PAYSRC_CHECKED',
                // Last time the token was checked
            'inline'  => 'PAYSRC_INLINE',
                // If it's allowed to display inline
            'profile' => 'PAYSRC_PROFILE',
                // The profile of the token
            'valid'   => 'PAYSRC_VALID',
                // If the token is valid
            'expiration' => 'PAYSRC_EXPIRATION',
                // Expiration hours for the payment requests
            ];
        return $configKeys;
    }



    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'paysrc';
        $this->tab = 'payments_gateways';
        $this->version = '1.0';
        $this->display = 'view';
        $this->ps_versions_compliancy = [
            'min'=>'1.6',
            'max'=> _PS_VERSION_
        ];
        $this->author = 'PaySrc.com';
        $this->controllers = array('payment','validation');
        $this->is_eu_compatible = 1;    // Cryptos are accepted in EU

        // Allow multiple currencies
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $configKeys = static::configKeys();
        $config = Configuration::getMultiple(array_values($configKeys));
        foreach($configKeys as $k=>$c) {
            if(!empty($config[$c])) {
                if($k == 'profile') {
                    $this->profile = json_decode($config[$c],1);
                } else {
                    $this->$k = $config[$c];
                }
            }
        }

        $this->bootstrap = true;
        $this->need_instance = 1;
        parent::__construct();

        // Revalidate token
        if( $this->token && $this->valid && ( $this->checked+static::PAYSRC_TOKEN_CHECK ) > time() ) {
            $this->profile = $this->apiCall('/user/profile');
            $this->checked = time();
            if(empty($this->profile)) {
                $this->valid = false;
                Configuration::updateValue('PAYSRC_VALID',$this->valid);
            }
            Configuration::updateValue('PAYSRC_PROFILE',json_encode($this->profile));
            Configuration::updateValue('PAYSRC_CHECKED',$this->checked);
        }

        // Check that USD is active
        $this->usd_currency_id = (int)Currency::getIdByIsoCode('USD');

        $this->displayName = $this->l('Bitcoin Cash - PaySrc');
        $this->description = $this->l('Accept Bitcoin Cash Payments without conversions to Bitcoin Legacy nor fiat currencies. Request Bitcoin Cash, keep Bitcoin Cash.');
        $this->confirmUninstall = $this->l('Are you sure about removing this module?');

    }





    /**
     * Installer
     */
    public function install()
    {
        // Install default
        if (!parent::install()) {
            return false;
        }
        // install DataBase
        if (!$this->installSQL()) {
            return false;
        }
        // Registration order status
        if (!$this->installOrderState()) {
            return false;
        }

        // Hooks
        if (!$this->registerHook('paymentReturn') || 
            !$this->registerHook('paymentOptions') ||
            !$this->registerHook('actionOrderStatusPostUpdate') ||
            !$this->registerHook('header') || 
            !$this->registerHook('displayOrderConfirmation') ||
            !$this->registerHook('displayOrderDetail')
        ) {
            return false;
        }

        // Configs
        if(
            !Configuration::updateValue('PAYSRC_TESTNET', 0) ||
            !Configuration::updateValue('PAYSRC_TOKEN', '') ||
            !Configuration::updateValue('PAYSRC_INLINE', 1) ||
            !Configuration::updateValue('PAYSRC_VALID', 0) ||
            !Configuration::updateValue('PAYSRC_CHECKED', 0) ||
            !Configuration::updateValue('PAYSRC_EXPIRATION', 2)
        ) {
            return false;
        }

        return true;
    }



    /**
     * Install the order state
     */
    public function installOrderState() {
        if (!Configuration::get('PAYSRC_OS_WAITING') ||
            !Validate::isLoadedObject(new OrderState(Configuration::get('PAYSRC_OS_WAITING')))) {
            $order_state = new OrderState();
			$order_state->name = [];
            foreach(Language::getLanguages() as $lang) {
                $order_state->name[$lang['id_lang']] = 'Awaiting for Bitcoin Cash Payment';
            }
            $order_state->module_name = $this->name;
            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            if ($order_state->save()) {
                $source = _PS_MODULE_DIR_.'paysrc/logo.png';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $order_state->id.'.gif';
                copy($source, $destination);
            }
            Configuration::updateValue('PAYSRC_OS_WAITING', (int) $order_state->id);
        }
		/*
        // TODO: Configuration for expiration time for BCH payment in hours <-- MIN 1 hour
        if (!Configuration::get('PAYSRC_OS_EXPIRED') ||
            !Validate::isLoadedObject(new OrderState(Configuration::get('PAYSRC_OS_EXPIRED')))) {
            $order_state = new OrderState();
            $order_state->name = [];
            foreach(Language::getLanguages() as $lang) {
                $order_state->name[$lang['id_lang']] = 'PaySrc Payment Expired';
            }
            $order_state->module_name = $this->name;
            $order_state->send_email = false;    // NOTE: Emails are handled by paysrc
            $order_state->color = '#ec2e15';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->save();
            Configuration::updateValue('PAYSRC_OS_EXPIRED', (int) $order_state->id);
        }
		*/
        return true;
    }


    /**
     * Install SQL
     */
    private function installSQL() {
        $sql = [];
        $sql[] = "
            CREATE TABLE `"._DB_PREFIX_."paysrc_order` (
              `id_paysrc_order` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `id_order` int NOT NULL,
              `payment` bigint NOT NULL,
              `checked` datetime NOT NULL,
              `status` varchar(20) NOT NULL,
              `updated` datetime NOT NULL,
              `expires` datetime NOT NULL,
              `amount` decimal(16,8) NOT NULL,
              `balance` decimal(16,8) NOT NULL,
              `address` varchar(100) NOT NULL,
              `order_canceled` tinyint(1) NOT NULL DEFAULT '0'
            );
        ";
        $sql[] = "
            ALTER TABLE `"._DB_PREFIX_."paysrc_order`
            ADD INDEX `id_order` (`id_order`),
            ADD INDEX `payment` (`payment`);
        ";
        foreach ($sql as $q) {
            if (!DB::getInstance()->execute($q)) {
                return false;
            }
        }
        return true;
    }
    private function uninstallSQL()
    {
        $sql = array();
        $sql[] = "DROP TABLE IF EXISTS `"._DB_PREFIX_."paysrc_order`";
        foreach ($sql as $q) {
            if (!DB::getInstance()->execute($q)) {
                return false;
            }
        }
        return true;
    }



    /**
     * Uninstall
     */
    public function uninstall()
    {
        $confs = static::configKeys();
        foreach($confs as $k) {
            if(!Configuration::deleteByName($k))
                return false;
        }
        if(!$this->uninstallSQL()) {
            return false;
        }
        if( !parent::uninstall() ) {
            return false;
        }
        // TODO: Cancel all BCH payments
        return true;
    }



    protected function _postProcess()
    {
    }




    /**
     * Configuration page
     */
    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        if($this->profile) {
            $this->smarty->assign([
                'profile_name' => $this->profile['name'] ?? '',
                'profile_email' => $this->profile['email'] ?? '',
                'profile_address' => $this->profile['address'] ?? '',
            ]);
        }
        if($this->usd_currency_id) {
            $this->smarty->assign([
                'usd_currency_id' => $this->usd_currency_id,
            ]);
        }

        $this->smarty->assign([
            'has_token' => $this->token ? true : false,
        ]);

        $this->_html .= $this->display(__FILE__,'config_header.tpl');
        if($this->valid && $this->usd_currency_id) {
            $this->_html .= $this->display(__FILE__,'config_profile.tpl');
        } else {
            $this->_html .= $this->displayWarning($this->l('Module is not active.'));
            if(!$this->usd_currency_id) {
                $this->_html .= $this->displayError($this->l('USD currency is not available in your shop.'));
            }
            if(!$this->valid) {
                if($this->token) {
                    $this->_html .= $this->displayError($this->l('Application Token is not valid.'));
                } else {
                    $this->_html .= $this->displayError($this->l('Application Token is not set.'));
                }
            }
            $this->_html .= $this->display(__FILE__,'config_instructions.tpl');
        }

        $this->_html .= $this->renderForm();

        return $this->_html;
    }



    /**
     * Header hook
     */
    public function hookHeader($params) {
        $this->context->controller->addJS(($this->_path).'views/js/paysrc.js');
    }



    /**
     * Cancel the payment if the order gets canceled from prestashop
     */
    public function hookActionOrderStatusPostUpdate(&$params) {
        $paysrc_order = PaysrcOrder::loadByOrderId($params['id_order']);
        $this->updatePaysrcOrder($paysrc_order);
    }



    /**
     * hookDisplayOrderConfirmation
     */
    public function hookDisplayOrderConfirmation(&$params) {
    }



    /**
     * hookDisplayOrderDetail
     */
    public function hookDisplayOrderDetail(&$params) {
        $paysrcOrder = $this->updatePaysrcOrderByOrderId( $params['order']->id ,$updated);
        $state = $params['order']->getCurrentState();
		if(Validate::isLoadedObject($paysrcOrder)) {
            $this->smarty->assign([
                'api_uri' => $this->testnet ? static::PAYSRC_API_TESTNET_URI : static::PAYSRC_API_URI,
                'root_uri' => $this->testnet ? static::PAYSRC_TESTNET_URI : static::PAYSRC_URI,
                'shop_name' => $this->context->shop->name,
                'status' => $paysrcOrder->status,
                'payment' => $paysrcOrder->payment,
                'amount' => $paysrcOrder->amount,
                'coin' => 'BCH',
                'balance' => $paysrcOrder->balance,
                'checked' => $paysrcOrder->checked,
                'expires' => $paysrcOrder->expires,
                'updated' => $paysrcOrder->updated,
                'address' => $paysrcOrder->address,
                'reference' => $params['order']->reference,
                'state' => $state,
                'contact_url' => $this->context->link->getPageLink('contact', true),
                'base_uri' => _PS_BASE_URL_,
            ]);
            return $this->fetch('module:paysrc/views/templates/hook/display_order.tpl');
        } else {
			// PaySrc order without pament id
			// Change state of order to PS_OS_ERROR
			$waiting_state = Configuration::get('PAYSRC_OS_WAITING');
			if($state == $waiting_state) {
				$error_state = Configuration::get('PS_OS_ERROR');
				$history = new OrderHistory;
				$history->id_order = $params['order']->id;
				$history->changeIdOrderState(
						$error_state,
						$state
					);
				$history->save();
				Tools::redirect('/index.php?controller=order-detail&id_order='.$this->currentOrder);
			}
		}
    }



    /**
     * Payment options hook
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->valid) {
            return;
        }
        if (!$this->usd_currency_id) {
            return;
        }

        // Checks the USD value
        $total_usd = $params['cart']->getOrderTotal(true, Cart::BOTH);
        if(!$params['cart']->id_currency != $this->usd_currency_id) {
            $currency = Currency::getCurrencyInstance($params['cart']->id_currency);
            $total_usd = $this->toUsd($total_usd,$currency);
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $this->smarty->assign([
            'order_total_usd'=>$total_usd,
        ]);

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->l('Pay with Bitcoin Cash'))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                ->setAdditionalInformation($this->fetch('module:paysrc/views/templates/hook/payment_intro.tpl'));
        $payment_options = [
            $newOption,
        ];
        return $payment_options;
    }



    /**
     * Hook to the payment return page
     */
    public function hookPaymentReturn($params)
    {
        $paysrcOrder = PaysrcOrder::loadByOrderId( $params['order']->id );
//        $paysrcOrder = $this->updatePaysrcOrder( $paysrcOrder );

        // Check if it can be inlinsed
        if(!$this->active || !$this->inline) {
            return;
        }

        $state = $params['order']->getCurrentState();
        if ($state == Configuration::get('PAYSRC_OS_WAITING')) {
            $this->smarty->assign([
                'api_uri' => $this->testnet ? static::PAYSRC_API_TESTNET_URI : static::PAYSRC_API_URI,
                'root_uri' => $this->testnet ? static::PAYSRC_TESTNET_URI : static::PAYSRC_URI,
                'shop_name' => $this->context->shop->name,
                'status' => $paysrcOrder->status,
                'payment' => $paysrcOrder->payment,
                'amount' => $paysrcOrder->amount,
                'coin' => 'BCH',
                'balance' => $paysrcOrder->balance,
                'checked' => $paysrcOrder->checked,
                'expires' => $paysrcOrder->expires,
                'updated' => $paysrcOrder->updated,
                'address' => $paysrcOrder->address,
                'reference' => $params['order']->reference,
                'state' => $state,
                'contact_url' => $this->context->link->getPageLink('contact', true),
                'base_uri' => _PS_BASE_URL_,
            ]);
            return $this->fetch('module:paysrc/views/templates/hook/payment_return.tpl');
        } else {
            // TODO Payment expired, offer to remake payment
            $this->smarty->assign([
                'status'=>'failed',
            ]);
        }
    }




    /**
     * Checks the cart has an accepted module currency
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }




    /**
     * Draws the configuration form
     */
    public function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('PaySrc Settings'),
                    // 'icon' => 'icon-key',
                ],
                'input' => [
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Application Token'),
                        'name' => 'PAYSRC_TOKEN',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Duration of payment requests in hours'),
                        'desc' => $this->l('If no payment is received after this duration, the order is automatically canceled.'),
                        'name' => 'PAYSRC_EXPIRATION',
                        'required' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Display Payment Requests Inline'),
                        'name' => 'PAYSRC_INLINE',
                        'is_bool' => true,
                        'desc' => $this->l('Your country\'s legislation may require you to send the invitation to pay by email only. Disabling the option will hide the invitation on the confirmation page.',[],'Modules.Paysrc.Admin'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled', [], 'Admin.Global'),
                            ]
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Use Testnet (FOR DEVELOPERS)'),
                        'name' => 'PAYSRC_TESTNET',
                        'is_bool' => true,
                        'desc' => $this->l('Accounts on testnet and mainnet are not shared. If you activate testnet, make sure your token is from: testnet.paysrc.com'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled', [], 'Admin.Global'),
                            ]
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='
            .$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm( [$fields_form] );
    }



    /**
     * Validates configuration form values
     */
    protected function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {

            Configuration::updateValue('PAYSRC_INLINE', Tools::getValue('PAYSRC_INLINE'));

            $this->testnet = Tools::getValue('PAYSRC_TESTNET');
            Configuration::updateValue('PAYSRC_TESTNET', $this->testnet);

            $this->token = Tools::getValue('PAYSRC_TOKEN');
            Configuration::updateValue('PAYSRC_TOKEN', $this->token);

            $this->expiration = (int)Tools::getValue('PAYSRC_EXPIRATION');
			if($this->expiration <= 0)
				$this->expiration = 2;
            Configuration::updateValue('PAYSRC_EXPIRATION', $this->expiration);

            $this->profile = false;
            if($this->token) {
                $this->profile = $this->apiCall('/user/profile');
            }
            Configuration::updateValue('PAYSRC_PROFILE',json_encode($this->profile));

            $this->checked = time();
            Configuration::updateValue('PAYSRC_CHECKED',$this->checked);
            
            $this->valid = false;
            if(!empty($this->profile)) {
                $this->valid = true;
            }
            Configuration::updateValue('PAYSRC_VALID',$this->valid);
        }
    }



    /**
     * Configuration form field values
     */
    public function getConfigFieldsValues()
    {
        $keys = ['PAYSRC_TESTNET','PAYSRC_TOKEN','PAYSRC_EXPIRATION','PAYSRC_INLINE'];
        $confs = COnfiguration::getMultiple($keys);
        /*
        foreach($keys as $k) {
            // $confs[$k] = Tools::getValue($k, Configuration::get($k));
            $confs[$k] = Configuration::get($k);
        }*/
        return $confs;
    }






    /**
     * Creates the payment request
     */
    public function createPaymentRequest($amount,$currency,$customer) {
        $usd = $this->toUsd($amount,$currency);
        $rate = $this->apiCall('/price/latest');
        $bch = round( $usd / $rate['price_averages']['USD'], 8);
        $msg = 'Order REFERENCE: '.$this->currentOrderReference;
        $req = [
            'payee_name' => $this->context->shop->name,
            'payer_name' => $customer->firstname.' '.$customer->lastname,
            'payer_email' => $customer->email,
            'coin' => 'BCH',
            'amount' => $bch,
            'usd' => $usd,
            'expires' => "+{$this->expiration} hours",
            'message' => $msg,
            'notify_uri' => _PS_BASE_URL_.'/index.php?fc=module&module=paysrc&controller=notify',
            'redirect_uri' => _PS_BASE_URL_.'/index.php?fc=module&module=paysrc&controller=redirect&id_order='.$this->currentOrder,
            // 'redirect_uri' => _PS_BASE_URL_.'/index.php?controller=order-detail&id_order='.$this->currentOrder,
        ];
        $payment = $this->apiCall('/payment/create',$req);
        if(empty($payment)) {
            // TODO: Payment failed
            $order = new Order($this->currentOrder);
            $order_state = Configuration::get('PS_OS_ERROR');
            $history = new OrderHistory;
            $history->id_order = $order->id;
            $history->changeIdOrderState(
                    $order_state,
                    $order->current_state
                );
            $history->save();
            return false;
        }
        $this->payment_request = $payment;
        $paysrcOrder = new PaysrcOrder();
        $paysrcOrder->id_order = $this->currentOrder;
        $paysrcOrder->payment = $payment['id'];
        $paysrcOrder->checked = gmdate('Y-m-d H:i:s');
        $paysrcOrder->expires = date('Y-m-d H:i:s',strtotime($payment['expires']));
        $paysrcOrder->status = 'NEW';
        $paysrcOrder->updated = '0000-00-00 00:00:00';
        $paysrcOrder->amount = $bch;
        $paysrcOrder->balance = 0;
        $paysrcOrder->address = $payment['broker_address'];
        $paysrcOrder->save();
        return $payment;
    }



    /**
     * Gets the payment request
     */
    public function getPaymentRequest() {
        if(!$this->currentOrder)
            return false;
        if(empty($this->payment_request)) {
            $this->payment_request = PaysrcOrder::loadByOrderId($this->currentOrder);
        }
        return $this->payment_request;
    }




    /**
     * Converts the amount to USD
     */
    public function toUsd($val,$currency) {
        if(!is_object($currency)) {
            $currency = Currency::getCurrencyInstance($currency);
        }
        $usd_currency = Currency::getCurrencyInstance($this->usd_currency_id);
        if(!$usd_currency) {
            return false;
        }
        return ( $val / $currency->getConversionRate() * $usd_currency->getConversionRate() );
    }





    /**
     * Updates a payment object
     */
    public function updatePaysrcOrder($paysrcOrder,&$updated=false) {
        if(!is_object($paysrcOrder)) {
            $paysrcOrder = new PaysrcOrder((int)$paysrcOrder);
        }
        if(!Validate::isLoadedObject($paysrcOrder)) {
			return false;
		}
        $data = $this->apiCall('/payment/summary/'.$paysrcOrder->payment);
        if($data['updated'] > $paysrcOrder->updated) {
            $paysrcOrder->updated = gmdate('Y-m-d H:i:s',strtotime($data['updated']));
            $paysrcOrder->status = $data['status'];
            $paysrcOrder->checked = gmdate('Y-m-d H:i:s');
            $paysrcOrder->balance = $data['balance'] + $data['balance_pending'];
            $paysrcOrder->save();
			$updated = $data['updated'];
            $order = new Order($paysrcOrder->id_order);
            // Update the order status
            if($order->current_state == Configuration::get('PS_OS_CANCELED')) {
                // Cancel PaySrc payment
                if($paysrcOrder->status == 'NEW' || $paysrcOrder->status == 'PENDING') {
                    $res = $this->apiCall('/payment/cancel/'.$paysrcOrder->payment);
                    if($res) {
                        $paysrcOrder->status = $res['status'];
                        $paysrcOrder->save();
                    }
                } else {
                    $paysrcOrder->order_canceled = true;
                    $paysrcOrder->save();
                }
            }
            else if($order->current_state == Configuration::get('PAYSRC_OS_WAITING')) {
                switch($paysrcOrder->status) {
                    case 'CREDITED':
                    case 'PAID':
                        $context = Context::getContext();
                        $order_state = Configuration::get('PS_OS_PAYMENT');
                        $history = new OrderHistory;
                        $history->id_order = $order->id;
                        $history->changeIdOrderState(
                                $order_state,
                                $order->current_state
                            );
                        $history->save();
                        $payment = new OrderPayment;
                        $payment->order_reference = $order->reference;
                        $payment->id_currency = $order->id_currency;
                        $payment->amount = $order->total_paid;
                        $payment->payment_method = $this->displayName;
                        $payment->conversion_rate = $order->conversion_rate;
                        $payment->transaction_id = $paysrcOrder->payment;
                        /*
                        $payment->card_number = 
                        $payment->card_brand =
                        $payment->card_expiration = 
                        $payment->card_holder = 
                        */
                        $payment->save();
                        break;
                    case 'EXPIRED':
                    case 'TOEXPIRE':
                        // PS_OS_ERROR
						/*
                        $context = Context::getContext();
                        $order_state = Configuration::get('PS_OS_ERROR');
                        $history = new OrderHistory;
                        $history->id_order = $order->id;
                        $history->changeIdOrderState(
                                $order_state,
                                $order->current_state
                            );
                        $history->save();
                        break;
						*/
                    case 'PAYEE_CANCELED':
                        // PS_OS_CANCELED
                        $context = Context::getContext();
                        $order_state = Configuration::get('PS_OS_CANCELED');
                        $history = new OrderHistory;
                        $history->id_order = $order->id;
                        $history->changeIdOrderState(
                                $order_state,
                                $order->current_state
                            );
                        $history->save();
                        break;
                }
            }
        }
        return $paysrcOrder;
    }
    public function updatePaysrcOrderByPayment($paymentId,&$updated=false) {
        $paysrcOrder = PaysrcOrder::loadByPayment($paymentId);
        if($paysrcOrder) {
            return $this->updatePaysrcOrder($paysrcOrder,$updated);
        }
        return false;
    }
    public function updatePaysrcOrderByOrderId($orderId,&$updated=false) {
        $paysrcOrder = PaysrcOrder::loadByOrderId($orderId);
        if($paysrcOrder) {
            return $this->updatePaysrcOrder($paysrcOrder,$updated);
        }
        return false;
    }




    /**
     * Creates an API call
     * If params passed, assumes POST
     */
    private function apiCall($path,$params=null) {
        $root = static::PAYSRC_API_URI;
        if($this->testnet)
            $root = static::PAYSRC_API_TESTNET_URI;
        if($params) {
            if(is_array($params)) {
                $params = http_build_query($params);
            }
            // Creates the HTTP context for file_get_contents
            $context = [
                'http'=>[
                    'method' => 'POST',
                    'header' => ['Content-type: application/x-www-form-urlencoded'],
                    'content' => $params,
                    'ignore_errors' => true,
                ],
            ];
        } else {
            $context = [
                'http'=>[
                    'ignore_errors' => true,
                ],
            ];
        }
        $token = $this->token;
        if(!empty($token)) {
            $context['http']['header'][] = 'Authorization: Bearer '.$token;
        }
        if(!empty($context['http']['header'])) {
            $context['http']['header'] = implode("\n",$context['http']['header']);
        }
        $contextObj = stream_context_create($context);
        // Calls the API
        $response = file_get_contents($root.$path,false,$contextObj);
        $result = json_decode($response,true);
        if(empty($result)) {
            if(empty($response)) {
                return false;
            }
            return $response;
        }
        // Evaluate error
        if(isset($result['error'])) {
            $this->api_error_code = $result['error']['code'];
            $this->api_error_message = $result['error']['message'];
            return false;
        }
        return $result;
    }



}

