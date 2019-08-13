var gmarkers = [];
var map;
var homeaddress = "";
var addressrelais = [];
var relaylatan = [];
var next_pt = 0;
var hidehomeicon = false;
var currentInfoWindow;

function loadMyPoint(i) {
	google.maps.event.trigger(gmarkers[i], "click");
}

var bounds = new google.maps.LatLngBounds();
function loadRelayMap(address, relaisArray, nextpt, mapid, nbPointRelai) {
  var geo = new google.maps.Geocoder();

  var myOptions = {
    zoom: 5,
    center: new google.maps.LatLng(47.37285025362682, 2.4172996312499784),
    mapTypeId: google.maps.MapTypeId.ROADMAP
  }
  map = new google.maps.Map(document.getElementById(mapid), myOptions);

  var blueIcon = new google.maps.MarkerImage(Picto_Chrono_Relais);

  var homeIcon = new google.maps.MarkerImage(Home_Chrono_Icon);

  if(!homeaddress && !hidehomeicon) {
    var ship_address = getShipAddress(); //get shipping address to set home address
    homeaddress = relaisArray.codePostal + " " + relaisArray.localite;
    if(ship_address) { homeaddress = ship_address+" "+homeaddress; }

    geo.geocode({'address': homeaddress}, function(results, status) {
      if (status == google.maps.GeocoderStatus.OK) {
        var point = results[0].geometry.location;
        var marker = new google.maps.Marker({
          position: point,
          map: map,
          title: 'home',
          icon: homeIcon
        });
        map.setCenter(point, 11);
      }
    });
  }

  function createTabbedMarker(point, relaisArray) {
    addressrelais.push(relaisArray);
    var label = nextpt;
    var relaypoint_id = relaisArray.identifiantChronopostPointA2PAS;
    var marker = new google.maps.Marker({map: map, position: point, title:relaisArray.nomEnseigne, icon:blueIcon});

    var infowindow;
    if (window.innerWidth < 700) {
      infowindow = new google.maps.InfoWindow({
        content: '<div style=""><div style="float: left;"><h2>Infos</h2>'+getMarkerInfoContent(relaisArray)+'</div><div style="margin-left: 10px; padding-left: 10px; border-left: 1px solid #000; float: left;"><h2>Horaires</h2><div style="">'+getHorairesTab(relaisArray, true)+'</div></div></div>'
      });
    } else {
      infowindow = new google.maps.InfoWindow({
        content: '<div style="width: 400px;"><div style="width: 190px; float: left;"><h2>Infos</h2>'+getMarkerInfoContent(relaisArray) + getActionsForm(addressrelais.length)+'</div><div style="margin-left: 10px; padding-left: 10px; border-left: 1px solid #000; float: left;"><h2>Horaires</h2><div style="width: 189px">'+getHorairesTab(relaisArray, true)+'</div></div></div>'
      });
    }
    google.maps.event.addListener(marker, 'click', function() {
      if(document.getElementById('s_method_chronorelais_'+relaisArray.identifiantChronopostPointA2PAS))
        document.getElementById('s_method_chronorelais_'+relaisArray.identifiantChronopostPointA2PAS).checked = true;

      if (currentInfoWindow) {
        currentInfoWindow.close();
      }
      infowindow.open(map,marker);
      currentInfoWindow = infowindow;

      // Compat Idev_OneStepCheckout
      if($('onestepcheckout-form') != undefined) { /* sauvegarde en ajax du point relay choisi */
        saveRelayPoint();
      }

    });

    gmarkers[relaypoint_id] = marker;
    return marker;
  }

  function saveRelayPoint() {
    shippingMethodChrono.save();
  }

  function showAddress(address, relaisArray) {
    var search = address;
    // ====== Perform the Geocoding ======
    geo.geocode({'address': search}, function(results, status)
    {
      // If that was successful
      if (status == google.maps.GeocoderStatus.OK) {
        // Loop through the results, placing markers
        //for (var i=0; i<result.Placemark.length; i++) {
        for (var i=0; i<1; i++) {
          var p = results[i].geometry.location;
          relaylatan.push(p);
          var marker = createTabbedMarker(p, relaisArray);
          // ==== Each time a point is found, extent the bounds ato include it =====
          bounds.extend(p);
        }
        // centre the map on the first result
        //!homeaddress && hidehomeicon &&
        if(nextpt==nbPointRelai) {
          var p = results[0].geometry.location;
          // ===== determine the zoom level from the bounds =====
          map.fitBounds(bounds);
          // ===== determine the centre from the bounds ======
          map.setCenter(bounds.getCenter());
        }
      }
      // ====== Decode the error status ======
      else {
        var reason = "Code " + status;
        /*
        if (reasons[status]) {
          reason = reasons[status]
        }
         */
        alert('Could not find "' + search + '" ' + reason);
      }
    });
  }
  showAddress(address, relaisArray);
} // end of loadRelayMap function

function addEvent( obj, type, fn ) {
  if ( obj.attachEvent ) {
    obj["e"+type+fn] = fn;
    obj[type+fn] = function() { obj["e"+type+fn]( window.event ) };
    obj.attachEvent( "on"+type, obj[type+fn] );
  }
  else{
    obj.addEventListener( type, fn, false );
  }
}

function getMarkerInfoContent(relaisArray){
	var icoPath = Picto_Chrono_Relais;
	var content="<div class=\"sw-map-adresse-wrp\" style=\"background-image: url("+ icoPath +"); background-repeat: no-repeat;padding-left:50px;\">"
    + "<h2>"+relaisArray.nomEnseigne+"</h2>"
    + "<div class=\"sw-map-adresse\">"
    + parseAdresse(relaisArray)
    + relaisArray.codePostal + " " + relaisArray.localite
    + "</div></div>";
	return content;
}
function getActionsForm(label)
{
	var html = '<div class="sw-map-tools"><a href="javascript:printPage('+label+')">Imprimer</a>'
    + '</div>';
	return html;
}

function getHorairesTab(anArray, highlight)
{
	var userAgent = navigator.userAgent.toLowerCase();
	var msie = /msie/.test( userAgent ) && !/opera/.test( userAgent );

	var rs = "" ;
	rs =  "<table id=\"sw-table-horaire\" class=\"sw-table\"";
	if(msie) {
		rs +=  " style=\"width:auto;\"";
	}
	rs +=  ">"
		+ "<tr><td>Lun:</td>"+ parseHorairesOuverture(anArray.horairesOuvertureLundi, 1, highlight) +"</tr>"
		+ "<tr><td>Mar:</td>"+ parseHorairesOuverture(anArray.horairesOuvertureMardi, 2, highlight) +"</tr>"
		+ "<tr><td>Mer:</td>"+ parseHorairesOuverture(anArray.horairesOuvertureMercredi, 3, highlight) +"</tr>"
		+ "<tr><td>Jeu:</td>"+ parseHorairesOuverture(anArray.horairesOuvertureJeudi, 4, highlight) +"</tr>"
		+ "<tr><td>Ven:</td>"+ parseHorairesOuverture(anArray.horairesOuvertureVendredi, 5, highlight) +"</tr>"
		+ "<tr><td>Sam:</td>"+ parseHorairesOuverture(anArray.horairesOuvertureSamedi, 6, highlight) +"</tr>"
		+ "<tr><td>Dim:</td>"+ parseHorairesOuverture(anArray.horairesOuvertureDimanche, 0, highlight) +"</tr>"
		+ "</table>" ;
	return rs ;
}

function parseAdresse(anArray)
{
	var address = anArray.adresse1 + "<br />" ;
	if (anArray.adresse2)
		address += anArray.adresse2 + "<br />" ;
	if (anArray.adresse3)
		address += anArray.adresse3 + "<br />" ;
	return address ;
}

function parseHorairesOuverture(value , day, highlight)
{
	var rs = "" ;

	var now = new Date() ;
	var today = now.getDay() ;	// number of day
	var attributedCell = "" ;
	var reg = new RegExp(" ", "g");

	var horaires = value.split(reg) ;

	for (var i=0; i < horaires.length; i++)
	{
		// first define the attributes for the current cell
		/* Aucun jour n'est mis en exergue car on ne sait pas quel sera le jour de livraison
		if ( highlight == true && day == today)
		{
			attributedCell = "style=\"color:red;\"" ;
		}
		else
		{
     */
    attributedCell = "" ;
		/*
		}
     */

		// so, re-format time
		if (horaires[i] == "00:00-00:00")
		{
			horaires[i] = "<td "+attributedCell+">Ferm&eacute;</td>" ;
		}
		else
		{
			horaires[i] = "<td "+attributedCell+">"+horaires[i]+"</td>" ;
		}

		// yeah, concatenates result to the returned value
		rs += horaires[i] ;
	}

	return rs ;
}

function printPage(i)
{
	var  fen=open("","Impression");
	fen.focus();
	var baseURL = "http://www.chronopost.fr/transport-express/webdav/site/chronov4/groups/administrators/public/Chronomaps/" ;
	var latlngpoint = relaylatan[i-1];
	if(latlngpoint) {
		fen.location.href = baseURL
      + "print-result.html?request=print&"
      + btQueryString(addressrelais[i-1], true).replace(/00%3A00-00%3A00/gi, 'Ferm%E9')
      + "&rtype=chronorelais"
      + "&icnname=ac"
      + "&lat=" + latlngpoint.lat()
      + "&lng=" + latlngpoint.lng()
      + "&sw-form-type-point=opt_chrlas"
      + "&is_print_direction=" + false
      + "&from_addr="
      + "&to_addr=";
	}
}

function btQueryString(anArray, needEscape)
{
	var rs = "" ;
  for (key in anArray)
  {
    if (needEscape == true)
    {
			if(anArray[key]) {
				if (rs != "")
					rs += "&"
				rs += key +"=" + escape(anArray[key]) ;
			}
    }
    else
    {
			if(anArray[key]) {
				if (rs != "")
					rs += "_-_"
				rs += key +"=" + anArray[key] ;
			}
    }
  }
	return rs ;
}

function getShipAddress() {
	var ship_address = '';
	if( ($('shipping:same_as_billing') && $('shipping:same_as_billing').checked) || ($('billing:use_for_shipping_yes') && $('billing:use_for_shipping_yes').checked) ) {
		if ($('billing-address-select') && $('billing-address-select').value) {
			var e = $('billing-address-select');
			var address_value = e.options[e.selectedIndex].text;
			var shipping_address = address_value.split(',');
			if(shipping_address[1]) {
				ship_address = 	shipping_address[1];
			}
		} else {
			if($('billing:street1')) { ship_address = $('billing:street1').value; }
			if($('billing:street2')) { ship_address = ship_address+" "+$('billing:street2').value; }
			if($('billing:street3')) { ship_address = ship_address+" "+$('billing:street3').value; }
		}
	} else {
		if ($('shipping-address-select') && $('shipping-address-select').value) {
			var e = $('shipping-address-select');
			var address_value = e.options[e.selectedIndex].text;
			var shipping_address = address_value.split(',');
			if(shipping_address[1]) {
				ship_address = 	shipping_address[1];
			}
		} else {
			if($('shipping:street1')) { ship_address = $('shipping:street1').value; }
			if($('shipping:street2')) { ship_address = ship_address+" "+$('shipping:street2').value; }
			if($('shipping:street3')) { ship_address = ship_address+" "+$('shipping:street3').value; }
		}
	}
	return ship_address;
}

/** Map content end **/


// shipping method
var ShippingMethodChrono = Class.create();
ShippingMethodChrono.prototype = {
  initialize: function(form, saveUrl){
    this.form = form;
    if ($(this.form)) {
      $(this.form).observe('submit', function(event){this.save();Event.stop(event);}.bind(this));
    }
    this.saveUrl = saveUrl;
    this.validator = new Validation(this.form);
    this.onSave = this.nextStep.bindAsEventListener(this);
    this.onComplete = this.resetLoadWaiting.bindAsEventListener(this);
  },

    validate: function() {

        if($('onestepcheckout-form') == undefined) {
            var methods = document.getElementsByName('shipping_method');
            if (methods.length==0) {
                alert(Translator.translate('Your order cannot be completed at this time as there is no shipping methods available for it. Please make neccessary changes in your shipping address.'));
                return false;
            }

            if(!this.validator.validate()) {
                return false;
            }

            for (var i=0; i<methods.length; i++) {
                if (methods[i].checked) {
                    //if (methods[i].value == 'chronorelais_chronorelais') {
                    if (methods[i].value.indexOf('chronorelais') != -1) { /* verifie si un point relai est selectionne */
                        var submethods = document.getElementsByName('shipping_method_chronorelais');
                        if (submethods.length==0) {
                            alert(Translator.translate('Veuillez sélectionner un point relais'));
                            return false;
                        }
                        for (var j=0; j<submethods.length; j++) {
                            if (submethods[j].checked) {
                                return true;
                            }
                        }
                        alert(Translator.translate('Veuillez sélectionner un point relais'));
                        return false;
                    } else if (methods[i].value.indexOf('chronopostsrdv') != -1) { /* vérifie si un creneau horaire est sélectionné */
                        var submethods = document.getElementsByName('shipping_method_chronopostsrdv');
                        if (submethods.length==0) {
                            alert(Translator.translate('Veuillez sélectionner une date'));
                            return false;
                        }
                        for (var j=0; j<submethods.length; j++) {
                            if (submethods[j].checked) {
                                return true;
                            }
                        }
                        alert(Translator.translate('Veuillez sélectionner une date'));
                        return false;
                    } else {
                        return true;
                    }
                }
            }
            alert(Translator.translate('Please specify shipping method.'));
            return false;
        }
        return true;
    },


    getrelais: function(url){
        if (checkout.loadWaiting!=false) return;

        // Compat Idev_OneStepCheckout
        if($('onestepcheckout-form') != undefined) { /* ajout loading */
            $$('.onestepcheckout-shipping-method-block')[0].insert('<div class="loading-ajax"></div>');
        }

        //if (this.validate()) {
        checkout.setLoadWaiting('shipping-method');
        hidehomeicon = false;
        var request = new Ajax.Request(
            url,
            {
                method:'post',
                onComplete: this.onComplete,
                onSuccess: this.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout),
                parameters: Form.serialize(this.form)
            }
        );
        //}
    },

  resetSessionrelais: function(url){
    var request = new Ajax.Request(
      url
    );
    //}
  },

  changePostalCode: function(url){
    if (checkout.loadWaiting!=false) return;
    //if (this.validate()) {
			if(!$('mappostalcode').value) { return false; }
      checkout.setLoadWaiting('shipping-method');
			$('mappostalcodebtn').hide();
			$('postalcode-please-wait').show();
			$('postalcode-please-wait').style.opacity = '0.5';
			hidehomeicon = true;
      var request = new Ajax.Request(
        url,
        {
          method:'post',
          onComplete: this.onComplete,
          onSuccess: this.onSave,
          onFailure: checkout.ajaxFailure.bind(checkout),
          parameters: Form.serialize(this.form)
        }
      );
    //}
  },

  hiderelais: function(url){
		if($$('.chronorelais-list')) {
			$$('.chronorelais-list').each(function(element) {
          element.innerHTML = "";
      });
		}
  },

    getPlanning: function(url){
        if (checkout.loadWaiting!=false) return;

        // Compat Idev_OneStepCheckout
        if($('onestepcheckout-form') != undefined) { /* ajout loading */
            $$('.onestepcheckout-shipping-method-block')[0].insert('<div class="loading-ajax"></div>');
        }

        //if (this.validate()) {
        checkout.setLoadWaiting('shipping-method');
        hidehomeicon = false;
        var request = new Ajax.Request(
            url,
            {
                method:'post',
                onComplete: this.onComplete,
                onSuccess: this.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout),
                parameters: Form.serialize(this.form)
            }
        );
        //}
    },

  save: function(){

    if (checkout.loadWaiting!=false) return;
    if (this.validate()) {
      checkout.setLoadWaiting('shipping-method');
      var request = new Ajax.Request(
      this.saveUrl,
      {
        method:'post',
        onComplete: this.onComplete,
        onSuccess: this.onSave,
        onFailure: checkout.ajaxFailure.bind(checkout),
        parameters: Form.serialize(this.form)
      }
    );
    }
  },

  resetLoadWaiting: function(transport){
    checkout.setLoadWaiting(false);
  },

  nextStep: function(transport){
    if (transport && transport.responseText){
      try{
        response = eval('(' + transport.responseText + ')');
      }
      catch (e) {
        response = {};
      }
    }

    // Compat Idev_OneStepCheckout
    if($('onestepcheckout-form') != undefined && $$('.onestepcheckout-shipping-method-block .loading-ajax')[0] != undefined) { /* ajout loading */
      $$('.onestepcheckout-shipping-method-block .loading-ajax')[0].remove();
    }

    if (response.error) {
      alert(response.message);
			if($('mappostalcodebtn')) { $('mappostalcodebtn').show(); }
			if($('postalcode-please-wait')) { $('postalcode-please-wait').hide(); }
      return false;
    }
	   if (response.update_section) {
      if($('checkout-'+response.update_section.name+'-load') == undefined) {
        var methodToCreate = response.update_section.name;
        //shipping-method-chronorelais_chronorelais
        var methodSelect = methodToCreate.replace('shipping-method-','');
        var input = $$('input[name=shipping_method][value='+methodSelect+']')[0];
        var parentNode = input.up();
        parentNode.insert('<div id="checkout-shipping-method-'+methodSelect+'-load" class="chronorelais-list"></div>');

      }

      $('checkout-'+response.update_section.name+'-load').update(response.update_section.html);
      response.update_section.html.evalScripts();

			if(response.relaypoints) {
				if(response.relaypoints.length>0) {
					var relayaddress = "";
					gmarkers = [];
					addressrelais = [];
					relaylatan = [];
					homeaddress = "";
					next_pt = 0;
					bounds = new google.maps.LatLngBounds();
					for(var s=0; s<response.relaypoints.length; s++) {
						next_pt = s+1;
						relayaddress = response.relaypoints[s].adresse1;
						if(response.relaypoints[s].codePostal)
							relayaddress += " "+response.relaypoints[s].codePostal;
						if(response.relaypoints[s].localite)
							relayaddress += " "+response.relaypoints[s].localite;
						loadRelayMap(relayaddress, response.relaypoints[s], next_pt, "chronomap",response.relaypoints.length);
					}
                    return;
				}
			} else if(response.planning) {
                return;
            }
    }

    payment.initWhatIsCvvListeners();

    if (response.goto_section) {
        checkout.gotoSection(response.goto_section,true);
        checkout.reloadProgressBlock();
        return;
    }

    if (response.payment_methods_html) {
        $('checkout-payment-method-load').update(response.payment_methods_html);
    }

    checkout.setShippingMethod();
  }
}

// shipping method
var multiindex = "";
var MultiShippingMethod = Class.create();
MultiShippingMethod.prototype = {
  initialize: function(){
    this.loadWaiting = false;
    this.onComplete = this.complete.bindAsEventListener(this);
  },

  getrelais: function(url, index, home){
    if (this.loadWaiting!=false) return;
    this.loadWaiting = true;
    homeaddress = home;
    hidehomeicon = false;
    multiindex = "_" + index;
    var request = new Ajax.Request(
    url,
    {
      method:'post',
      onComplete: this.onComplete
    }
  );

  },

  changePostalCode: function(url, index){
    if (this.loadWaiting!=false) return;
    this.loadWaiting = true;
    multiindex = "_" + index;
    if(!$('mappostalcode' + multiindex).value) { return false; }
    homeaddress = $('mappostalcode' + multiindex).value;
    $('mappostalcodebtn' + multiindex).hide();
    $('postalcode-please-wait' + multiindex).show();
    $('postalcode-please-wait' + multiindex).style.opacity = '0.5';
    hidehomeicon = true;
    var request = new Ajax.Request(
    url + '&zip=' + $('mappostalcode' + multiindex).value,
    {
      method:'post',
      onComplete: this.onComplete
    }
  );
  },

  hiderelais: function(url, index){
    if($('checkout-shipping-method-chronorelais-load_' + index)) {
      $('checkout-shipping-method-chronorelais-load_' + index).innerHTML = "";
    }
  },

  complete: function(transport){
    this.loadWaiting = false;

    if (transport && transport.responseText){
      try{
        response = eval('(' + transport.responseText + ')');
      }
      catch (e) {
        response = {};
      }
    }

    if (response.error) {
      alert(response.message);
      if($('mappostalcodebtn' + multiindex)) { $('mappostalcodebtn' + multiindex).show(); }
      if($('postalcode-please-wait' + multiindex)) { $('postalcode-please-wait' + multiindex).hide(); }
      return false;
    }

    if (response.update_section) {
      $(response.update_section.name).update(response.update_section.html);
      response.update_section.html.evalScripts();

      if(response.relaypoints) {
        if(response.relaypoints.length>0) {
          var relayaddress = "";
          gmarkers = [];
          addressrelais = [];
          relaylatan = [];
          next_pt = 0;
          bounds = new google.maps.LatLngBounds();
          for(var s=0; s<response.relaypoints.length; s++) {
            next_pt = s+1;
            relayaddress = response.relaypoints[s].adresse1;
            if(response.relaypoints[s].codePostal)
              relayaddress += " "+response.relaypoints[s].codePostal;
            if(response.relaypoints[s].localite)
              relayaddress += " "+response.relaypoints[s].localite;
            loadRelayMap(relayaddress, response.relaypoints[s], next_pt, 'chronomap' + multiindex,response.relaypoints.length);
          }
        }
      }
    }
  }
}
