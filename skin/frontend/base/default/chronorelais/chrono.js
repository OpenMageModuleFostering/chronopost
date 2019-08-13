Ajax.Responders.register({
    onComplete: function(req,xhr) {
        var url = req.url;
        if(url.indexOf("onestepcheckout/ajax/save_billing") !== -1) {

            new Ajax.Request(Url_ShippingMethodImage, {
                method: 'post',
                onSuccess: function(transport)    {
                    if(transport.status == 200)    {

                        var data = transport.responseText;

                        var shipment_methods = $$('div.onestepcheckout-shipping-method-block')[0];
                        var shipment_methods_found = false;

                        if(typeof shipment_methods != 'undefined') {
                            shipment_methods_found = true;
                        }

                        if(shipment_methods_found)  {
                            shipment_methods.insert(data);
                        }
                    }
                }
            });
        }
    }
});