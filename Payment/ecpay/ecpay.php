<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ecpay extends PaymentModule
{
    // protected $_html = '';
    // protected $_postErrors = array();

    // public $details;
    // public $owner;
    // public $address;
    // public $extra_mail_vars;

    # Custom variables: ECPay log
    private $ecpayLog = [];

    public function __construct()
    {
        $this->name = 'ecpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.2002260';
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];

        $this->author = 'ECPay';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('ECPay Integration Payment');
        $this->description = 'https://www.ecpay.com.tw/';
        $this->confirmUninstall = $this->l('Do you want to uninstall ECPay payment module?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        $this->ecpayMerchantIdDefult = '2000132';
        $this->ecpayHashKeyDefult = '5294y06JbISpM5x9';
        $this->ecpayHashIvDefult = 'v77hoKGq4kWxNNIS';

        # Custom variables: ECPay parameters
        $this->ecpayParams = [
            'ecpay_merchant_id',
            'ecpay_hash_key',
            'ecpay_hash_iv',
            'ecpay_status_create_order',
            'ecpay_status_pay_success',
            'ecpay_status_pay_fail',
            'ecpay_payment_credit',
            'ecpay_payment_credit_03',
            'ecpay_payment_credit_06',
            'ecpay_payment_credit_12',
            'ecpay_payment_credit_18',
            'ecpay_payment_credit_24',
            'ecpay_payment_webatm',
            'ecpay_payment_atm',
            'ecpay_payment_cvs',
            'ecpay_payment_barcode',

        ];
        
        # Custom variables: ECPay log
        $this->ecpayLog = _PS_MODULE_DIR_ . $this->name . '/log/return_url.log';
        if (!file_exists(dirname($this->ecpayLog)))
        {
            mkdir(dirname($this->ecpayLog));
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }

        Configuration::updateValue('ecpay_merchant_id', $this->ecpayMerchantIdDefult);
        Configuration::updateValue('ecpay_hash_key', $this->ecpayHashKeyDefult);
        Configuration::updateValue('ecpay_hash_iv', $this->ecpayHashIvDefult);

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() || !$this->cleanEcpayConfig()) {
            return false;
        } else {
            return true;
        }
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = $this->getPaymentOption() ;
        return $payment_options;
    }

    // P
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
    *  判斷付款方式是否啟用
    * @access   public
    * @return   array
    */
    public function getPaymentOption()
    {
        $paymentOptions = [] ;

        $ecpayPayment = Configuration::getMultiple($this->ecpayParams);

        // 判斷是否啟用付款方式
        foreach($ecpayPayment as $key => $value){

            if($key == 'ecpay_payment_credit' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayCreditPaymentOption() ;
            }
            if($key == 'ecpay_payment_credit_03' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayCredit03PaymentOption() ;
            }
            if($key == 'ecpay_payment_credit_06' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayCredit06PaymentOption() ;
            }
            if($key == 'ecpay_payment_credit_12' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayCredit12PaymentOption() ;
            }
            if($key == 'ecpay_payment_credit_18' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayCredit18PaymentOption() ;
            }
            if($key == 'ecpay_payment_credit_24' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayCredit24PaymentOption() ;
            }
            if($key == 'ecpay_payment_webatm' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayWebatmPaymentOption() ;
            }
            if($key == 'ecpay_payment_atm' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayAtmPaymentOption() ;
            }
            if($key == 'ecpay_payment_cvs' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayCvsPaymentOption() ;
            }
            if($key == 'ecpay_payment_barcode' && $value == 'on'){
                $paymentOptions[] = $this->getEcpayBarcodePaymentOption() ;
            }
        }

        return $paymentOptions ;
    }

    /**
    *  信用卡付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayCreditPaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('Credit');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                            'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_credit',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'credit_type' => [
                                'name' =>'installment',
                                'type' =>'hidden',
                                'value' =>'0',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    /**
    *  信用卡3分期付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayCredit03PaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('Credit_03');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                             'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_credit_03',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'credit_type' => [
                                'name' =>'installment',
                                'type' =>'hidden',
                                'value' =>'3',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;       
    }

   /**
    *  信用卡6分期付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayCredit06PaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('Credit_06');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                            'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_credit_06',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'credit_type' => [
                                'name' =>'installment',
                                'type' =>'hidden',
                                'value' =>'6',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    /**
    *  信用卡12分期付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayCredit12PaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('Credit_12');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                            'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_credit_12',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'credit_type' => [
                                'name' =>'installment',
                                'type' =>'hidden',
                                'value' =>'12',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    /**
    *  信用卡18分期付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayCredit18PaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('Credit_18');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                            'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_credit_18',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'credit_type' => [
                                'name' =>'installment',
                                'type' =>'hidden',
                                'value' =>'18',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    /**
    *  信用卡24分期付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayCredit24PaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('Credit_24');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                            'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_credit_24',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'credit_type' => [
                                'name' =>'installment',
                                'type' =>'hidden',
                                'value' =>'24',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    /**
    *  WebAtm付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayWebatmPaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('WebATM');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                            'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_webatm',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'token' => [
                                'name' =>'token',
                                'type' =>'hidden',
                                'value' =>'12345689',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    /**
    *  Atm付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayAtmPaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('ATM');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                            'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_atm',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'token' => [
                                'name' =>'token',
                                'type' =>'hidden',
                                'value' =>'12345689',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    /**
    *  Cvs付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayCvsPaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('CVS');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                            'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_cvs',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'token' => [
                                'name' =>'token',
                                'type' =>'hidden',
                                'value' =>'12345689',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    /**
    *  Barcode付款方式
    * @access   public
    * @return   object
    */
    public function getEcpayBarcodePaymentOption()
    {
        $paymentDesc = $this->getPaymentDesc('BARCODE');

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($paymentDesc)
                       ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                       ->setInputs([
                            'payment_type' => [
                                'name' =>'payment_type',
                                'type' =>'hidden',
                                'value' =>'ecpay_payment_barcode',
                            ],
                            'payment_type_name' => [
                                'name' =>'payment_type_name',
                                'type' =>'hidden',
                                'value' =>$paymentDesc,
                            ],
                            'token' => [
                                'name' =>'token',
                                'type' =>'hidden',
                                'value' =>'12345689',
                            ],
                        ]) 
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $externalOption;
    }

    public function getContent()
    {
        $output = null;
        
        # Update the settings
        if (Tools::isSubmit('submit'.$this->name)) {

            # Validate the POST parameters
            $this->postValidation();
            
            if (!empty($this->postError)){
                # Display the POST error
                $output .= $this->displayError($this->postError);
            } else {
                $output .= $this->postProcess();
            }
        }
        
        return $output.$this->displayForm();
    }

    /**
    *  檢查必填項目
    * @access   private
    */
    private function postValidation()
    {
        $requiredFields = [
            'ecpay_merchant_id' => $this->l('Merchant ID'),
            'ecpay_hash_key'    => $this->l('Hash Key'),
            'ecpay_hash_iv'     => $this->l('Hash IV'),
        ];
        
        foreach ($requiredFields as $fieldName => $fieldDesc) 
        {
            $tmp_field_value = Tools::getValue($fieldName);
            if (empty($tmp_field_value))
            {
                $this->postError = $fieldDesc . $this->l(' is required');
                return ;
            }
        }
    }

    /**
    *  更新參數欄位
    * @access   private
    */
    private function postProcess()
    {
        # Update ECPay parameters
        foreach ($this->ecpayParams as $paramName)
        {
            if (!Configuration::updateValue($paramName, Tools::getValue($paramName))) {
                return $this->displayError($paramName . ' ' . $this->l('updated failed'));
            }
        }
        
        return $this->displayConfirmation($this->l('Settings updated'));
    }

    /**
    *  顯示表單
    * @access   private
    */
    private function displayForm()
    {
        # Set the payment methods options
        $paymentMethods = [];
        $paymentsDesc = $this->getPaymentsDesc();
        foreach ($paymentsDesc as $paymentName => $paymentDesc)
        {
            array_push( $paymentMethods, ['id_option' => strtolower($paymentName), 'name' => $paymentDesc] );
        }

        # Set Order Status
        $orderStatusMethods = [];
        $orderStates = OrderState::getOrderStates($this->context->language->id);
        foreach ($orderStates as $key => $value)
        {
            $orderStatusMethods[$value['id_order_state']]  = [
                'id_option' => $value['id_order_state'],
                'name' => $value['name'],
            ];
        }
        ksort($orderStatusMethods);

        # Set the configurations for generating a setting form
        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('ECPay configuration'),
            ],
            'input' => [
                [
                    'type'      => 'text',
                    'label'     => $this->l('Merchant ID'),
                    'name'      => 'ecpay_merchant_id',
                    'required'  => true,
                ],
                [
                    'type'      => 'text',
                    'label'     => $this->l('Hash Key'),
                    'name'      => 'ecpay_hash_key',
                    'required'  => true,
                ],
                [
                    'type'      => 'text',
                    'label'     => $this->l('Hash IV'),
                    'name'      => 'ecpay_hash_iv',
                    'required'  => true,
                ],
                [
                    'type'      => 'checkbox',
                    'label'     => $this->l('Payment Methods'),
                    'name'      => 'ecpay_payment',
                    'values'    => [

                        'query' => $paymentMethods,
                        'name'  => 'name',
                        'id'    => 'id_option',
                    ]
                ],
                [
                    'type'      => 'select',
                    'label'     => $this->l('Create Order Status'),
                    'name'      => 'ecpay_status_create_order',
                    'required'  => true,
                    'options'    => [

                        'query' => $orderStatusMethods,
                        'name'  => 'name',
                        'id'    => 'id_option',
                    ]
                ],
                [
                    'type'      => 'select',
                    'label'     => $this->l('Pay Success Status'),
                    'name'      => 'ecpay_status_pay_success',
                    'required'  => true,
                    'options'    => [

                        'query' => $orderStatusMethods,
                        'name'  => 'name',
                        'id'    => 'id_option',
                    ]
                ],
                [
                    'type'      => 'select',
                    'label'     => $this->l('Pay Fail Status'),
                    'name'      => 'ecpay_status_pay_fail',
                    'required'  => true,
                    'options'    => [

                        'query' => $orderStatusMethods,
                        'name'  => 'name',
                        'id'    => 'id_option',
                    ]
                ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
                'name'  => 'submit'.$this->name,
            ]            
        ];
        
        $helper = new HelperForm();
        
        # Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        
        # Get the default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        
        # Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        
        # Load the current settings
        foreach ($this->ecpayParams as $paramName)
        {
            $helper->fields_value[$paramName] = Configuration::get($paramName);
        }
     
        return $helper->generateForm($fieldsForm);
    }

    /**
    *  取得欄位描述
    * @access   public
    * @return   array
    */
    public function getPaymentsDesc()
    {
        $paymentsDesc = [
            'Credit'    => $this->l('Credit'),
            'Credit_03' => $this->l('Credit Card(03 Installments)'),
            'Credit_06' => $this->l('Credit Card(06 Installments)'),
            'Credit_12' => $this->l('Credit Card(12 Installments)'),
            'Credit_18' => $this->l('Credit Card(18 Installments)'),
            'Credit_24' => $this->l('Credit Card(24 Installments)'),
            'WebATM'    => $this->l('WebATM'),
            'ATM'       => $this->l('ATM'),
            'CVS'       => $this->l('CVS'),
            'BARCODE'   => $this->l('BARCODE'),
        ];
        
        return $paymentsDesc;
    }

    /**
    *  取得特定付款方式欄位描述
    * @access   public
    * @return   array
    */
    public function getPaymentDesc($paymentName)
    {
        $paymentsDesc = $this->getPaymentsDesc();
        
        if (!isset($paymentsDesc[$paymentName])) {
            return '';
        } else {
            return $paymentsDesc[$paymentName];
        }
    }

    /**
    *  驗證SDK是否載入
    * @access   public
    * @return   bool
    */
    public function invokeEcpayModule()
    {
        if (!class_exists('ECPay_AllInOne', false)) {
            if (!include(_PS_MODULE_DIR_ . $this->name . '/sdk/ECPay.Payment.Integration.php')) {
                return false;
            }
        }
        
        return true;
    }

    /**
    *  設定欄位對應SDK付款方式
    * @access   public
    * @return   array
    */
    public function getSdkPaymentsName()
    {
        $paymentsNameSdk = [
            'ecpay_payment_credit'    => 'Credit',
            'ecpay_payment_credit_03' => 'Credit',
            'ecpay_payment_credit_06' => 'Credit',
            'ecpay_payment_credit_12' => 'Credit',
            'ecpay_payment_credit_18' => 'Credit',
            'ecpay_payment_credit_24' => 'Credit',
            'ecpay_payment_webatm'    => 'WebATM',
            'ecpay_payment_atm'       => 'ATM',
            'ecpay_payment_cvs'       => 'CVS',
            'ecpay_payment_barcode'   => 'BARCODE',
        ];

        return $paymentsNameSdk;
    }

    /**
    *  取得欄位對應SDK付款方式
    * @access   public
    * @return   array
    */
    public function getSdkPaymentName($paymentName)
    {
        $paymentsNameSdk = $this->getSdkPaymentsName();
        
        if (!isset($paymentsNameSdk[$paymentName])) {
            return '';
        } else {
            return $paymentsNameSdk[$paymentName];
        }
    }

    /**
    *  判斷是否為測試帳號
    * @access   public
    * @return   bool
    */
    public function isTestMode($ecpayMerchantId)
    {
        if ($ecpayMerchantId == '2000132' || $ecpayMerchantId == '2000214') {
            return true;
        } else {
            return false;
        }
    }

    /**
    *  取得訂單狀態
    * @access   public
    * @return   integer
    */
    public function getOrderStatusID($statusName)
    {
        $orderStatus = [
            'created'   => (int) Configuration::get('ecpay_status_create_order'),
            'succeeded' => (int) Configuration::get('ecpay_status_pay_success'),
            'failed'    => (int) Configuration::get('ecpay_status_pay_fail'),
        ];
        
        return $orderStatus[$statusName];
    }

    /**
    *  LOG記錄
    * @access   public
    * @return   integer
    */
    public function logEcpayMessage($message)
    {
        $logPath  = $this->ecpayLog ; // LOG路徑
        $log = '+++++++++++++++++++++++++++++++++++++++' . date('Y-m-d H:i:s') . ' ++++++++++++++++++++++++++++++++++++++++++++' . "\n";
        $fp=fopen($logPath, "a+");
        fputs($fp, $log);
        fclose($fp);

        $logFile =  $message  . "\n" ;
        $fp=fopen($logPath, "a+");
        fputs($fp, $logFile);
        fclose($fp);
    }

    /**
    *  依照帳號取訂單編號
    * @access   public
    * @return   integer
    */
    public function getCartOrderID($merchantTradeNo, $ecpayMerchantId)
    {
        $cartOrderId = $merchantTradeNo;
        if ($this->isTestMode($ecpayMerchantId))
        {
            $cartOrderId = substr($merchantTradeNo, 14);
        }
        
        return $cartOrderId;
    }

    /**
    *  更新訂單狀態
    * @access   public
    */
    public function updateOrderStatus($orderId, $statusId, $sendMail = false)
    {
        # Update the order status
        $orderHistory = new OrderHistory();
        $orderHistory->id_order = (int)$orderId;
        $orderHistory->changeIdOrderState((int)$statusId, (int)$orderId);
        
        # Send a mail
        if ($sendMail)
        {
            $orderHistory->addWithemail();
        }
    }

    /**
    *  寫入訂單備註
    * @access   public
    */
    public function setOrderComments($orderId, $comments)
    {
        # Set the order comments 

        $order = new Order( (int)$orderId );

        // Add this message in the customer thread
        $customer_thread = new CustomerThread();
        $customer_thread->id_contact = 0;
        $customer_thread->id_customer = (int) $order->id_customer;
        $customer_thread->id_shop = (int) $this->context->shop->id;
        $customer_thread->id_order = (int) $order->id;
        $customer_thread->id_lang = (int) $this->context->language->id;
        $customer_thread->email = $this->context->customer->email;
        $customer_thread->status = 'open';
        $customer_thread->token = Tools::passwdGen(12);
        $customer_thread->add();

        $customer_message = new CustomerMessage();
        $customer_message->id_customer_thread = $customer_thread->id;
        $customer_message->id_employee = 0;
        $customer_message->message = $comments;
        $customer_message->private = 1;
        $customer_message->add();
    }

    public function formatOrderTotal($orderTotal)
    {
        return intval(round($orderTotal));
    }

    /**
    *  清除資料庫參數
    * @access   private
    * @return   bool
    */
    private function cleanEcpayConfig()
    {
        foreach ($this->ecpayParams as $paramName)
        {
            if (!Configuration::deleteByName($paramName))
            {
                return false;
            }
        }
        
        return true;
    }
}
