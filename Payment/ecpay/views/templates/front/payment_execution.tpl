
{capture name=path}
	{l s='Pay by ECPay Integration Payment' mod='ecpay'}
{/capture}

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if !empty($ecpay_warning)}
	<div class="box cheque-box">
		<p>{$ecpay_warning}</p>
	</div>
	<p class="cart_navigation clearfix" id="cart_navigation">
		<a href="{$link->getPageLink('order', true, NULL, 'step=3')|escape:'html':'UTF-8'}" class="button-exclusive btn btn-default">
			<i class="icon-chevron-left"></i>{l s='Other payment methods' mod='ecpay'}
		</a>
	</p>
{else}
	<form action="{$link->getModuleLink('ecpay', 'payment', [], true)|escape:'html'}" method="post">
		<div class="box cheque-box">
			<p>
				<img src="{$this_path_ecpay}images/ecpay_payment_logo.png" alt="{l s='ECPay' mod='ecpay'}" width="148" height="52" style="float:left;" />
			</p>
			<p>
				{l s='The total amount of your order is ' mod='ecpay'}
				<span id="amount" class="price">{displayPrice price=$total}</span>
				{if $use_taxes == 1}
					{l s='(tax incl.)' mod='ecpay'}
				{/if}
			</p>
			<p>
			{if !empty($payment_methods)}
				{l s='Payment Method : ' mod='ecpay'}
				<select name="payment_type">
					{foreach from=$payment_methods key=payment_name item=payment_description}
						<option value="{$payment_name}">{$payment_description}</option>
					{/foreach}
				</select>
			{else}
				{l s='No available payment methods, please contact with the administrator.' mod='ecpay'}
			{/if}
			</p>
		</div>
		<p class="cart_navigation clearfix" id="cart_navigation">
			{if count($payment_methods) > 0}
				<button type="submit" class="button btn btn-default button-medium">
					<span>{l s='Checkout' mod='ecpay'}<i class="icon-chevron-right right"></i></span>
				</button>
			{/if}
			
			<a href="{$link->getPageLink('order', true, NULL, 'step=3')|escape:'html':'UTF-8'}" class="button-exclusive btn btn-default">
				<i class="icon-chevron-left"></i>{l s='Other payment methods' mod='ecpay'}
			</a>
		</p>
	</form>
{/if}
