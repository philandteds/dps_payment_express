<div class="warning">
	<h3>{'Transaction is failed.'|i18n( 'design/standard/shop' )}</h3>
	{'DPS Response:'|i18n( 'design/standard/shop' )} {$transaction.response_text}
    {def $has_DPS = cond( ezini('ExtensionSettings','ActiveAccessExtensions')|contains('dps_payment_express'), true(), true(),false() )}
    {def $has_multisafepay = cond( ezini('ExtensionSettings','ActiveAccessExtensions')|contains('multisafepay'), true(), true(),false() )}

    <form method="post" action={cond( $has_multisafepay, "xrowecommerce/set_payment_gateway/multisafepay", true(), "xrowecommerce/confirmorder" )|ezurl} id="confirmorder" name="confirmorder">
    {include uri="design:shop/payment_buttons.tpl"}
    </form>

</div>
