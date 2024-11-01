function clean(str) { return str.replace(/[^\d.-]/g, ''); }

jQuery(document).ready(function() {

    jQuery('form.checkout').on('checkout_place_order_transactium_woocommerce_addon', transactiumFormHandler);

    // Pay Page Form
    jQuery('form#order_review').on('submit', transactiumFormHandler);

    // Add Payment Method Form
    jQuery('form#add_payment_method').on('submit', transactiumFormHandler);
	 
	
});
 
var hack = false;

function transactiumFormHandler(event) {

	//(if a payment method is selected and "Use a payment method is checked") OR (if page is "Add New Payment Method" is user settings)
    if ( 
		 jQuery('#payment_method_transactium_woocommerce_addon').is(':checked') 
		 && ('new' === jQuery( 'input[name="wc-transactium_woocommerce_addon-payment-token"]:checked' ).val() 
		  || undefined === jQuery( 'input[name="wc-transactium_woocommerce_addon-payment-token"]:checked' ).val()) 
		 || ('1' === jQuery('#woocommerce_add_payment_method').val())) {

        //if (jQuery('#transactium_woocommerce_addon-token').val().length === 0) {
		if (!hack) 
		{
			hack = true;
			//overrides default form.submit();
            event.preventDefault();
			event.stopImmediatePropagation();

			//obtain whatever form is available
            form = jQuery('form.checkout, form#order_review, form#add_payment_method');
			
            function onSuccessfulTokenize(data) {
                //handle successful payments here
                //alert("success");

                //store the token in the form to be submitted
                jQuery('#transactium_woocommerce_addon-token').val(data.token);
				
				//clear card number, card expiry and cvc
				//so that they are removed from form submit (for security)
				jQuery('#transactium_woocommerce_addon-card-number').val('');
                jQuery('#transactium_woocommerce_addon-card-expiry').val('');
                jQuery('#transactium_woocommerce_addon-card-cvc').val('');

                form.submit();
				
				hack = false;
				
				//clear token after form submit
				//setTimeout(function(){jQuery('#transactium_woocommerce_addon-token').val('');},1000);
            }

            function onFailedTokenize(jqXHR, textStatus, errorThrown) {
                //handle failed payments here
                //deleteToken();
                alert('transaction failed: ' + errorThrown);
            }

			function invalidateField(field, invalid) { //mark invalid fields, restore valid fields
				
				if (invalid) {
					(jQuery(field).parent()).removeClass("woocommerce-validated").addClass("woocommerce-invalid"); 
				}
				else 
				{
					(jQuery(field).parent()).removeClass("woocommerce-invalid").addClass("woocommerce-validated"); 
				}
				
			}
			
            function validateForm() {
			
				//recognise card type & check card number, expiry and cvc
				var cardType = jQuery.payment.cardType(jQuery('#transactium_woocommerce_addon-card-number').val());
				var cardnumberValid = jQuery.payment.validateCardNumber(jQuery('#transactium_woocommerce_addon-card-number').val());
				var cardexpiryValid = jQuery.payment.validateCardExpiry(jQuery('#transactium_woocommerce_addon-card-expiry').payment('cardExpiryVal'));
				var cardCVCValid = jQuery.payment.validateCardCVC(jQuery('#transactium_woocommerce_addon-card-cvc').val(), cardType); 
				
				var firstInvalidField = null;
				if (!cardnumberValid) {
					invalidateField('#transactium_woocommerce_addon-card-number', true);
					if (!firstInvalidField) firstInvalidField = "#transactium_woocommerce_addon-card-number";
				} else {
					invalidateField('#transactium_woocommerce_addon-card-number', false);
				}

				if (!cardexpiryValid) {
					invalidateField('#transactium_woocommerce_addon-card-expiry', true);
					if (!firstInvalidField) firstInvalidField = "#transactium_woocommerce_addon-card-expiry";
				} else {
					invalidateField('#transactium_woocommerce_addon-card-expiry', false);
				}
				
				if (!cardCVCValid) {
					invalidateField('#transactium_woocommerce_addon-card-cvc', true);
					if (!firstInvalidField) firstInvalidField = "#transactium_woocommerce_addon-card-cvc";
				} else {
					invalidateField('#transactium_woocommerce_addon-card-cvc', false);
				}
				
				if (firstInvalidField) {
					jQuery(firstInvalidField).focus();
					jQuery("#add_payment_method").unblock();
					hack = false;
					return false; //validation fail
				}
				
                return true; //validation success
            }

            if (validateForm()) {
                EZPAY.API.getOrCreateCurtain();

                var cardnumber = clean(jQuery('#transactium_woocommerce_addon-card-number').val());
                var cvv = clean(jQuery('#transactium_woocommerce_addon-card-cvc').val());
                var expiry = clean(jQuery('#transactium_woocommerce_addon-card-expiry').val());
                var amount = transactium_params.amount; 	//parameters passed from 
				var currency = transactium_params.currency; //PHP's init_checkout()

				//format date for Transactium
				var expiryYYMM;
				
				if (expiry.length == 4) { // eg. 0219
					expiryYYMM = expiry.substring(2) + expiry.substring(0, 2);
				} else if (expiry.length == 6 && expiry.substring(2, 4) == '20') { //eg. 022019
					expiryYYMM = expiry.substring(4, 6) + expiry.substring(0, 2);
				} else {
					invalidateField('#transactium_woocommerce_addon-card-expiry', true);
					jQuery("#transactium_woocommerce_addon-card-expiry").focus();
					jQuery("#add_payment_method").unblock();
					EZPAY.API.getOrCreateCurtain().remove();
					hack = false;
					return false;
				}

				//Fill in fields required by Transactium EZPAY
                jQuery('input[data-ezpay-token-type="card"]').val(cardnumber);
                jQuery('input[data-ezpay-token-type="cvv"]').val(cvv);
                jQuery('input[data-ezpay-token-type="expiry"]').val(expiryYYMM);
                jQuery('input[data-ezpay-token-type="amount-decimal"]').val(amount);
				jQuery('input[data-ezpay-token-type="currency"]').val(currency);
				
				//Delete old token
				EZPAY.API.deleteToken().done(function(){
				
				//Try get token
                EZPAY.API.tokenizeForm(form)
                    .done(onSuccessfulTokenize)
                    .fail(onFailedTokenize)
                    .always(function() {
                        EZPAY.API.getOrCreateCurtain().remove()
                    });
				});

            }

            return false;

        }

    } else if (!jQuery('#transactium_woocommerce_addon-card-cvc').attr('name')) {
		//make field as sendable to server in case of pre-defined payment methods
		
		var cardCVCValid = jQuery.payment.validateCardCVC(jQuery('#transactium_woocommerce_addon-card-cvc').val()); //validate other cards' cvc number
		if (!cardCVCValid)
		{
			event.preventDefault();
			event.stopImmediatePropagation();
			jQuery('#transactium_woocommerce_addon-card-cvc').focus();
			return false;
		}
		
		jQuery('#transactium_woocommerce_addon-card-cvc').attr('name', 'transactium_woocommerce_addon-card-cvc'); 
	}

}