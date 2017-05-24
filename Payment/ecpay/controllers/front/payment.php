<?php

class EcpayPaymentModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	public $display_column_left = false;
	public $display_column_right = false;
	public $ecpay_warning = '';
	
	# See FrontController::initContent()
	public function initContent()
	{
		parent::initContent();

		$cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart))
		{
			Tools::redirect('index.php?controller=order');
		}
		
		# Get the available payment methods
		$ecpay_payments = $this->module->getPaymentsDesc();
		$payment_methods = array();
		foreach ($ecpay_payments as $payment_name => $payment_desc)
		{
			if (Configuration::get('ecpay_payment_' . strtolower($payment_name)) == 'on')
			{
				$payment_methods[$payment_name] = $payment_desc;
			}
		}
		
		# Check the product number in the cart
		$cart_product_number = $cart->nbProducts();
		if ($cart_product_number < 1)
		{
			$this->ecpay_warning = $this->module->l('Your shopping cart is empty.', 'payment');
		}
		
		# Format the error message
		if (!empty($this->ecpay_warning))
		{
			$this->ecpay_warning = Tools::displayError($this->ecpay_warning);
		}
		
		# Set PrestaShop Smarty parameters
		$this->context->smarty->assign(array(
			'total' => $cart->getOrderTotal(true, Cart::BOTH)
			, 'isoCode' => $this->context->language->iso_code
			, 'payment_methods' => $payment_methods
			, 'this_path_ecpay' => $this->module->getPathUri()
			, 'this_path' => $this->module->getPathUri()
			,	'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
			, 'ecpay_warning' => $this->ecpay_warning
		));
		
		# Display the template
		$this->setTemplate('payment_execution.tpl');
	}
	
	public function postProcess()
	{
		# Validate the payment module
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
		{
			if ($module['name'] == 'ecpay')
			{
				$authorized = true;
				break;
			}
		}
		
		if (!$authorized)
		{
			$this->ecpay_warning = $this->module->l('This payment module is not available.', 'payment');
		}
		else
		{
			$payment_type = Tools::getValue('payment_type');
			if ($payment_type)
			{
				# Check the cart info
				$cart = $this->context->cart;
				if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
				{
					Tools::redirect(__PS_BASE_URI__.'order.php?step=1');
				}
				
				try
				{
					# Validate the payment type
					$chosen_payment_desc = $this->module->getPaymentDesc($payment_type);
					if (empty($chosen_payment_desc))
					{
						throw new Exception($this->module->l('this payment method is not available.', 'payment'));
					}
					else
					{
						# Include the ECPay integration class
						$invoke_result = $this->module->invokeEcpayModule();
						if (!$invoke_result)
						{
							throw new Exception($this->module->l('ECPay module is missing.', 'payment'));
						}
						else
						{
							# Get the customer object
							$customer = new Customer($this->context->cart->id_customer);
							if (!Validate::isLoadedObject($customer))
							{
								Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
							}
              
							# Get the order id
							$cart_id = (int)$cart->id;
							
							# Set ECPay parameters
							$aio = new ECPay_AllInOne();
							$aio->Send['MerchantTradeNo'] = '';
							$aio->MerchantID = Configuration::get('ecpay_merchant_id');
							if ($this->module->isTestMode($aio->MerchantID))
							{
								$service_url = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut';
								$aio->Send['MerchantTradeNo'] = date('YmdHis');
							} else {
								$service_url = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut';
							}
							$aio->HashKey = Configuration::get('ecpay_hash_key');
							$aio->HashIV = Configuration::get('ecpay_hash_iv');
							$aio->ServiceURL = $service_url;
							$aio->Send['ReturnURL'] = $this->context->link->getModuleLink('ecpay','response', array());
							$aio->Send['ClientBackURL'] = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . '/index.php?controller=order-confirmation&id_cart=' . $cart_id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key;
							$aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
							
							# Get the currency object
							$currency = $this->context->currency;
							
							# Set the product info
							$order_total = $cart->getOrderTotal(true, Cart::BOTH);
							$aio->Send['TotalAmount'] = $this->module->formatOrderTotal($order_total);
							array_push(
								$aio->Send['Items'],
								array(
									'Name' => $this->module->l('A Package Of Online Goods', 'payment'),
									'Price' => $aio->Send['TotalAmount'],
									'Currency' => $currency->iso_code,
									'Quantity' => 1,
									'URL' => ''
								)
							);
							
							# Set the trade description
							$aio->Send['TradeDesc'] = 'ecpay_module_prestashop_1_0_0922';
							
							# Get the chosen payment and installment
							$type_pieces = explode('_', $payment_type);
							$aio->Send['ChoosePayment'] = $type_pieces[0];
							$choose_installment = 0;
							if (isset($type_pieces[1])) {
								$choose_installment = $type_pieces[1];
							}
							
							# Set the extend information
							switch ($aio->Send['ChoosePayment']) {
								case ECPay_PaymentMethod::Credit:
									# Do not support UnionPay
									$aio->SendExtend['UnionPay'] = false;
									
									# Credit installment parameters
									if (!empty($choose_installment)) {
										$aio->SendExtend['CreditInstallment'] = $choose_installment;
										$aio->SendExtend['InstallmentAmount'] = $aio->Send['TotalAmount'];
										$aio->SendExtend['Redeem'] = false;
									}
									break;
								case ECPay_PaymentMethod::WebATM:
									break;
								case ECPay_PaymentMethod::ATM:
									$aio->SendExtend['ExpireDate'] = 3;
									$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
									break;
								case ECPay_PaymentMethod::CVS:
								case ECPay_PaymentMethod::BARCODE:
									$aio->SendExtend['Desc_1'] = '';
									$aio->SendExtend['Desc_2'] = '';
									$aio->SendExtend['Desc_3'] = '';
									$aio->SendExtend['Desc_4'] = '';
									$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
									break;
								default:
									throw new Exception($this->module->l('this payment method is not available.', 'payment'));
									break;
							}
							
							# Create an order
							$order_status_id = $this->module->getOrderStatusID('created');# Preparation in progress
							$this->module->validateOrder($cart_id, $order_status_id, $order_total, $this->module->displayName, $chosen_payment_desc, array(), (int)$currency->id, false, $customer->secure_key);
							
							# Get the order id
							$order = new Order($cart_id);
							$order_id = Order::getOrderByCartId($cart_id);
							$aio->Send['MerchantTradeNo'] .= (int)$order_id;							
							
							# Get the redirect html
							$aio->CheckOut();
						}
					}
				}
				catch(Exception $e)
				{
					$this->ecpay_warning = sprintf($this->module->l('Payment failure, %s', 'payment'), $e->getMessage());
				}
			}
		}
	}
}
