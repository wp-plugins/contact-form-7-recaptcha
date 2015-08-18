function contact_form_7_recaptcha_callback() {
    jQuery('.g-recaptcha-explicit-id').each(function(){
        grecaptcha.render(this.value, {
            'sitekey' : contact_form_7_recaptcha_data.sitekey
        });
    });
};

jQuery(function($){
    
    $('.wpcf7').on('invalid.wpcf7 mailsent.wpcf7', function() {
        var id = $('.g-recaptcha-explicit-id', this).val();
        $('#' + id).html('');
        grecaptcha.render(id, {
            'sitekey' : contact_form_7_recaptcha_data.sitekey
        });
    });
    
});