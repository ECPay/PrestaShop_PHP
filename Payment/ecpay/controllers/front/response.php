<?php
	
class EcpayResponseModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	
	public function postProcess()
	{
		# Return URL log
		$this->module->logEcpayMessage('Process ECPay feedback');

		
		
		# Set the default result message
		$resultMessage = '1|OK';
		$cartOrderId = null;
		$order = null;
		try
		{
			# Include the ECPay integration class
			$invoke_result = $this->module->invokeEcpayModule();
			if (!$invoke_result)
			{
				throw new Exception('ECPay module is missing.');
			}
			else
			{
				# Retrieve the checkout result
				$aio = new ECPay_AllInOne();
				$aio->HashKey = Configuration::get('ecpay_hash_key');
				$aio->HashIV = Configuration::get('ecpay_hash_iv');
				$ecpayFeedback = $aio->CheckOutFeedback();
				unset($aio);

				$this->module->logEcpayMessage(print_r($ecpayFeedback, true));

				# Process ECPay feedback
				if (count($ecpayFeedback) < 1) {

					throw new Exception('Get ECPay feedback failed.');

				} else {
                    # Get the cart order id
					$cartOrderId = $this->module->getCartOrderID($ecpayFeedback['MerchantTradeNo'], Configuration::get('ecpay_merchant_id'));
		
					# Get the cart order amount
					$order = new Order( (int)$cartOrderId );
	
					$cartAmount = $this->module->formatOrderTotal($order->total_paid);
					
					# Check the amounts
					$ecpayAmount = $ecpayFeedback['TradeAmt'];

					if ($cartAmount != $ecpayAmount) {
						throw new Exception(sprintf('Order %s amount are not identical.', $cartOrderId));
					
					} else {
						
						# Set the common comments
						$comments = sprintf(
							$this->module->l('Payment Method : %s, Trade Time : %s, ',  'response')
							, $ecpayFeedback['PaymentType']
							, $ecpayFeedback['TradeDate']
						);
						
						# Set the getting code comments
						$returnMessage = $ecpayFeedback['RtnMsg'];
						$returnCode = $ecpayFeedback['RtnCode'];

						$getCodeResultComments = sprintf(
							$this->module->l('Getting Code Result : (%s)%s', 'response')
							, $returnCode
							, $returnMessage
						);
						
						# Set the payment result comments
						$paymentResultComments = sprintf(
							$this->module->l('Payment Result : (%s)%s', 'response')
							, $returnCode
							, $returnMessage
						);
						
						# Get ECPay payment method
						$type_pieces = explode('_', $ecpayFeedback['PaymentType']);
						$ecpay_payment_method = $type_pieces[0];
						
						# Update the order status and comments
						$failMessage 		= sprintf('Order %s Exception.(%s: %s)', $cartOrderId, $returnCode, $returnMessage);
						$createdStatusId 	= $this->module->getOrderStatusID('created');
						$succeededStatusId 	= $this->module->getOrderStatusID('succeeded');
						$orderCurrentStatus = (int)$order->getCurrentState();
						
						switch($ecpay_payment_method)
						{
							case ECPay_PaymentMethod::Credit:
							case ECPay_PaymentMethod::WebATM:

								if ($returnCode != 1 && $returnCode != 800){
									throw new Exception($failMessage);
								} else {
									
									if ($orderCurrentStatus != $createdStatusId) {
										# The order already paid or not in the standard procedure, do nothing
									} else {

										$this->module->setOrderComments($cartOrderId, $paymentResultComments);
										$this->module->updateOrderStatus($cartOrderId, $succeededStatusId, true);
									}
								}

							break;

							case ECPay_PaymentMethod::ATM:

								if ($returnCode != 1  && $returnCode != 2  && $returnCode != 800) {
									throw new Exception($failMessage);
								} else {
									
									if ($returnCode == 2) {

										# Set the getting code result
										$comments .= sprintf(
											$this->module->l('Bank Code : %s, Virtual Account : %s, Payment Deadline : %s, ', 'response')
											, $ecpayFeedback['BankCode']
											, $ecpayFeedback['vAccount']
											, $ecpayFeedback['ExpireDate']
										);
										
										$this->module->setOrderComments($cartOrderId, $comments . $getCodeResultComments);
									
									} else {

										if ($orderCurrentStatus != $createdStatusId) {
											# The order already paid or not in the standard procedure, do nothing
										} else {
											
											$this->module->setOrderComments($cartOrderId, $paymentResultComments);
											$this->module->updateOrderStatus($cartOrderId, $succeededStatusId, true);
										}
									}
								}
							
							break;
							case ECPay_PaymentMethod::CVS:
								
								if ($returnCode != 1 && $returnCode != 800  && $returnCode != 10100073) {
									throw new Exception($failMessage);
								} else {
									
									if ($returnCode == 10100073) {

										$comments .= sprintf(
											$this->module->l('Trade Code : %s, Payment Deadline : %s, ', 'response')
											, $ecpayFeedback['PaymentNo']
											, $ecpayFeedback['ExpireDate']
										);
										$this->module->setOrderComments($cartOrderId, $comments . $getCodeResultComments);
									
									} else {
										
										if ($orderCurrentStatus != $createdStatusId) {
											# The order already paid or not in the standard procedure, do nothing
										} else {
											
											$this->module->setOrderComments($cartOrderId, $paymentResultComments);
											$this->module->updateOrderStatus($cartOrderId, $succeededStatusId, true);
										}
									}
								}
							break;
							case ECPay_PaymentMethod::BARCODE:
								
								if ($returnCode != 1  && $returnCode != 800  && $returnCode != 10100073) {
									throw new Exception($failMessage);
								
								} else {
									
									if ($returnCode == 10100073) {

										$comments .= sprintf(
											$this->module->l('Payment Deadline : %s, BARCODE 1 : %s, BARCODE 2 : %s, BARCODE 3 : %s, ', 'response')
											, $ecpayFeedback['ExpireDate']
											, $ecpayFeedback['Barcode1']
											, $ecpayFeedback['Barcode2']
											, $ecpayFeedback['Barcode3']
										);
										$this->module->setOrderComments($cartOrderId, $comments . $getCodeResultComments);
									}
									else
									{
										if ($orderCurrentStatus != $createdStatusId) {
											# The order already paid or not in the standard procedure, do nothing
										} else {

											$this->module->setOrderComments($cartOrderId, $paymentResultComments);
											$this->module->updateOrderStatus($cartOrderId, $succeededStatusId, true);
										}
									}
								}
							break;
							default:
								throw new Exception(sprintf('Order %s, payment method is invalid.', $cartOrderId));
							break;
						}
					}
				}
			}
		}
		catch(Exception $e)
		{
			$error = $e->getMessage();
			if (!empty($order))
			{
				$failed_status_id = $this->module->getOrderStatusID('failed');
				$comments = sprintf($this->module->l('Paid Failed, Error : %s', 'response'), $error);
				$this->module->setOrderComments($cartOrderId, $comments);
				$this->module->updateOrderStatus($cartOrderId, $failed_status_id, true);
			}
			
			# Set the failure result
			$resultMessage = '0|' . $error;
		}
		
		# Return URL log
		$this->module->logEcpayMessage('Order ' . $cartOrderId . ' process result : ' . $resultMessage, true);
		
		echo $resultMessage;
		exit;
	}
}
