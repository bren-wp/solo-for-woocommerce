<?php
/**
 * Plugin Name: Solo for WooCommerce
 * Plugin URI: https://solo.com.hr/api-dokumentacija/dodaci
 * Description: Narudžba u tvojoj WooCommerce trgovini će automatski kreirati račun ili ponudu u servisu Solo.
 * Version: 1.9.4
 * Requires at least: 5.2
 * Tested up to: 6.9
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 * Author: Solo
 * Author URI: https://solo.com.hr/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: solo-for-woocommerce
 * Domain Path: /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version.
if ( ! defined( 'SOLO_WOOCOMMERCE_VERSION' ) ) {
	define( 'SOLO_WOOCOMMERCE_VERSION', '1.9.4' );
}

// Backward-compatible version constant (deprecated).
if ( ! defined( 'SOLO_VERSION' ) ) {
	define( 'SOLO_VERSION', SOLO_WOOCOMMERCE_VERSION );
}

/**
 * Returns plugin settings array.
 *
 * @return array<string,mixed>
 */
function solo_woocommerce_get_settings() {
	$data = get_option( 'solo_woocommerce_postavke' );
	return is_array( $data ) ? $data : array();
}

/**
 * Returns a single setting.
 *
 * @param string $key Setting key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function solo_woocommerce_get_setting_value( $key, $default = null ) {
	$settings = solo_woocommerce_get_settings();
	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

/**
 * Determines whether telemetry is allowed.
 *
 * Telemetry can be disabled:
 * - via constant SOLO_WOOCOMMERCE_DISABLE_TELEMETRY
 * - via settings (telemetry=0)
 * - via filter solo_woocommerce_allow_telemetry
 *
 * @param string $event Event name.
 * @return bool
 */
function solo_woocommerce_is_telemetry_enabled( $event = '' ) {
	if ( defined( 'SOLO_WOOCOMMERCE_DISABLE_TELEMETRY' ) && SOLO_WOOCOMMERCE_DISABLE_TELEMETRY ) {
		return false;
	}

	// Backward compatibility: if option doesn't exist, default to enabled.
	$enabled = (int) solo_woocommerce_get_setting_value( 'telemetry', 1 );
	$enabled = ( 1 === $enabled );

	/**
	 * Filter whether telemetry is allowed.
	 *
	 * @param bool   $enabled Whether enabled.
	 * @param string $event   Event name (activation/deactivation/uninstall/update).
	 */
	return (bool) apply_filters( 'solo_woocommerce_allow_telemetry', $enabled, $event );
}

/**
 * Masks token values in stored request strings.
 *
 * @param string $text Request text.
 * @return string
 */
function solo_woocommerce_mask_token( $text ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return '';
	}
	// Token length is 33 chars in Solo; mask any sufficiently long alnum sequence.
	return preg_replace( '/token=([A-Za-z0-9]{10,})/', 'token=*****************************', $text );
}

/**
 * Builds a retry transient key.
 *
 * @param int    $order_id Order ID.
 * @param string $document_type Document type.
 * @return string
 */
function solo_woocommerce_retry_key( $order_id, $document_type ) {
	return 'solo_woocommerce_retry_' . absint( $order_id ) . '_' . sanitize_key( (string) $document_type );
}

//// Activate plugin
register_activation_hook(__FILE__, 'solo_woocommerce_activate');

function solo_woocommerce_activate() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	// Check PHP version
	if (version_compare(PHP_VERSION, '7.2', '<')) {
		wp_die(
		sprintf(
			/* translators: %s: current PHP version */
			esc_html__( 'Solo for WooCommerce dodatak ne podržava PHP %s. Ažuriraj PHP na verziju 7.2 ili noviju.', 'solo-for-woocommerce' ),
			esc_html( PHP_VERSION )
		),
		esc_html__( 'Greška', 'solo-for-woocommerce' ),
		array( 'back_link' => true )
	);
	}

	// Check if WooCommerce plugin installed
	if (!class_exists('WooCommerce')) {
		wp_die(
		wp_kses_post( __( 'Solo for WooCommerce ne radi bez WooCommerce dodatka.<br>Prvo instaliraj WooCommerce i zatim aktiviraj ovaj dodatak.', 'solo-for-woocommerce' ) ),
		esc_html__( 'Greška', 'solo-for-woocommerce' ),
		array( 'back_link' => true )
	);
	}
    if (version_compare(get_option('woocommerce_version'), 5, '<')) {
        wp_die(
		wp_kses_post( __( 'Solo for WooCommerce radi samo s WooCommerce verzijom 5 ili novijom.', 'solo-for-woocommerce' ) ),
		esc_html__( 'Greška', 'solo-for-woocommerce' ),
		array( 'back_link' => true )
	);
    }

	// Check if Woo Solo Api plugin installed
	if (is_plugin_active('woo-solo-api/woo-solo-api.php')) {
		wp_die(
		wp_kses_post( __( 'Prvo deaktiviraj "Woo Solo Api" dodatak.', 'solo-for-woocommerce' ) ),
		esc_html__( 'Greška', 'solo-for-woocommerce' ),
		array( 'back_link' => true )
	);
	}

	// Check if MX R1 plugin installed
	if (is_plugin_active('woocommerce-mx-r1/woocommerce-mx-r1.php')) {
		wp_die(
		wp_kses_post( __( 'Prvo deaktiviraj "WooCommerce MX R1 račun" dodatak.<br>Solo for WooCommerce automatski dodaje polja za pravne osobe (R1 račun) pri naručivanju.', 'solo-for-woocommerce' ) ),
		esc_html__( 'Greška', 'solo-for-woocommerce' ),
		array( 'back_link' => true )
	);
	}

	// Add exchange rate to database
	solo_woocommerce_exchange(1);

	// Create custom table in database
	solo_woocommerce_create_table();

	// Inform
	solo_woocommerce_inform('activation');
}

//// Deactivate plugin
register_deactivation_hook(__FILE__, 'solo_woocommerce_deactivate');

function solo_woocommerce_deactivate() {
	// Delete exchange rate from database and remove scheduled job
	solo_woocommerce_exchange(4);

	// Delete temporary transients
	delete_transient('solo_tag');
	delete_transient('solo_url');

	// Inform
	solo_woocommerce_inform('deactivation');

	// Note: keep table with orders
}

//// Uninstall plugin
register_uninstall_hook(__FILE__, 'solo_woocommerce_uninstall');

function solo_woocommerce_uninstall() {
	// Inform (respects telemetry setting).
	solo_woocommerce_inform( 'uninstall' );

	// Clear all scheduled events created by this plugin.
	$crons = _get_cron_array();
	if ( is_array( $crons ) ) {
		foreach ( $crons as $timestamp => $cron ) {
			if ( ! is_array( $cron ) ) {
				continue;
			}
			foreach ( array( 'solo_woocommerce_exchange_update', 'solo_woocommerce_api_post', 'solo_woocommerce_api_get' ) as $hook ) {
				if ( empty( $cron[ $hook ] ) ) {
					continue;
				}
				foreach ( $cron[ $hook ] as $sig => $data ) {
					if ( isset( $data['args'] ) && is_array( $data['args'] ) ) {
						wp_unschedule_event( (int) $timestamp, $hook, $data['args'] );
					}
				}
			}
		}
	}

	delete_transient( 'solo_latest' );
	delete_transient( 'solo_tag' );

	$settings = get_option( 'solo_woocommerce_postavke' );
	$remove   = is_array( $settings ) && ! empty( $settings['remove_data_on_uninstall'] );

	// Always remove exchange rate option if present.
	delete_option( 'solo_woocommerce_tecaj' );

	if ( $remove ) {
		delete_option( 'solo_woocommerce_postavke' );

		global $wpdb;
		// Table name is derived from $wpdb->prefix and then strictly sanitized.
		// Identifier placeholders (%i) are only available in WP 6.2+, so we safely interpolate a sanitized identifier.
		$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'solo_woocommerce' );
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
	}
}


//// Inform on activation, deactivation, uninstall
function solo_woocommerce_inform( $event ) {
	if ( ! solo_woocommerce_is_telemetry_enabled( (string) $event ) ) {
		return;
	}

	global $wp_version;
	$woo_version = class_exists( 'WooCommerce' ) ? WC()->version : '';
	$site_host   = wp_parse_url( home_url(), PHP_URL_HOST );

	$payload = array(
		'event'          => sanitize_key( (string) $event ),
		'site_host'      => $site_host ? (string) $site_host : '',
		'plugin_version' => SOLO_WOOCOMMERCE_VERSION,
		'wordpress'      => (string) $wp_version,
		'woocommerce'    => (string) $woo_version,
		'php'            => (string) PHP_VERSION,
	);

	/**
	 * Filter telemetry payload before sending.
	 *
	 * @param array  $payload Payload array.
	 * @param string $event   Event name.
	 */
	$payload = (array) apply_filters( 'solo_woocommerce_telemetry_payload', $payload, (string) $event );

	wp_safe_remote_post(
		'https://api.solo.com.hr/solo-for-woocommerce',
		array(
			'method'     => 'POST',
			'timeout'    => 10,
			'headers'    => array(
				'Content-Type' => 'application/json',
			),
			'body'       => wp_json_encode( $payload ),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
		)
	);
}


//// Create, update, view, delete exchange rate
function solo_woocommerce_exchange( int $action ) {
	switch ( $action ) {
		// Create.
		case 1:
			$encoded_json = solo_woocommerce_exchange_fetch();
			if ( null !== $encoded_json ) {
				if ( false === get_option( 'solo_woocommerce_tecaj', false ) ) {
					add_option( 'solo_woocommerce_tecaj', $encoded_json, '', 'no' );
				} else {
					update_option( 'solo_woocommerce_tecaj', $encoded_json, 'no' );
				}
			}

			// Add scheduled job for updating exchange rate (avoid duplicates).
			if ( ! wp_next_scheduled( 'solo_woocommerce_exchange_update', array( 2 ) ) ) {
				wp_schedule_event( time(), 'hourly', 'solo_woocommerce_exchange_update', array( 2 ) );
			}

			break;

		// Update.
		case 2:
			$encoded_json = solo_woocommerce_exchange_fetch();
			if ( null !== $encoded_json ) {
				update_option( 'solo_woocommerce_tecaj', $encoded_json, 'no' );
			}
			break;

		// View.
		case 3:
			$exchange = get_option( 'solo_woocommerce_tecaj' );
			if ( ! $exchange ) {
				printf(
					'<br><div class="notice notice-error inline"><p>%s</p></div>',
					wp_kses_post(
						sprintf(
							/* translators: %s: Plugins page URL. */
							__( 'Tečajna lista nije dostupna. Pokušaj <a href="%s#deactivate-solo-for-woocommerce">deaktivirati</a> i ponovno aktivirati dodatak.', 'solo-for-woocommerce' ),
							esc_url( admin_url( 'plugins.php' ) )
						)
					)
				);
			} else {
				$decoded_json = json_decode( (string) $exchange, true );
				$next_run    = wp_next_scheduled( 'solo_woocommerce_exchange_update', array( 2 ) );
				$next_run_h  = $next_run ? get_date_from_gmt( gmdate( 'H:i', (int) $next_run ), 'H:i' ) : '&ndash;';

				echo '<p>' . wp_kses_post(
					sprintf(
						/* translators: 1: Next update time (H:i). 2: Source URL. */
						__( 'Tečajna lista je formatirana za Solo gdje se HNB-ov tečaj dijeli s 1 (npr. tečaj za račun ili ponudu u valuti USD treba biti 0,94 umjesto 7,064035).<br>Podaci se automatski ažuriraju svakih sat vremena (iduće ažuriranje u %1$s). Izvor podataka: <a href="%2$s" target="_blank" rel="noopener noreferrer">Hrvatska Narodna Banka</a>', 'solo-for-woocommerce' ),
						esc_html( (string) $next_run_h ),
						esc_url( 'https://www.hnb.hr/statistika/statisticki-podaci/financijski-sektor/sredisnja-banka-hnb/devizni-tecajevi/referentni-tecajevi-esb-a' )
					)
				)
				. '</p>';

				echo '<table class="widefat striped" style="width:auto;"><colgroup><col style="width:50%;"><col style="width:50%;"></colgroup><thead><th>' . esc_html__( 'Valuta', 'solo-for-woocommerce' ) . '</th><th>' . esc_html__( 'Tečaj', 'solo-for-woocommerce' ) . '</th></thead><tbody>';
				if ( is_array( $decoded_json ) ) {
					foreach ( $decoded_json as $key => $val ) {
						if ( 'datum' === $key ) {
							continue;
						}
						echo '<tr><td>1 ' . esc_html( (string) $key ) . '</td><td>' . esc_html( str_replace( '.', ',', (string) $val ) ) . ' EUR</td></tr>';
					}
				}
				echo '</tbody></table>';
			}
			break;

		// Delete.
		case 4:
			wp_clear_scheduled_hook( 'solo_woocommerce_exchange_update', array( 2 ) );
			delete_option( 'solo_woocommerce_tecaj' );
			break;
	}
}



function solo_woocommerce_exchange_fetch() {
	$response = wp_safe_remote_get(
		esc_url_raw( 'https://api.hnb.hr/tecajn-eur/v3' ),
		array(
			'timeout'    => 15,
			'redirection'=> 3,
		)
	);

	if ( is_wp_error( $response ) ) {
		return null;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return null;
	}

	$data = wp_remote_retrieve_body( $response );
	if ( ! is_string( $data ) || '' === $data ) {
		return null;
	}

	$decoded_json = json_decode( $data, true );
	if ( ! is_array( $decoded_json ) ) {
		return null;
	}

	$array = array( 'datum' => get_date_from_gmt( gmdate( 'Y-m-d H:i:s' ) ) );

	foreach ( $decoded_json as $item ) {
		if ( ! is_array( $item ) || ! isset( $item['valuta'], $item['srednji_tecaj'] ) ) {
			continue;
		}
		$valuta = (string) $item['valuta'];
		$rate   = solo_woocommerce_floatvalue( (string) $item['srednji_tecaj'] );
		if ( $rate <= 0 ) {
			continue;
		}
		// HNB rate is per 1 unit; Solo expects rate in EUR (1/rate).
		$array[ $valuta ] = substr( (string) ( 1 / $rate ), 0, 8 );
	}

	return wp_json_encode( $array );
}


//// Needed for exchange rate parsing
function solo_woocommerce_floatvalue($val) {
	$val = str_replace(',', '.', $val);
	$val = preg_replace('/\.(?=.*\.)/', '', $val);
	return floatval($val);
}

//// Create custom table to save WooCommerce orders
function solo_woocommerce_create_table() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'solo_woocommerce' );

	// Prevent table creation if already exists.
	$maybe_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( $maybe_table === $table_name ) {
		return;
	}

	$sql = "CREATE TABLE {$table_name} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		order_id varchar(50) NOT NULL,
		api_request text NOT NULL,
		api_response text NOT NULL,
		created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		updated datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		sent datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		PRIMARY KEY (id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}


//// Call Solo API to create document
function solo_woocommerce_api_post( $url, $api_request, $order_id, $document_type ) {
	$sslverify = (bool) apply_filters( 'solo_woocommerce_sslverify', true );

	$response = wp_safe_remote_post(
		esc_url_raw( (string) $url ),
		array(
			'body'       => (string) $api_request,
			'sslverify'  => $sslverify,
			'timeout'    => 15,
			'redirection'=> 3,
			'headers'    => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		$api_response = wp_json_encode(
			array(
				'status'  => -1,
				'message' => $response->get_error_message(),
			)
		);
	} else {
		$api_response = (string) wp_remote_retrieve_body( $response );
	}

	// Save API response to our table.
	global $wpdb;
	$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'solo_woocommerce' );
	$wpdb->update(
		$table_name,
		array(
			'api_response' => $api_response,
			'updated'      => current_time( 'mysql' ),
		),
		array(
			'order_id' => (string) $order_id,
		)
	);

	// Decode JSON from API response.
	$json_response = json_decode( $api_response, true );
	$status        = is_array( $json_response ) && isset( $json_response['status'] ) ? (int) $json_response['status'] : null;
	$pdf           = null;

	if ( is_array( $json_response ) ) {
		if ( isset( $json_response['racun']['pdf'] ) ) {
			$pdf = $json_response['racun']['pdf'];
		} elseif ( isset( $json_response['ponuda']['pdf'] ) ) {
			$pdf = $json_response['ponuda']['pdf'];
		}
	}

	$retry_key   = solo_woocommerce_retry_key( (int) $order_id, (string) $document_type );
	$max_retries = (int) apply_filters( 'solo_woocommerce_max_retries', 12, (int) $order_id, (string) $document_type );
	$max_retries = max( 0, $max_retries );
	$retry_count = (int) get_transient( $retry_key );

	// Check for errors.
	if ( 0 === $status && ! empty( $pdf ) ) {
		delete_transient( $retry_key );

		// Download and send PDF (avoid scheduling duplicates).
		if ( ! wp_next_scheduled( 'solo_woocommerce_api_get', array( (string) $pdf, (int) $order_id, (string) $document_type ) ) ) {
			wp_schedule_single_event( time() + 5, 'solo_woocommerce_api_get', array( (string) $pdf, (int) $order_id, (string) $document_type ) );
		}
		return;
	}

	if ( 100 === $status ) {
		if ( $retry_count < $max_retries ) {
			set_transient( $retry_key, $retry_count + 1, HOUR_IN_SECONDS );

			// Retry after 5 seconds.
			wp_schedule_single_event( time() + 5, 'solo_woocommerce_api_post', array( (string) $url, (string) $api_request, (int) $order_id, (string) $document_type ) );
			return;
		}

		// Stop retrying.
		delete_transient( $retry_key );
		return;
	}

	// Stop on other errors.
	delete_transient( $retry_key );
}
;

//// Download PDF and send e-mail to buyer
function solo_woocommerce_api_get( $pdf, $order_id, $document_type ) {
	$send  = (int) solo_woocommerce::setting( 'posalji' );
	$title = (string) solo_woocommerce::setting( 'naslov' );
	$body  = (string) solo_woocommerce::setting( 'poruka' );

	// Proceed if enabled in settings.
	if ( 1 !== $send ) {
		return;
	}

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$billing_email = (string) $order->get_billing_email();
	if ( '' === $billing_email || ! is_email( $billing_email ) ) {
		return;
	}

	$pdf_url = (string) $pdf;
	if ( ! wp_http_validate_url( $pdf_url ) ) {
		return;
	}

	// Download PDF to a temporary file.
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$temp_file = download_url( $pdf_url, 20 );
	if ( is_wp_error( $temp_file ) ) {
		return;
	}

	$headers = 'Content-Type: text/mixed; charset=UTF-8';

	$sent = wp_mail( $billing_email, $title, $body, $headers, array( $temp_file ) );

	if ( $sent ) {
		global $wpdb;
		$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'solo_woocommerce' );
		$wpdb->update(
			$table_name,
			array(
				'sent' => current_time( 'mysql' ),
			),
			array(
				'order_id' => (string) $order_id,
			)
		);
	}

	wp_delete_file( $temp_file );
}
;

//// Main class, holds properties and methods
class solo_woocommerce {
	// Declare params to avoid "PHP Deprecated: Creation of dynamic property" warnings
	public $plugin_name = '';

	// Magic function
	public function __construct() {
		if (is_admin()) {
			// Shortcuts
			$this->plugin_name = 'solo-woocommerce';

			// Create settings link in WordPress > Plugins
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'solo_woocommerce_settings_link'));

			// Create settings link inside WooCommerce menu
			add_action('admin_menu', array($this, 'solo_woocommerce_submenu_link'), 99);

			// Load custom CSS and JS
			add_action('admin_enqueue_scripts', array($this, 'solo_woocommerce_css_js'));

			// Plugin settings (or update plugin)
			add_action('admin_init', array($this, 'solo_woocommerce_settings'));

			// Always show messages
			add_action('admin_notices', array($this, 'solo_woocommerce_show_messages'));

			// Ajax token check
			add_action('wp_ajax_check_token', array($this, 'solo_woocommerce_check_token'));
		}

		// Scheduled job for updating exchange rate
		add_action('solo_woocommerce_exchange_update', 'solo_woocommerce_exchange');

		// WooCommerce: remove certain fields in checkout
		add_filter('woocommerce_checkout_fields', array($this, 'solo_woocommerce_remove_fields'), 11);

		// WooCommerce: show custom fields in checkout
		add_action('woocommerce_before_checkout_billing_form', array($this, 'solo_woocommerce_custom_fields'), 12);

		// WooCommerce: save custom fields after checkout
		add_action('woocommerce_checkout_update_order_meta', array($this, 'solo_woocommerce_custom_meta'), 13);

		// WooCommerce: hooks
		add_action('woocommerce_order_status_changed', array($this, 'solo_woocommerce_process_order'), 14, 3);

		// WooCommerce: show custom fields in admin
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'solo_woocommerce_admin_order_meta'), 15);
		add_action('manage_shop_order_posts_custom_column', array($this, 'solo_woocommerce_admin_column_meta'), 16);
		add_action('woocommerce_order_details_after_order_table', array($this, 'solo_woocommerce_customer_order_meta'), 17);

		// Scheduled job for calling Solo API
		add_action('solo_woocommerce_api_post', 'solo_woocommerce_api_post', 1, 4);

		// Scheduled job for downloading PDF
		add_action('solo_woocommerce_api_get', 'solo_woocommerce_api_get', 2, 3);
	}

	//// Show notices
	function solo_woocommerce_show_messages() {
		settings_errors();
	}

	//// Removes certain fields in checkout
	public function solo_woocommerce_remove_fields($fields) {
		unset($fields['billing']['billing_company']);
		unset($fields['billing']['billing_state']);
		return $fields;
	}

	//// Show custom fields in checkout
	public function solo_woocommerce_custom_fields($fields) {
		echo '<div id="vat_number">';
		woocommerce_form_field('vat_checkbox', array(
				'type' => 'checkbox',
				'label' => __('Trebam R1 račun', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('input-checkbox')
			)
		);
		woocommerce_form_field('company_name', array(
				'type' => 'text',
				'label' => __('Naziv tvrtke', 'solo-for-woocommerce'),
				'placeholder' => __('Naziv tvrtke', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('form-row-wide hidden')
			),
			$fields->get_value('company_name')
		);
		woocommerce_form_field('company_address', array(
				'type' => 'text',
				'label' => __('Adresa', 'solo-for-woocommerce'),
				'placeholder' => __('Adresa', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('form-row-wide hidden')
			),
			$fields->get_value('company_address')
		);
		woocommerce_form_field('vat_number', array(
				'type' => 'text',
				'label' => __('OIB', 'solo-for-woocommerce'),
				'placeholder' => __('OIB', 'solo-for-woocommerce'),
				'required' => false,
				'class' => array('form-row-wide hidden')
			),
			$fields->get_value('vat_number')
		);
		echo '</div>';
		echo '<style>#vat_number .hidden{display:none;}</style>';
		echo '<script>jQuery(function($){$("#vat_number [type=checkbox]").on("click",function(){if($(this).is(":checked")){$("#company_name_field,#company_address_field,#vat_number_field").removeClass("hidden");$("#company_name").focus();}else{$("#company_name_field,#company_address_field,#vat_number_field").addClass("hidden");}});});</script>';
	}

	//// Save custom fields after checkout
	public function solo_woocommerce_custom_meta( $order_id ) {
	if ( empty( $_POST['vat_number'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	$company_name    = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$company_address = isset( $_POST['company_address'] ) ? sanitize_text_field( wp_unslash( $_POST['company_address'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$vat_number      = sanitize_text_field( wp_unslash( $_POST['vat_number'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	update_post_meta( absint( $order_id ), '_company_name', $company_name );
	update_post_meta( absint( $order_id ), '_company_address', $company_address );
	update_post_meta( absint( $order_id ), '_vat_number', $vat_number );
}


	//// Show custom fields to admin
	public function solo_woocommerce_admin_order_meta( $order ) {
	$order_id      = is_object( $order ) ? (int) $order->get_id() : 0;
	$naziv_tvrtke  = get_post_meta( $order_id, '_company_name', true );
	$adresa_tvrtke = get_post_meta( $order_id, '_company_address', true );
	$oib           = get_post_meta( $order_id, '_vat_number', true );

	if ( $naziv_tvrtke ) {
		$lines = array_filter(
			array(
				(string) $naziv_tvrtke,
				(string) $adresa_tvrtke,
				(string) $oib,
			)
		);

		$lines = array_map( 'esc_html', $lines );

		$lines_html = implode( '<br>', $lines );
			echo '<p><strong>' . esc_html__( 'Podaci za R1 račun', 'solo-for-woocommerce' ) . ':</strong><br>' . wp_kses_post( $lines_html ) . '</p>';
	}
}


	public function solo_woocommerce_admin_column_meta( $column ) {
	if ( 'order_number' === $column ) {
		global $the_order;

		if ( ! $the_order ) {
			return;
		}

		$naziv_tvrtke = get_post_meta( (int) $the_order->get_id(), '_company_name', true );
		if ( $naziv_tvrtke ) {
			echo '<br>' . esc_html( (string) $naziv_tvrtke );
		}
	}
}


	//// Show custom fields to customer
	public function solo_woocommerce_customer_order_meta( $order ) {
	$order_id      = is_object( $order ) ? (int) $order->get_id() : 0;
	$naziv_tvrtke  = get_post_meta( $order_id, '_company_name', true );
	$adresa_tvrtke = get_post_meta( $order_id, '_company_address', true );
	$oib           = get_post_meta( $order_id, '_vat_number', true );

	if ( $naziv_tvrtke ) {
		echo '<h2 class="woocommerce-column__title">' . esc_html__( 'Podaci za R1 račun', 'solo-for-woocommerce' ) . '</h2>';

		$lines = array_filter(
			array(
				(string) $naziv_tvrtke,
				(string) $adresa_tvrtke,
				(string) $oib,
			)
		);

		$lines = array_map( 'esc_html', $lines );
		$lines_html = implode( '<br>', $lines );
			echo '<p>' . wp_kses_post( $lines_html ) . '</p>';
	}
}


	//// Create settings link in plugins
	public function solo_woocommerce_settings_link($links) {
		$url = esc_url(add_query_arg('page', $this->plugin_name, get_admin_url() . 'admin.php'));
		$settings_link = '<a href="' . $url . '">' . esc_html__( 'Postavke', 'solo-for-woocommerce' ) . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	//// Create settings link under WooCommerce menu
	public function solo_woocommerce_submenu_link() {
		add_submenu_page('woocommerce', 'Solo for WooCommerce', __('Solo postavke', 'solo-for-woocommerce'), 'manage_options', $this->plugin_name, array($this, 'solo_woocommerce_settings_url'));
	}

	//// Settings file location
	public function solo_woocommerce_settings_url() {
		require_once plugin_dir_path(__FILE__) . 'lib/' . $this->plugin_name . '-settings.php';
	}

	//// Load custom CSS and JS
	public function solo_woocommerce_css_js( $hook_suffix ) {
	// Load assets only on this plugin's settings screen.
	$expected = 'woocommerce_page_' . $this->plugin_name;
	if ( $expected !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'lib/' . $this->plugin_name . '.css', array(), SOLO_WOOCOMMERCE_VERSION );
	wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'lib/' . $this->plugin_name . '.js', array( 'jquery' ), SOLO_WOOCOMMERCE_VERSION, true );

	wp_localize_script(
		$this->plugin_name,
		'ajax_object',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'solo_woocommerce_ajax' ),
		)
	);
}


	//// Return single setting
	public static function setting($id) {
		$data = get_option('solo_woocommerce_postavke');
		if (isset($data[$id])) return $data[$id];
	}

	//// Plugin settings (or update plugin)
	function solo_woocommerce_settings() {
		// Ensure plugin functions are available.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		// Deactivate if another plugin is active
		if (is_plugin_active('woo-solo-api/woo-solo-api.php')) {
			deactivate_plugins(__FILE__);
			// Show custom notice
			add_settings_error( 'solo_woocommerce_postavke', 'plugin_conflict', esc_html__( 'Solo for WooCommerce je automatski deaktiviran zbog Woo Solo Api dodatka.', 'solo-for-woocommerce' ), 'error' );
		}

		// Update plugin
		if (isset($_GET['update']) && current_user_can('update_plugins')) {
			// Nonce check
			if (check_admin_referer('solo_woocommerce_update_nonce')) {
				// Prepare update file to download
				$url = (string) get_transient( 'solo_url' );
				if ( ! wp_http_validate_url( $url ) ) {
					wp_die( esc_html__( 'Neispravan URL za ažuriranje.', 'solo-for-woocommerce' ) );
				}

				$host          = wp_parse_url( $url, PHP_URL_HOST );
				$allowed_hosts = array( 'github.com', 'api.github.com', 'objects.githubusercontent.com', 'raw.githubusercontent.com' );
				if ( $host && ! in_array( $host, $allowed_hosts, true ) ) {
					wp_die( esc_html__( 'Ažuriranje je blokirano zbog nepoznatog izvora.', 'solo-for-woocommerce' ) );
				}

				$temp_file = download_url( $url, 30 );
				if (is_wp_error($temp_file)) {
					wp_die( esc_html( $temp_file->get_error_message() ) );
					}

				// Deactivate plugin
				deactivate_plugins(__FILE__);

				// WordPress Filesystem API
				if (!function_exists('WP_Filesystem')) {
					require_once(ABSPATH . 'wp-admin/includes/file.php');
				}
				WP_Filesystem();

				// Unzip the file
				$folder = WP_PLUGIN_DIR;
				$result = unzip_file($temp_file, $folder);
				if (is_wp_error($result)) {
					wp_die( esc_html( $result->get_error_message() ) );
					}

				// Delete temporary file
				wp_delete_file( $temp_file );

				// Activate plugin
				activate_plugins(__FILE__);

				// Inform
				solo_woocommerce_inform('update');

				// Show custom notice
				add_settings_error( 'solo_woocommerce_postavke', 'solo_woocommerce_postavke', esc_html__( 'Dodatak uspješno ažuriran.', 'solo-for-woocommerce' ), 'updated' );
			}
		}

		register_setting('solo_woocommerce_postavke', 'solo_woocommerce_postavke', array($this, 'solo_woocommerce_form_validation'));
	}

	function solo_woocommerce_form_validation($data) {
		// Read settings
		$settings_data = get_option('solo_woocommerce_postavke');

		// Create array if doesn't exist
		if (!is_array($settings_data)) $settings_data = array();

		// Validate fields
		if ($data) {
			$message = __('Postavke uspješno spremljene.', 'solo-for-woocommerce');
			$type = 'updated';

			foreach($data as $key => $value) {
				// API token validation
				if ($key=='token' && !preg_match('/^[a-zA-Z0-9]{33}$/', $data[$key])) {
					$message = __('API token nije ispravan.', 'solo-for-woocommerce');
					$type = 'error';
					$settings_data = '';

					break;
				} else {
					$settings_data[$key] = sanitize_textarea_field($value);

					// Checkboxes
					if ( ! isset( $data['prikazi_porez'] ) ) {
						$settings_data['prikazi_porez'] = 0;
					}
					if ( ! isset( $data['posalji'] ) ) {
						$settings_data['posalji'] = 0;
					}
					// Backward compatibility defaults.
					if ( ! isset( $data['telemetry'] ) && ! isset( $settings_data['telemetry'] ) ) {
						$settings_data['telemetry'] = 1;
					}
					if ( ! isset( $data['remove_data_on_uninstall'] ) && ! isset( $settings_data['remove_data_on_uninstall'] ) ) {
						$settings_data['remove_data_on_uninstall'] = 0;
					}


					// Required param for API
					if (empty($data['tip_racuna']) || $data['tip_racuna']<=0) $settings_data['tip_racuna'] = 1;
				}
			}

			// Show custom notice
			add_settings_error('solo_woocommerce_postavke', 'solo_woocommerce_postavke', $message, $type);

			return $settings_data;
		}
	}

	//// Ajax token check
	function solo_woocommerce_check_token() {
	check_ajax_referer( 'solo_woocommerce_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Nemaš dopuštenje za ovu radnju.', 'solo-for-woocommerce' ) ), 403 );
	}

	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' === $token ) {
		wp_send_json_error( array( 'message' => __( 'Token nije zadan.', 'solo-for-woocommerce' ) ), 400 );
	}

	$url      = add_query_arg( 'token', rawurlencode( $token ), 'https://api.solo.com.hr/licenca' );
	$response = wp_safe_remote_get(
		esc_url_raw( $url ),
		array(
			'timeout'     => 15,
			'redirection' => 3,
		)
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error(
			array(
				'message' => $response->get_error_message(),
			),
			500
		);
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( (string) $body, true );

	if ( null === $data ) {
		wp_send_json_error(
			array(
				'message' => __( 'Neispravan odgovor servisa.', 'solo-for-woocommerce' ),
			),
			502
		);
	}

	wp_send_json( $data );
}


	//// Process order before sending to Solo API
	function solo_woocommerce_process_order($order_id, $old_status, $new_status) {
		// Get order information
		$order = wc_get_order($order_id);
		$order_status = $order->get_status();
		$payment_method = $order->get_payment_method();

		// Get plugin settings
		$settings = get_option('solo_woocommerce_postavke');
		$document_type = $trigger = '';
		if (!empty($settings)) {
			foreach ($settings as $key => $value) {
				${$key} = $value;
				// Find document type and trigger for this order
				if ($key==$payment_method . '1') $document_type = $value;
				if ($key==$payment_method . '2') $trigger = $value;
			}
		}

		// Setting found for this gateway, proceed
		if ($document_type<>'' && $trigger<>'') {

			// Check if order already exists.
			global $wpdb;
			$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'solo_woocommerce' );
			// Table name is sanitized above; prepare() is used for the dynamic values only.
			$exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT order_id FROM {$table_name} WHERE order_id = %d", absint( $order_id ) )
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Proceed on "checkout" or "completed"
			if (($old_status=='pending' && $new_status=='on-hold' && $trigger==1 && !$exists) || ($old_status=='pending' && $new_status=='processing' && $trigger==1 && !$exists) || ($old_status<>$new_status && $new_status=='completed' && $trigger==2 && !$exists)) {
				// Get order information
				$date_created = $order->get_date_created();
				$kupac_ime = $order->get_billing_first_name();
				$kupac_prezime = $order->get_billing_last_name();
				$kupac_naziv = $kupac_ime . ' ' . $kupac_prezime;
					// Custom fields added by plugin
					$naziv_tvrtke = get_post_meta($order_id, '_company_name', true);
					$adresa_tvrtke = get_post_meta($order_id, '_company_address', true);
					$kupac_oib = get_post_meta($order_id, '_vat_number', true);
					if ($naziv_tvrtke<>'') $kupac_naziv = $naziv_tvrtke;
				$kupac_adresa = $order->get_billing_address_1();
				if (!empty($order->get_billing_address_2())) $kupac_adresa .= ' ' . $order->get_billing_address_2();
				$kupac_adresa = $kupac_adresa . ', ' . $order->get_billing_postcode() . ' ' . $order->get_billing_city() . ', ' . $order->get_billing_country();
					if ($adresa_tvrtke<>'') $kupac_adresa = $adresa_tvrtke;

				// Payment methods (needed for fiscalization)
				$nacin_placanja = $fiskalizacija = '';
				switch ($payment_method) {
					// Direct bank transfer
					case 'bacs':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// Check payments
					case 'cheque':
						$nacin_placanja = 4;
						$fiskalizacija = 1;
						break;
					// Cash on delivery
					case 'cod':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// Stripe (Credit Card)
					case 'stripe':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Stripe (SEPA Direct Debit)
					case 'stripe_sepa':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// PayPal
					case 'paypal':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					case 'ppec-gateway':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					case 'ppcp-gateway':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// Braintree (Credit Card)
					case 'braintree_credit_card':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Braintree (PayPal)
					case 'braintree_paypal':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// CorvusPay (Credit Card)
					case 'corvuspay':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Monri (Credit Card)
					case 'monri':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// myPOS (Credit Card)
					case 'mypos_virtual':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Uplatnica
					case 'wooplatnica-croatia':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// KEKS Pay
					case 'erste-kekspay-woocommerce':
						$nacin_placanja = 1;
						$fiskalizacija = 0;
						break;
					// PayPal Express
					case 'eh_paypal_express':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Revolut (Credit/Debit Cards)
					case 'revolut_cc':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Aircash
					case 'aircash-woocommerce':
						$nacin_placanja = 3;
						$fiskalizacija = 1;
						break;
					// Stop
					default:
						return;
				}

				// Grammar
				$grammar = 'ponude';
				if ($document_type=='racun') $grammar = 'racuna';

				// API URL
				$url = 'https://api.solo.com.hr/' . $document_type;

				// Create POST request from order details
				$i = 0;
				$api_request = 'token=' . $token . PHP_EOL;
				if (!isset($tip_usluge)) {
					$tip_usluge = 1;
				} else {
					if (!is_numeric($tip_usluge)) $tip_usluge = 1;
				}
				$api_request .= '&tip_usluge=' . $tip_usluge . PHP_EOL;
				if (!isset($prikazi_porez)) $prikazi_porez = 0;
				$api_request .= '&prikazi_porez=' . $prikazi_porez . PHP_EOL;
				if (!isset($tip_racuna) || empty($tip_racuna)) $tip_racuna = 1;
				$api_request .= '&tip_racuna=' . $tip_racuna . PHP_EOL;
				$api_request .= '&kupac_naziv=' . urlencode($kupac_naziv) . PHP_EOL;
				$api_request .= '&kupac_adresa=' . urlencode($kupac_adresa) . PHP_EOL;
				$api_request .= '&kupac_oib=' . urlencode($kupac_oib) . PHP_EOL;

				// Order items
				$items = $order->get_items();
				foreach ($items as $item_key => $item) {
					$i++;

					// Get item details
					$item_name = $item->get_name();
					$item_quantity = $item->get_quantity();
					$tax_total = $item->get_subtotal_tax();

					// Fetch tax rate and round if necessary
					$taxes = WC_Tax::get_rates($item->get_tax_class());
					$item_tax = isset(current($taxes)['rate']) ? round(current($taxes)['rate']) : 0;

					// Override tax if not a standard rate or if no tax was applied
					if (!in_array($item_tax, [5, 13, 25]) || $tax_total == 0) {
						$item_tax = 0;
					}

					// Autodetect variable item
					$item_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];

					// Switch to product object (needed for prices and name)
					$product = wc_get_product($item_id);

					// Variable item
					if ($item['variation_id']) {
						// Replace order item name with product name
						$item_name = $product->get_title();

						// Append attributes and variations to product name
						foreach ($item->get_meta_data() as $meta) {
							if (empty($meta->id) || '' === $meta->value || !is_scalar($meta->value)) {
								continue;
							}

							$meta->key = rawurldecode((string) $meta->key);
							$meta->value = rawurldecode((string) $meta->value);
							$attribute_key = str_replace('attribute_', '', $meta->key);
							$display_key = wc_attribute_label($attribute_key, $product);
							$display_value = wp_kses_post($meta->value);

							if (taxonomy_exists($attribute_key)) {
								$term = get_term_by('slug', $meta->value, $attribute_key);
								if (!is_wp_error($term) && is_object($term) && $term->name) {
									$display_value = $term->name;
								}
							}
							$item_name .= '\r\n' . $display_key . ': ' . $display_value;
						}
					}

					$item_price = wc_get_price_excluding_tax($product, array('price' => $product->get_regular_price()));
					$item_discount = 0;
					// On sale products
					if ($product->is_on_sale()) {
						$item_sale_price = wc_get_price_excluding_tax($product, array('price' => $product->get_sale_price()));
						$item_discount = 100 - (($item_sale_price/$item_price) * 100);
						// Max 18 chars
						$item_discount = substr($item_discount, 0, 8);
						// Max 4 decimals
						$item_discount = number_format($item_discount, 4, ',', '');
					}
					$item_price = round($item_price, 2);
					$item_price = number_format($item_price, 2, ',', '');

					$item_quantity = str_replace('.', ',', $item_quantity);
					$item_discount = str_replace('.', ',', $item_discount);

					$api_request .= '&usluga=' . $i . PHP_EOL;
					$api_request .= '&opis_usluge_' . $i . '=' . urlencode($item_name) . PHP_EOL;
					$api_request .= '&jed_mjera_' . $i . '=2' . PHP_EOL;
					$api_request .= '&cijena_' . $i . '=' . $item_price . PHP_EOL;
					$api_request .= '&kolicina_' . $i . '=' . $item_quantity . PHP_EOL;
					$api_request .= '&popust_' . $i . '=' . $item_discount . PHP_EOL;
					$api_request .= '&porez_stopa_' . $i . '=' . $item_tax . PHP_EOL;
				}

				// Coupons
				$coupon_price = 0;
				foreach($order->get_items('coupon') as $item_id => $item) {
					$coupon_data = $item->get_data();
					$coupon_price = $coupon_data['discount'];
					$coupon_code = $coupon_data['code'];
					$coupon_tax = $coupon_data['discount_tax'];
					$coupon_price = ($coupon_price + $coupon_tax);
					if ($coupon_tax>0) {
						$coupon_tax = 25;
					} else {
						$coupon_tax = 0;
					}

					if ($coupon_price>0) {
						$i++;

						$coupon_price = $coupon_price / (1 + ($coupon_tax/100));
						$coupon_price = -1 * $coupon_price;
						$coupon_price = round($coupon_price, 2);
						$coupon_price = number_format($coupon_price, 2, ',', '');

						$api_request .= '&usluga=' . $i . PHP_EOL;
						$api_request .= '&opis_usluge_' . $i . '=' . urlencode(__('Kupon za popust', 'solo-for-woocommerce') . ' (' . $coupon_code . ')') . PHP_EOL;
						$api_request .= '&jed_mjera_' . $i . '=1' . PHP_EOL;
						$api_request .= '&cijena_' . $i . '=' . $coupon_price . PHP_EOL;
						$api_request .= '&kolicina_' . $i . '=1' . PHP_EOL;
						$api_request .= '&popust_' . $i . '=0' . PHP_EOL;
						$api_request .= '&porez_stopa_' . $i . '=' . $coupon_tax . PHP_EOL;
					}
				}

				// Shipping
				$shipping_price = $order->get_shipping_total();
				$shipping_tax = $order->get_shipping_tax();
				if ($shipping_price>0) {
					$i++;

					$shipping_tax = (($shipping_tax/$shipping_price) * 100);
					if (!is_numeric($shipping_tax)) $shipping_tax = 0;
					$shipping_tax = round($shipping_tax);
					$shipping_price = round($shipping_price, 2);
					$shipping_price = number_format($shipping_price, 2, ',', '');

					$api_request .= '&usluga=' . $i . PHP_EOL;
					$api_request .= '&opis_usluge_' . $i . '=' . urlencode(__('Poštarina', 'solo-for-woocommerce')) . PHP_EOL;
					$api_request .= '&jed_mjera_' . $i . '=1' . PHP_EOL;
					$api_request .= '&cijena_' . $i . '=' . $shipping_price . PHP_EOL;
					$api_request .= '&kolicina_' . $i . '=1' . PHP_EOL;
					$api_request .= '&popust_' . $i . '=0' . PHP_EOL;
					$api_request .= '&porez_stopa_' . $i . '=' . $shipping_tax . PHP_EOL;
				}

				$api_request .= '&nacin_placanja=' . $nacin_placanja . PHP_EOL;
				if (isset($rok_placanja) && !empty($rok_placanja)) $api_request .= '&rok_placanja=' . $rok_placanja . PHP_EOL;
				if (isset($iban) && !empty($iban)) $api_request .= '&iban=' . $iban . PHP_EOL;

				// Other currencies
				$currency = $order->get_currency();
				$currency_exchange = 1;
				if ($currency<>'EUR') {
					$exchange_rates = get_option('solo_woocommerce_tecaj');
					$exchange_rates = json_decode($exchange_rates, true);
					if (!empty($exchange_rates)) {
						foreach ($exchange_rates as $key => $value) {
							if ($currency==$key) $currency_exchange = $value;
						}
					}
					$currency_exchange = str_replace('.', ',', $currency_exchange);

					// Transform currency name to integer
					$accepted_currencies = array(
						'AUD' => '2',
						'CAD' => '3',
						'CZK' => '4',
						'DKK' => '5',
						'HUF' => '6',
						'JPY' => '7',
						'NOK' => '8',
						'SEK' => '9',
						'CHF' => '10',
						'GBP' => '11',
						'USD' => '12',
						'BAM' => '13',
						'EUR' => '14',
						'PLN' => '15'
					);

					// Set document currency and exchange rate
					if (isset($accepted_currencies[$currency])) {
						$currency_id = $accepted_currencies[$currency];

						$api_request .= '&valuta_' . $grammar . '=' . $currency_id . PHP_EOL;
						$api_request .= '&tecaj=' . $currency_exchange . PHP_EOL;
						$api_request .= '&napomene=' . urlencode(__('Preračunato po srednjem tečaju HNB-a', 'solo-for-woocommerce') . ' (1 EUR = ' . $currency_exchange . ' ' . $currency . ')') . PHP_EOL;
					} else {
						// Stop
						return;
					}
				}

				// Notes
				if (isset(${'napomene_' . $document_type}) && !empty(${'napomene_' . $document_type})) $api_request .= '&napomene=' . urlencode(${'napomene_' . $document_type}) . PHP_EOL;

				// Language
				if (isset($jezik_) && !empty($jezik_)) $api_request .= '&jezik_' . $grammar . '=' . $jezik_ . PHP_EOL;

				// Fiscalization
				$api_request .= '&fiskalizacija=' . $fiskalizacija . PHP_EOL;

				// Check for table in database
				global $wpdb;
				$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'solo_woocommerce' );
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					// Create table if doesn't exist
					solo_woocommerce_create_table();
				}

				// Save order to database
				$wpdb->insert(
					$table_name,
					array(
						'order_id' => $order_id,
						'api_request' => solo_woocommerce_mask_token( 'POST ' . $url . PHP_EOL . $api_request ),
						'created' => current_time('mysql')
					)
				);

				// Send order to Solo API
				solo_woocommerce_api_post($url, str_replace(PHP_EOL, '', $api_request), $order_id, $document_type);
			}
		}
	}
}
$solo_woocommerce = new solo_woocommerce;
