<?php
/**
* Plugin Name: GA Custom Event Tracker
* Description: Google Analytics Event Tracking for the following events: click on chat box, subscribe to newsletter.
* Version: 1.1
* Author: Sorin Coza
* Author URI: http://sorincoza.com
*
* 
*/


// include options page class and instantiate
if ( is_admin() ){
	include 'options-page-lib/class.php';
	new Evt_Tracker_Plugin_Settings( __FILE__ );
}






// then bussiness as usual
add_action( 'wp_enqueue_scripts', 'event_tracker_scripts' );
add_action( 'wp_ajax_send_event_email', 'event_tracker_send_email' );
add_action( 'wp_ajax_save_event_tracking_table_as_csv', 'event_tracker_save_table_as_csv' );
register_activation_hook( __FILE__, 'event_tracker_install' );




function event_tracker_scripts(){
	wp_enqueue_script( 'event-tracker-script', plugin_dir_url( __FILE__ ) . 'assets/js/event-tracker.js', array('jquery'), '', true );

	// pass ajax url to javascript:
	wp_localize_script( 
		'event-tracker-script',
		'ajax_object',
        array( 
        	'ajax_url' => admin_url( 'admin-ajax.php' )  
        ) 
    );

}


function event_tracker_send_email(){
	if ( empty( $_REQUEST ) ){ return; }
	$message = '';


	$db_values = array();

	$message .= '<!DOCTYPE html><body>';


	$subject = empty( $_REQUEST['subject'] )  ?  'New event'  :  $_REQUEST['subject'];
	$message .= '<h2 id="email-title">A new event occured on one of your pages. Here are the details:</h2>';

	// add the IP:
	$message .= '<span id="ip" class="keyval-pair"><b class="name">IP Address</b> :  <span class="value">' . $_SERVER['REMOTE_ADDR'] . '</span></span><br><br>';
	$db_values['ip'] = $_SERVER['REMOTE_ADDR']; // store value for database insert

	// add all other keys:
	foreach ($_REQUEST as $key => $value) {
		if ( $key === 'action'  ||  $key === 'query'  ||  $key === '_ca_data'  ||  $key === '_ca_history' ){ continue; }

		$message .= '<span id="' . $key . '" class="keyval-pair"><b class="name">' . $key . '</b> :  <span class="value">' . $value . '</span></span><br>';

	}

	// add to db values
	$keys = array( '_ga', 'email', 'page' );
	foreach ($keys as $key) {
		$db_values[ $key ] = $_REQUEST[ $key ];
	}


	// add query parameters info:
	if ( !empty( $_REQUEST['query'] ) ){
		$query_pairs = explode( '&', $_REQUEST['query'] );
		$message .= '<br>' . '<h3 id="url-params-title">The following parameters were found in the URL:</h3>';

		$message .= '<div id="url-params-values">';
		foreach ($query_pairs as $pair) {
			$message .= str_replace( '=', ' = ', $pair ); // put some space for readability
			$message .= '<br>';
		}
		$message .= '</div>';

	}else{
		$message .= '<h3 id="url-params-title">No parameters were found in the URL.</h3>';
	}


	// inform about invalid email:
	if ( $_REQUEST['isValidEmail'] == 'false' ){
		$message .= '<h3 id="invalid-email-message">The email appears to be invalid, so probably Spokal ignored this subscription.</h3>';
	}

	//get the tracking data
	$tracking_data = get_event_tracker__ca_data();

	// add to db values:
	$db_values = array_merge( $db_values, $tracking_data );

	// now dump all tracking data:
	$message .= '<h3 id="ga-data-title">The following Google Analytics tracking data was found in localStorage:</h3>';

	$message .= '<div id="ga-data-values">';
	foreach ( $tracking_data as $key => $value ) {
		if ( $key === 'paidSearch' ){
			$value = empty($value) ? 'false' : 'true';
		}
		$message .= '<span id="' . $key . '" class="keyval-pair"><b class="name">' . $key . '</b> :  <span class="value">' . $value . '</span></span><br>';
	}
	$message .= '</div>';

	// add some style
	$message .= 
	'<style>
		body{
			font-family: sans-serif;
		}
		.keyval-pair .name{
			display: inline-block;
			width: 150px;
		}
		.keyval-pair .value{
			color: red;
		}

	</style>';

	$message .= '</body>';



	// save to database:
	$db_values['_ca_history'] = stripslashes( $_REQUEST['_ca_history'] );
	event_tracker_save_to_database( $db_values );



	// send email if email is set up
	$to = get_option( 'evt_track_email', '' );
	if ( !empty( $to ) ){
		add_filter( 'wp_mail_content_type', create_function('', 'return "text/html"; ') );
		wp_mail( $to, $subject, $message );
	}

var_dump($_REQUEST);

	//all done
	wp_die();

}

function get_event_tracker__ca_data(){
	return json_decode( stripslashes($_REQUEST['_ca_data']), true );
}

function get_event_tracker_table_name(){
	global $wpdb;
	return $wpdb->prefix . 'event_tracker';
}

function event_tracker_install() {
	global $wpdb;

	$table_name = get_event_tracker_table_name();
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		_ga varchar(50),
		email varchar(100),
		type varchar(50),
		ip varchar(50),
		page text,
		submits varchar(20) DEFAULT '0',
		clicks varchar(20) DEFAULT '0',
		utm_campaign varchar(100),
		utm_content varchar(100),
		utm_medium varchar(100),
		utm_source varchar(100),
		utm_term varchar(100),
		gclid varchar(100),
		paidSearch varchar(1),
		_ca_history text,
		timestamp varchar(50),
		UNIQUE KEY id (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

}

function event_tracker_save_to_database( $key_vals ){
	global $wpdb;

	// take care of details
	$key_vals['paidSearch'] = !empty( $key_vals['paidSearch'] )  ?  '1'  :  '0';
	$key_vals['email'] = trim( $key_vals['email'] );

	$eventTypeKey = $_REQUEST['eventType'] . 's';


	$existing_row = $wpdb->get_row( 'SELECT * FROM ' . get_event_tracker_table_name() .  ' WHERE _ga = "' . $key_vals['_ga'] . '"', ARRAY_A );


	if (  ( $existing_row === null  &&  !empty($key_vals['_ga'])  )  ||  ( empty($key_vals['_ga']) ) ) {

		$key_vals[ $eventTypeKey ] = 1;
		$wpdb->insert(
			get_event_tracker_table_name(), 
			$key_vals
		);

	}elseif ( $existing_row !== null  &&  !empty($key_vals['_ga']) ){
		// if empty email or invalid, get the existing value
		if ( !empty($existing_row['email']) ){
			if (  empty( trim($key_vals['email']) )  ||  ( isset($_REQUEST['isValidEmail']) && $_REQUEST['isValidEmail'] == 'false' )  ){
				$key_vals['email'] = $existing_row['email'];
			}
		}

		$key_vals[ $eventTypeKey ] = $existing_row[ $eventTypeKey ] + 1;
		$wpdb->update(
			get_event_tracker_table_name(), 
			$key_vals,
			array( '_ga' => $key_vals['_ga'] )  // where clause
		);

	}

}


function event_tracker_save_table_as_csv(){
	global $wpdb;

	$query = 'SELECT * FROM ' . get_event_tracker_table_name() . ' ;';

	$results = $wpdb->get_results( $query, ARRAY_A );


	/* Construct csv content:  ***/

	// init with the first line, which contains column names:
	$file_content = construct_event_tracker_csv_row( $results[0], true );

	// now construct all the other lines:
	foreach ( $results as $row) {
		$file_content .= construct_event_tracker_csv_row( $row );
	}
	

	// We'll be outputting a csv
	header('Content-type: application/csv');

	// Set file name
	header('Content-Disposition: attachment; filename="event-tracking-data.csv"');

	// Write the contents to output stream
	file_put_contents( "php://output", $file_content );

	wp_die();
}


function construct_event_tracker_csv_row( $array, $construct_with_keys = false ){
	$row_start_flag = '$#123_';  // something that has a good change of being unique. This helps removing the start comma
	$row_content = $row_start_flag;
	
	foreach ( $array as $key => $val ) {
		$row_content .= ', ' . ( $construct_with_keys ? $key : $val ) . ' ';
	}
	// remove the ', ' from the beginning of row
	$row_content = str_replace( $row_start_flag . ', ' , '', $row_content );
	// new line
	$row_content .= PHP_EOL;
	
	return $row_content;
}