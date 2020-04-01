<?php

class EcpayPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {

        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'ecpay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'payment'));
        } else {

            $paymentType = Tools::getValue('payment_type');
            $paymentTypeName = Tools::getValue('payment_type_name');
            
            if(!empty($paymentType)){

                try
                {
                    # Validate the payment type
                    $ecpayPayment = Configuration::get($paymentType);
                    
                    if ($ecpayPayment === 'on') {

                        # Include the ECPay integration class
                        $invokeResult = $this->module->invokeEcpayModule();
                        
                        if (!$invokeResult)
                        {
                            throw new Exception($this->module->l('ECPay module is missing.', 'payment'));
                        }
                        else
                        {
                            # Get the customer object
                            $customer = new Customer($cart->id_customer);
                            if (!Validate::isLoadedObject($customer)) {
                                Tools::redirect('index.php?controller=order&step=1');
                            }

                            # Get the order id
                            $cartId = (int)$cart->id;
                            
                            # Set ECPay parameters
                            $aio = new ECPay_AllInOne();
                            $aio->Send['MerchantTradeNo'] = '';
                            $aio->MerchantID = Configuration::get('ecpay_merchant_id');

                            if ($this->module->isTestMode($aio->MerchantID)) {
                                $serviceUrl = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut';
                                $aio->Send['MerchantTradeNo'] = date('YmdHis');
                            } else {
                                $serviceUrl = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut';
                            }

                            $aio->HashKey = Configuration::get('ecpay_hash_key');
                            $aio->HashIV = Configuration::get('ecpay_hash_iv');
                            $aio->ServiceURL = $serviceUrl;
                            $aio->Send['ReturnURL'] = $this->context->link->getModuleLink('ecpay','response', []);
                            $aio->Send['ClientBackURL'] = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key;
                            $aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');

                            # Get the currency object
                            $currency = $this->context->currency;
                            
                            # Set the product info
                            $orderTotal = $cart->getOrderTotal(true, Cart::BOTH);
                            $aio->Send['TotalAmount'] = $orderTotal;

                            array_push(
                                $aio->Send['Items'],
                                [
                                    'Name' => $this->module->l('A Package Of Online Goods', 'payment'),
                                    'Price' => $aio->Send['TotalAmount'],
                                    'Currency' => $currency->iso_code,
                                    'Quantity' => 1,
                                    'URL' => ''
                                ]
                            );
                            
                            # Set the trade description
                            $aio->Send['TradeDesc'] = 'ecpay_module_prestashop_2_0_200302';
                            
                            # Get the chosen payment
                            $choosePayment = $this->module->getSdkPaymentName($paymentType);
                            $aio->Send['ChoosePayment'] = $choosePayment;

                            # Set the extend information

                            switch ($choosePayment) {

                                case ECPay_PaymentMethod::Credit:
                                    # Do not support UnionPay
                                    $aio->SendExtend['UnionPay'] = false;

                                    $chooseInstallment = Tools::getValue('installment'); // 分期期數
                                    
                                    # Credit installment parameters
                                    if (!empty($chooseInstallment)) {
                                        $aio->SendExtend['CreditInstallment'] = $chooseInstallment;
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
                            $chosenPaymentDesc = $this->module->getPaymentDesc($paymentType);
                            $orderStatusId = $this->module->getOrderStatusID('created');# Preparation in progress
                            $this->module->validateOrder($cartId, $orderStatusId, $orderTotal, $paymentTypeName, $chosenPaymentDesc, [], (int)$currency->id, false, $customer->secure_key);
                            
                            # Get the order id
                            $order = new Order($cartId);
                            $orderId = Order::getOrderByCartId($cartId);
                            $aio->Send['MerchantTradeNo'] .= (int)$orderId;                            
                            
                            # Get the redirect html
                            $aio->CheckOut();
                        }
                    } else {
                        throw new Exception($this->module->l('this payment method is not available.', 'payment')); 
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
