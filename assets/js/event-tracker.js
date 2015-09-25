(function (){
	var eventTracker = {

		init: function(){

			// init the elements that should be tracked:
			for ( var key in eventTracker.elements ){
				eventTracker.initElement( key );
			}

		}

		,elements: {
			// init all elements that interest us
			chatSwitcher: { 
				id: 'habla_oplink_a', 
				object: null,
				eventCategory: 'chat-window',
				eventType: 'click',
				eventLabel: 'Chat Window Click',
				emailSubject: 'New chat event',
				emailSent: false 
			},
			subscribeForm_Bottom: { 
				id: 'spokalLeadsScrollBox', 
				object: null,
				eventCategory: 'subscribe-button',
				eventType: 'submit',
				eventLabel: 'Bottom Form Subscription',
				emailSubject: 'New subscription event',
				emailSent: false 
			},
			subscribeForm_Center: { 
				id: 'sp_popup_form-802', 
				object: null,
				eventCategory: 'subscribe-button',
				eventType: 'submit',
				eventLabel: 'Popup Form Subscription',
				emailSubject: 'New subscription event',
				emailSent: false 
			}
		}

		,initElement: function( elementKey ){
			if ( !elementKey ){ return; }

			// set an interval, since these elements are created with JS, and we don't know when they are created
			var interval,
				count = 0,
				maxCount = 100;

			interval = setInterval(function(){
				count++;
				var element = document.getElementById( eventTracker.elements[ elementKey ]['id'] );
				if ( element ){
					clearInterval( interval );
					eventTracker.elements[ elementKey ]['object'] = element;

					// add the appropriate event listeners:
					var onEvt = 'on' + eventTracker.elements[ elementKey ].eventType;
					element[ onEvt ] = function(e){
						e.preventDefault();
						eventTracker[ onEvt ]( element, elementKey );
					};

				}
				if ( count >= maxCount ){
					clearInterval( interval );
				}
			}, 200);
		}

		,onclick: function ( elem, elementKey ){

			// now send data to google
			eventTracker.sendEvent( elementKey );

			// handle email sending
			var data = {};
			eventTracker.sendEmail( elementKey, data );

		}

		,onsubmit: function( form, elementKey ){
			var inputs = form.getElementsByTagName( 'input' ),
				emailElem = null,

				isValidEmail = false,

				eventLabel = eventTracker.elements[ elementKey ].eventLabel; // store the original value

			// get the right email input:
			for( var i=inputs.length; i-->0; ){
				if ( inputs[i].getAttribute('name') === 'Email' ){
					// we found our input
					emailElem = inputs[i];
					break;
				}
			}

			// return if we did not find the email input by now
			if ( emailElem === null ){ return; }



			isValidEmail = eventTracker.isValidEmail( emailElem.value );


			if ( !isValidEmail ){
				// add temporary 'invalid' flag
				eventTracker.elements[ elementKey ].eventLabel += ' - invalid email';
			}

			// now send data to google
			eventTracker.sendEvent( elementKey );

			// restore original value
			if ( !isValidEmail ){
				eventTracker.elements[ elementKey ].eventLabel = eventLabel;
			}

			// handle email sending
			var data = {
				email: emailElem.value,
				isValidEmail: isValidEmail
			} 
			eventTracker.sendEmail( elementKey, data );

		}

		,sendEvent: function( elementKey ){
			if ( typeof ga !== 'function' ){ return; } // no point in going further

			var eventCategory = eventTracker.elements[ elementKey ].eventCategory,
				eventType = eventTracker.elements[ elementKey ].eventType,
				eventLabel = eventTracker.elements[ elementKey ].eventLabel;

			ga('send', 'event', eventCategory, eventType, eventLabel, 1);
		}

		,sendEmail: function( elementKey, data ){
			if ( typeof jQuery !== 'function'   ||  eventTracker.elements[ elementKey ].emailSent === true ){ 
				console.warn( 'Email was not sent.' );
				return; 
			}


			var data = eventTracker.getDataToBeSent( elementKey, data );

			jQuery.post(
				ajax_object.ajax_url,  // this object is passed from WP, in file event-tracker.php
				data
			);

			if ( data.eventType === 'click' ){
				// we don't want to send email for every click
				eventTracker.elements[ elementKey ].emailSent = true;
			}
			
			// if the email is invalid, we keep sending emails
			if ( data.eventType === 'submit'  &&  !data.isValidEmail ){
				eventTracker.elements[ elementKey ].emailSent = false;
			}

		}

		,getDataToBeSent: function( elementKey, data ){

			data.subject = eventTracker.elements[ elementKey ].emailSubject;
			data.eventType = eventTracker.elements[ elementKey ].eventType;
			data.page = window.location.origin + window.location.pathname;

			// get the query vars
			data.query = window.location.search.replace( '?', '' );

			// get the cookies and local storage data:
			var storageObjs = eventTracker.getLocalStorageObjects();
			data._ga = eventTracker.getGACookieValue();
			data._ca_data = storageObjs['_ca_data'];
			data._ca_history = storageObjs['_ca_history'];

			data.action = 'send_event_email';

			return data;
		}

		,isValidEmail: function ( emailAddress ) {
		    var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
		    return pattern.test( emailAddress );
		}

		,getLocalStorageObjects: function(){
			storage = ( typeof window.localStorage !== 'undefined' ? window.localStorage : false );
			if ( !storage ){ return false; }

			var result = {},
				keys = [ '_ca_data', '_ca_history' ];

			for ( var i in keys ){
				if ( typeof storage.getItem !== 'undefined' ){
					result[ keys[i] ] = storage.getItem( keys[i] );
				}
			}

			return result;
		}

		,getGACookieValue: function(){
			var sKey = '_ga';
    		return decodeURIComponent(document.cookie.replace(new RegExp("(?:(?:^|.*;)\\s*" + encodeURIComponent(sKey).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=\\s*([^;]*).*$)|^.*$"), "$1")) || null;
		}


	};

	eventTracker.init();
})();