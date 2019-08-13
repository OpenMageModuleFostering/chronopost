Event.observe(window, 'load', function() {
    if ($('chronorelais_assurance_picto_img'))
    {
        $('chronorelais_assurance_picto_img').observe('click', function() {
            $('chronorelais_assurance_infobulle').show();
        });
        $('chronorelais_assurance_infobulle_header_close').observe('click', function() {
            $('chronorelais_assurance_infobulle').hide();
        });
    }


    configForm.submit = function(url){
        if (typeof varienGlobalEvents != undefined) {
            varienGlobalEvents.fireEvent('formSubmit', this.formId);
        }
        this.errorSections = $H({});
        this.canShowError = true;
        this.submitUrl = url;
        if (this.validator && this.validator.validate()) {
            if ($('carriers_chronopost')) {
                if ($('carriers_chronopost_active').value == 1 && $('carriers_chronopostc18_active').value == 1) {
                    alert('Vous ne pouvez pas activer Chronopost C13 et Chronopost C18 en même temps');
                    return false;
                }
            }
            if (this.validationUrl) {
                this._validate();
            }
            else {
                this._submit();
            }
            return true;
        }
        return false;
    }

});