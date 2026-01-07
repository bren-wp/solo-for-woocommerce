<?php
/**
 * Settings page markup for Solo for WooCommerce.
 *
 * @package solo-for-woocommerce
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Version check
$version_check = get_transient('solo_tag');
$tag = $installed = SOLO_VERSION;

$solo_update = isset( $_GET['update'] ) ? sanitize_text_field( wp_unslash( $_GET['update'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ( $installed !== $version_check ) && ( '' === $solo_update ) ) {
	// Read transient if exists
	$tag = ltrim(get_transient('solo_tag'), 'v');

	// Transient not found, fetch latest version from GitHub
	if (!$version_check) {
		$json = wp_safe_remote_get('https://api.github.com/repos/coax/solo-for-woocommerce/releases',
			array(
				'timeout'      => 10,
				'redirection' => 3,
				'user-agent'   => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				'headers'      => array(
					'Accept' => 'application/json'
				)
			)
		);
		$decoded_json = array();
		if ( ! is_wp_error( $json ) ) {
			$decoded_json = json_decode( wp_remote_retrieve_body( $json ), true );
		}
		if ( ! is_array( $decoded_json ) ) {
			$decoded_json = array();
		}
		if (isset($decoded_json[0]['name'])) {
			$tag = ltrim($decoded_json[0]['name'], 'v');
			$url = $decoded_json[0]['assets'][0]['browser_download_url'];

			// Create temporary transients (instead session)
			set_transient('solo_tag', $tag, 60*60*24);
			set_transient('solo_url', $url, 60*60*24);
		}
	}

	// Display notice
	if (version_compare($installed, $tag, '<')) {
?>
      <div class="notice notice-info notice-alt">
	<p>
		<?php
		printf(
			'%s: <a href="%s" target="_blank" rel="noopener noreferrer">%s %s</a>',
			esc_html__( 'Dostupna je nova verzija dodatka', 'solo-for-woocommerce' ),
			esc_url( 'https://github.com/coax/solo-for-woocommerce/releases' ),
			esc_html__( 'Solo for WooCommerce', 'solo-for-woocommerce' ),
			esc_html( $tag )
		);
		?>
	</p>
	<p>
		<a href="<?php echo esc_url( wp_nonce_url( '?page=solo-woocommerce&update=true', 'solo_woocommerce_update_nonce' ) ); ?>" class="button button-small button-primary">
			<?php echo esc_html__( 'Instaliraj novu verziju', 'solo-for-woocommerce' ); ?>
		</a>
	</p>
</div>
<?php
	}
}

// Tabs
$default_tab = null;
$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default_tab; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Init main class
$solo_woocommerce = new solo_woocommerce;

// Define default variables
$token = $tip_usluge = $jezik_ = $prikazi_porez = $tip_racuna = $rok_placanja = $napomene_racun = $napomene_ponuda = $iban = $akcija = $posalji = $naslov = $poruka = $telemetry = $remove_data_on_uninstall = '';

// Create variables from settings
$settings = get_option('solo_woocommerce_postavke');
if (!empty($settings)) {
	foreach ($settings as $key => $option) {
		${$key} = $option;
	}
}
?>
<div class="wrap">
  <form action="options.php" method="post">
    <?php settings_fields('solo_woocommerce_postavke'); ?>
    <h1><div class="solo-logo"></div><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p><?php echo esc_html__('Narudžba u tvojoj WooCommerce trgovini će automatski kreirati račun ili ponudu u servisu Solo.', 'solo-for-woocommerce'); ?></p>
    <nav class="nav-tab-wrapper">
      <a href="?page=solo-woocommerce" class="nav-tab <?php if($tab===null):?>nav-tab-active<?php endif; ?>">API token</a>
<?php if ($token) { ?>
      <a href="?page=solo-woocommerce&tab=postavke" class="nav-tab<?php if($tab==='postavke'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Solo postavke', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=akcije" class="nav-tab<?php if($tab==='akcije'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Načini plaćanja i akcije', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=email" class="nav-tab<?php if($tab==='email'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('E-mail postavke', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=tecaj" class="nav-tab<?php if($tab==='tecaj'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Tečajna lista', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=arhiva" class="nav-tab<?php if($tab==='arhiva'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Arhiva', 'solo-for-woocommerce'); ?></a>
      <a href="?page=solo-woocommerce&tab=podrska" class="nav-tab<?php if($tab==='podrska'):?> nav-tab-active<?php endif; ?>"><?php echo esc_html__('Podrška', 'solo-for-woocommerce'); ?></a>
<?php } ?>
    </nav>
    <div class="tab-content">
<?php
// API token missing
if (!$token) $tab = $default_tab;

switch($tab):
	default:
?>
      <input type="hidden" name="solo_woocommerce_postavke[prikazi_porez]" value="<?php echo esc_attr( $prikazi_porez ); ?>">
      <input type="hidden" name="solo_woocommerce_postavke[tip_racuna]" value="<?php echo esc_attr( $tip_racuna ); ?>">
      <input type="hidden" name="solo_woocommerce_postavke[posalji]" value="<?php echo esc_attr( $posalji ); ?>">
      <table class="form-table">
        <tbody>
          <tr>
            <th>
              <label for="token"><?php echo esc_html__('API token', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Upiši svoj API token. Token ćeš pronaći u web servisu klikom na Postavke.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td class="mailserver-pass-wrap">
              <span class="wp-pwd">
                <input type="password" name="solo_woocommerce_postavke[token]" id="token" value="<?php echo esc_attr( $token ); ?>" autocorrect="off" autocomplete="off" maxlength="33" placeholder="" class="regular-text" class="mailserver-pass-wrap">
                <button type="button" class="button wp-hide-pw hide-if-no-js" id="toggle"><span class="dashicons dashicons-visibility"></span></button>
              </span>
              <p class="description"><?php if ($token==''): ?><?php echo esc_html__('Upiši i spremi promjene kako bi se prikazale ostale opcije.', 'solo-for-woocommerce'); ?><?php else: ?><a href="#" class="provjera"><?php echo esc_html__('Provjeri valjanost tokena', 'solo-for-woocommerce'); ?></a><?php endif; ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		break;

	case 'postavke':
?>
      <input type="hidden" name="solo_woocommerce_postavke[posalji]" value="<?php echo esc_attr( $posalji ); ?>">
      <table class="form-table">
        <tbody>
          <tr>
            <th>
              <label for="tip_usluge"><?php echo esc_html__('Tip usluge', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Upiši redni broj glavnog tipa usluge iz web sučelja > Usluge > Tipovi usluga.<br>Koristi se samo za generiranje poziva na broj.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[tip_usluge]" id="tip_usluge" value="<?php echo esc_attr( $tip_usluge ); ?>" autocorrect="off" autocomplete="off" maxlength="2" placeholder="" class="small-text int">
              <p class="description"><?php echo esc_html__('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="jezik_"><?php echo esc_html__('Jezik', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Odaberi jezik na kojem želiš kreirati račun ili ponudu.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <select name="solo_woocommerce_postavke[jezik_]">
<?php
		$languages = [__('Hrvatski', 'solo-for-woocommerce') => 1, __('Engleski', 'solo-for-woocommerce') => 2, __('Njemački', 'solo-for-woocommerce') => 3, __('Francuski', 'solo-for-woocommerce') => 4, __('Talijanski', 'solo-for-woocommerce') => 5, __('Španjolski', 'solo-for-woocommerce') => 6];

		foreach ($languages as $key => $value) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $jezik_, $value, false ), esc_html( $key ) );}
?>
              </select>
            </td>
          </tr>
          <tr>
            <th>
              <label for="prikazi_porez"><?php echo esc_html__('Prikaži porez', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Uključi ako želiš prikazati PDV na računu ili ponudi.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <fieldset>
                <label for="prikazi_porez"><input type="checkbox" name="solo_woocommerce_postavke[prikazi_porez]" id="prikazi_porez" value="1"<?php if ($prikazi_porez==1) echo ' checked="checked"' ?>> <?php echo esc_html__('Da', 'solo-for-woocommerce'); ?></label>
                <p class="description"><?php echo esc_html__('Obavezno uključi ako si u sustavu PDV-a.', 'solo-for-woocommerce'); ?></p>
              </fieldset>
            </td>
          </tr>
          <tr>
            <th>
              <label for="tip_racuna"><?php echo esc_html__('Tip računa', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Odaberi zadani tip računa.', 'solo-for-woocommerce'); ?>">?</sup></label>
            </th>
            <td>
              <select name="solo_woocommerce_postavke[tip_racuna]">
<?php
		$types = ['R' => 1, 'R1' => 2, 'R2' => 3, __('bez oznake', 'solo-for-woocommerce') => 4, __('Avansni', 'solo-for-woocommerce') => 5];

		foreach ($types as $key => $value) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $tip_racuna, $value, false ), esc_html( $key ) );}
?>
              </select>
              <p class="description"><?php echo esc_html__('Odnosi se samo na račune. Ponude nemaju tipove.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th>
              <label for="rok_placanja"><?php echo esc_html__('Rok plaćanja', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Upiši broj dana koji se dodaje na datum izrade računa ili ponude, a do kojeg kupac treba platiti.<br>Ako nije upisano, Solo će staviti zadani broj dana za rok plaćanja (7) ili će kopirati s prethodnog računa ili ponude.', 'solo-for-woocommerce'); ?>"></sup></label>
            </th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[rok_placanja]" id="rok_placanja" value="<?php echo esc_attr( $rok_placanja ); ?>" autocorrect="off" autocomplete="off" maxlength="2" placeholder="" class="small-text int">
              <p class="description"><?php echo esc_html__('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="napomene_racun"><?php echo esc_html__('Napomene na računu', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Upiši napomene koje će se pojaviti na svakom računu.<br>Solo prihvaća do najviše 1000 znakova.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[napomene_racun]" id="napomene_racun" rows="2" maxlength="1000" class="large-text"><?php echo esc_textarea( $napomene_racun ); ?></textarea>
              <p class="description"><?php echo esc_html__('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="napomene_ponuda"><?php echo esc_html__('Napomene na ponudi', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Upiši napomene koje će se pojaviti na svakoj ponudi.<br>Solo prihvaća do najviše 1000 znakova.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[napomene_ponuda]" id="napomene_ponuda" rows="2" maxlength="1000" class="large-text"><?php echo esc_textarea( $napomene_ponuda ); ?></textarea>
              <p class="description"><?php echo esc_html__('Nije obavezno upisati.', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
          <tr>
            <th><label for="iban"><?php echo esc_html__('IBAN za uplatu', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Odaberi IBAN (tvoj žiro račun) koji će se pojaviti na računu ili ponudi.<br>IBAN možeš mijenjati u web sučelju > Postavke > Moja tvrtka.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <select name="solo_woocommerce_postavke[iban]">
<?php
		$ibans = [__('Glavni IBAN', 'solo-for-woocommerce') => 1, __('Drugi IBAN (ako postoji)', 'solo-for-woocommerce') => 2];

		foreach ($ibans as $key => $value) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $iban, $value, false ), esc_html( $key ) );}
?>
              </select>
            </td>
          </tr>
        
          <tr>
            <th><label for="telemetry"><?php echo esc_html__( 'Anonimna dijagnostika', 'solo-for-woocommerce' ); ?></label></th>
            <td>
              <fieldset>
                <input type="hidden" name="solo_woocommerce_postavke[telemetry]" value="0">
                <label><input type="checkbox" id="telemetry" name="solo_woocommerce_postavke[telemetry]" value="1" <?php checked( ( '' === $telemetry ? 1 : (int) $telemetry ), 1 ); ?>> <?php echo esc_html__( 'Dozvoli', 'solo-for-woocommerce' ); ?></label>
                <p class="description"><?php echo esc_html__( 'Ako je uključeno, dodatak može poslati anonimne tehničke informacije (verzije sustava) radi poboljšanja stabilnosti. Možeš isključiti u bilo kojem trenutku ili definirati SOLO_WOOCOMMERCE_DISABLE_TELEMETRY u wp-config.php.', 'solo-for-woocommerce' ); ?></p>
              </fieldset>
            </td>
          </tr>
          <tr>
            <th><label for="remove_data_on_uninstall"><?php echo esc_html__( 'Brisanje podataka', 'solo-for-woocommerce' ); ?></label></th>
            <td>
              <fieldset>
                <input type="hidden" name="solo_woocommerce_postavke[remove_data_on_uninstall]" value="0">
                <label><input type="checkbox" id="remove_data_on_uninstall" name="solo_woocommerce_postavke[remove_data_on_uninstall]" value="1" <?php checked( (int) $remove_data_on_uninstall, 1 ); ?>> <?php echo esc_html__( 'Obriši podatke pri deinstalaciji dodatka', 'solo-for-woocommerce' ); ?></label>
                <p class="description"><?php echo esc_html__( 'Ako je uključeno, prilikom brisanja dodatka obrisat će se postavke i arhiva poslanih narudžbi iz baze.', 'solo-for-woocommerce' ); ?></p>
              </fieldset>
            </td>
          </tr>
</tbody>
      </table>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		break;

	case 'akcije':
?>
      <input type="hidden" name="solo_woocommerce_postavke[prikazi_porez]" value="<?php echo esc_attr( $prikazi_porez ); ?>">
      <input type="hidden" name="solo_woocommerce_postavke[tip_racuna]" value="<?php echo esc_attr( $tip_racuna ); ?>">
      <input type="hidden" name="solo_woocommerce_postavke[posalji]" value="<?php echo esc_attr( $posalji ); ?>">
<?php
		// Show enabled WooCommerce gateways
		$gateways = WC()->payment_gateways->payment_gateways();
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ($gateways) {
?>
      <p><?php echo wp_kses_post( __( 'Namjesti postavke kreiranja računa ili ponude za svaki od prikazanih <a href="admin.php?page=wc-settings&tab=checkout" target="_blank" rel="noopener noreferrer">načina plaćanja</a>.', 'solo-for-woocommerce' ) ); ?></p>
<?php
			foreach ($gateways as $gateway) {
				$gateway_id = $gateway->id;
				$gateway_title = $gateway->title;
				$gateway_description = $gateway->method_description;

				// Mark active payment methods
				$color = '';
				if (is_array($available_gateways)) {
					foreach ($available_gateways as $available_gateway) {
						if ($gateway_id === $available_gateway->id) {
							$color = ' notice-alt notice-success';
							break;
						}
					}
				}

				// Beautify gateway names
				$translations = array(
					'bacs' => __('Uplata na žiro račun', 'solo-for-woocommerce'),
					'cheque' => __('Plaćanje čekom (fiskalizacija)', 'solo-for-woocommerce'),
					'cod' => __('Plaćanje pri pouzeću', 'solo-for-woocommerce'),
					'stripe' => __('Stripe (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'stripe_sepa' => __('Stripe SEPA uplata', 'solo-for-woocommerce'),
					'braintree_credit_card' => __('Braintree (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'braintree_paypal' => __('Braintree (PayPal)', 'solo-for-woocommerce'),
					'paypal' => __('PayPal', 'solo-for-woocommerce'),
					'ppec_paypal' => __('PayPal', 'solo-for-woocommerce'),
					'ppcp-gateway' => __('PayPal', 'solo-for-woocommerce'),
					'corvuspay' => __('CorvusPay (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'monri' => __('Monri (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'mypos_virtual' => __('myPOS (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'wooplatnica-croatia' => __('Uplatnica', 'solo-for-woocommerce'),
					'erste-kekspay-woocommerce' => __('KEKS Pay', 'solo-for-woocommerce'),
					'eh_paypal_express' => __('PayPal Express (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'revolut_cc' => __('Revolut (kartice, fiskalizacija)', 'solo-for-woocommerce'),
					'aircash-woocommerce' => __('Aircash (kartice, fiskalizacija)', 'solo-for-woocommerce')
				);

				// Show only available payments
				if (isset($translations[$gateway_id])) {
					$gateway_title = $translations[$gateway_id];

					// Dynamic variable error handling
					if (isset(${$gateway_id . '1'})) {
						$dynamic_var1 = ${$gateway_id . '1'};
						$dynamic_var2 = ${$gateway_id . '2'};
					} else {
						$dynamic_var1 = $dynamic_var2 = '';
					}
?>
      <div class="card<?php echo esc_attr( $color ); ?>">
        <h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $gateway_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $gateway_title ); ?></a></h3>
        <p><?php echo wp_kses_post( $gateway_description ); ?></p>
        <hr>
        <label for="<?php echo esc_attr($gateway_id); ?>"><?php echo esc_html__('Automatski kreiraj', 'solo-for-woocommerce'); ?></label>
        <select name="solo_woocommerce_postavke[<?php echo esc_attr($gateway_id); ?>1]" id="<?php echo esc_attr($gateway_id); ?>">
<?php
					$types = [__('ništa', 'solo-for-woocommerce') => '', __('račun', 'solo-for-woocommerce') => 'racun', __('ponudu', 'solo-for-woocommerce') => 'ponuda'];

					foreach ($types as $key => $value) {
						printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $dynamic_var1, $value, false ), esc_html( $key ) );}
?>
        </select>
        <label for="<?php echo esc_attr($gateway_id); ?>"><?php echo esc_html__('kada', 'solo-for-woocommerce'); ?></label>
        <select name="solo_woocommerce_postavke[<?php echo esc_attr($gateway_id); ?>2]" id="<?php echo esc_attr($gateway_id); ?>">
<?php
					$actions = ["primiš narudžbu (bez uplate)" => 1, "kupac uplati" => 2];

					foreach ($actions as $key => $value) {
						printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $dynamic_var2, $value, false ), esc_html( $key ) );}
?>
        </select>
      </div>
<?php
				}
			}
?>
      <br>
      <div class="notice notice-info inline">
        <p><?php echo esc_html__('Akcija <b>"primiš narudžbu (bez uplate)"</b> se izvršava čim kupac napravi narudžbu neovisno o tipu plaćanja. Takve narudžbe će imati status <span class="status processing">Processing / U obradi</span> ili <span class="status on-hold">On hold / Na čekanju</span> u WooCommerce popisu narudžbi.', 'solo-for-woocommerce'); ?></p>
        <p><?php echo esc_html__('Akcija <b>"kupac uplati"</b> se izvršava kada narudžbu obilježiš kao <span class="status completed">Completed / Završeno</span> u WooCommerce popisu narudžbi ili kada naplata karticom bude uspješna (trebalo bi automatski promijeniti status).', 'solo-for-woocommerce'); ?></p>
      </div>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		} else {
?>
      <br>
      <div class="notice notice-error inline"><p><?php echo wp_kses_post( __( 'Prvo uključi barem jedan način plaćanja u <a href="admin.php?page=wc-settings&tab=checkout" target="_blank" rel="noopener noreferrer">WooCommerce postavkama</a>.', 'solo-for-woocommerce' ) ); ?></p></div>
<?php
		}
		break;

	case 'email':

		// Cron check
		if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
?>
      <br>
      <div class="notice notice-error inline"><p><?php echo esc_html__('Za automatsko slanje računa ili ponude na e-mail, potrebno je izbrisati <code>define(\'DISABLE_WP_CRON\', true);</code> iz <i>wp-config.php</i> datoteke.', 'solo-for-woocommerce'); ?></p></div>
<?php
		} else {
			$known_smtp_plugins = array(
				// WP Mail SMTP
				'wp-mail-smtp/wp_mail_smtp.php',
				// Post SMTP
				'post-smtp/postman-smtp.php',
				// Easy WP SMTP
				'easy-wp-smtp/easy-wp-smtp.php',
				// FluentSMTP
				'fluent-smtp/fluent-smtp.php',
				// SureMail
				'suremails/suremails.php',
				// SMTP Mailer
				'smtp-mailer/main.php',
				// WP SMTP Mailer - SMTP7
				'wp-mail-smtp-mailer/wp-mail-smtp-mailer.php',
				// YaySMTP and Email Logs
				'yaysmtp/yay-smtp.php',
				// Site Mailer
				'site-mailer/site-mailer.php',
				// SMTP by BestWebSoft
				'bws-smtp/bws-smtp.php',
				// Swift SMTP (formerly Welcome Email Editor)
				'welcome-email-editor/sb_welcome_email_editor.php',
				// Configure SMTP
				'configure-smtp/configure-smtp.php',
				// Bit SMTP
				'bit-smtp/bit_smtp.php',
			);

			// Retrieve the list of active plugins
			$active_plugins = (array) get_option('active_plugins', array());

			// Optionally include network activated plugins on multisite installs
			if (is_multisite()) {
				$active_plugins = array_merge($active_plugins, array_keys(get_site_option('active_sitewide_plugins', array())));
			}

			$smtp_plugin_active = false;

			foreach ($known_smtp_plugins as $plugin_file) {
				if (in_array($plugin_file, $active_plugins, true)) {
					$smtp_plugin_active = true;
					break;
				}
			}

			if (!$smtp_plugin_active) {
?>
      <br>
      <div class="notice notice-error inline"><p><?php echo wp_kses_post( __( 'Potrebno je instalirati (i aktivirati) SMTP dodatak za WordPress (npr. <a href="https://wordpress.org/plugins/smtp-mailer/" target="_blank" rel="noopener noreferrer">SMTP Mailer</a>) kako bi se račun ili ponuda automatski poslali kupcu na e-mail.', 'solo-for-woocommerce' ) ); ?></p></div>
<?php
			}
?>
      <input type="hidden" name="solo_woocommerce_postavke[prikazi_porez]" value="<?php echo esc_attr( $prikazi_porez ); ?>">
      <input type="hidden" name="solo_woocommerce_postavke[tip_racuna]" value="<?php echo esc_attr( $tip_racuna ); ?>">
      <table class="form-table">
        <tbody>
          <tr>
            <th><label for="posalji"><?php echo esc_html__('Automatsko slanje', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Uključi ako želiš da se račun ili ponuda automatski pošalju e-mailom kupcu nakon uspješne kupnje ili narudžbe.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <fieldset>
                <label for="posalji"><input type="checkbox" name="solo_woocommerce_postavke[posalji]" id="posalji" value="1"<?php if ($posalji==1) echo ' checked="checked"' ?>> <?php echo esc_html__('Da', 'solo-for-woocommerce'); ?></label>
              </fieldset>
            </td>
          </tr>
          <tr>
            <th><label for="naslov"><?php echo esc_html__('Naslov poruke', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Upiši naslov e-mail poruke koju će kupac dobiti.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <input type="text" name="solo_woocommerce_postavke[naslov]" id="naslov" value="<?php echo esc_attr( $naslov ); ?>" autocorrect="off" autocomplete="off" maxlength="100" placeholder="" class="regular-text">
            </td>
          </tr>
          <tr>
            <th><label for="poruka"><?php echo esc_html__('Sadržaj poruke', 'solo-for-woocommerce'); ?><sup class="tooltip" title="<?php echo esc_attr__('Upiši sadržaj e-mail poruke koju će kupac dobiti.<br>HTML formatiranje nije podržano.', 'solo-for-woocommerce'); ?>"></sup></label></th>
            <td>
              <textarea name="solo_woocommerce_postavke[poruka]" id="poruka" rows="8" class="large-text"><?php echo esc_textarea( $poruka ); ?></textarea>
              <p class="description">*<?php echo esc_html__('PDF kopija dokumenta će automatski biti u privitku', 'solo-for-woocommerce'); ?></p>
            </td>
          </tr>
        </tbody>
      </table>
      <?php submit_button(__('Spremi promjene', 'solo-for-woocommerce')); ?>
<?php
		}

		break;

	case 'tecaj':

		// Display exchange rate
		solo_woocommerce_exchange(3);

		break;

	case 'arhiva':

		// Check for table in database.
		global $wpdb;

		$table_name = preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix . 'solo_woocommerce' );

		$cache_key    = 'solo_woocommerce_table_exists';
		$table_exists = wp_cache_get( $cache_key, 'solo-for-woocommerce' );

		if ( false === $table_exists ) {
			$table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_set( $cache_key, $table_exists, 'solo-for-woocommerce', 300 );
		}

		if ( ! $table_exists ) {
			solo_woocommerce_create_table();
		}

		// Table name is derived from $wpdb->prefix and strictly sanitized above.
		// Identifier placeholders (%i) are only available in WP 6.2+, so we safely interpolate a sanitized identifier.
		$results = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if (array_filter($results)) {
?>
      <p><?php echo wp_kses_post( __( 'Prikazane su sve narudžbe koje je WooCommerce poslao u servis Solo. Imaj na umu da WooCommerce šalje samo narudžbe za koje je u <a href="?page=solo-woocommerce&tab=akcije">"Načini plaćanja i akcije"</a> omogućeno kreiranje dokumenta.', 'solo-for-woocommerce' ) ); ?></p>
      <table class="widefat fixed striped" id="arhiva">
        <colgroup>
          <col style="width:9%;">
          <col style="width:29%;">
          <col style="width:29%;">
          <col style="width:11%;">
          <col style="width:11%;">
          <col style="width:11%;">
        </colgroup>
        <thead>
          <tr>
            <th data-sortas="numeric"><?php echo esc_html__('Narudžba', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="case-insensitive"><?php echo esc_html__('API zahtjev', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="case-insensitive"><?php echo esc_html__('API odgovor', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="datetime"><?php echo esc_html__('Datum zahtjeva', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="datetime"><?php echo esc_html__('Datum odgovora', 'solo-for-woocommerce'); ?></th>
            <th data-sortas="datetime"><?php echo esc_html__('Datum slanja', 'solo-for-woocommerce'); ?></th>
          </tr>
        </thead>
        <tbody>
<?php
			function solo_woocommerce_solo_woocommerce_timeago($datetime) {
				$seconds_ago = (time() - strtotime($datetime . ' Europe/Zagreb'));
				$prefix = 'Prije ';
				$when = $suffix = '';
				if ($seconds_ago >= 31536000) {
					return;
				} elseif ($seconds_ago>=2419200) {
					return;
				} elseif ($seconds_ago>=86400) {
					return;
				} elseif ($seconds_ago>=3600) {
					$when = intval($seconds_ago / 3600);
					if ($when==1) {
						$suffix = ' sat';
					} elseif ($when>1 && $when<5) {
						$suffix = ' sata';
					} else {
						$suffix = ' sati';
					}
				} elseif ($seconds_ago>=120) {
					$when = intval($seconds_ago / 60);
					if ($when==1) {
						$suffix = ' minutu';
					} elseif ($when>1 && $when<5) {
						$suffix = ' minute';
					} else {
						$suffix = ' minuta';
					}
				} elseif ($seconds_ago>=60) {
					$prefix = 'Prije minutu';
				} elseif ($seconds_ago>=0) {
					$prefix = 'Upravo sada';
				} else {
					return;
				}
				return $prefix . $when . $suffix;
			}

			foreach($results as $row) {
				$api_request = $row->api_request;
				$api_request = preg_replace('/token=[a-zA-Z0-9]{33}/', 'token=*****************************', $api_request);
				$api_request = nl2br( esc_html( $api_request ) );
				$api_response = $row->api_response;
				//$api_response = str_replace(' ', '&nbsp;', $api_response);
				$api_response = nl2br( esc_html( $api_response ) );
				$created = $row->created;
				$updated = $row->updated;
				if (!$updated || $updated=='0000-00-00 00:00:00') $updated = '–';
				$sent = $row->sent;
				if (!$sent || $sent=='0000-00-00 00:00:00') $sent = '–';
?>
          <tr class="shrink">
            <td data-sortvalue="<?php echo esc_attr( $row->order_id ); ?>"><p><a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $row->order_id ) . '&action=edit' ) ); ?>"><?php echo esc_html( $row->order_id ); ?></a></p></td>
            <td><p><?php echo wp_kses_post( $api_request ); ?></p></td>
            <td><p><?php echo wp_kses_post( $api_response ); ?></p></td>
            <td data-sortvalue="<?php echo esc_attr( $created ); ?>"><p><?php echo esc_html( $created ) . '<br>' . esc_html( (string) solo_woocommerce_timeago( $created ) ); ?></p></td>
            <td data-sortvalue="<?php echo esc_attr( $updated ); ?>"><p><?php echo esc_html( $updated ) . '<br>' . esc_html( (string) solo_woocommerce_timeago( $updated ) ); ?></p></td>
            <td data-sortvalue="<?php echo esc_attr( $sent ); ?>"><p><?php echo esc_html( $sent ) . '<br>' . esc_html( (string) solo_woocommerce_timeago( $sent ) ); ?></p></td>
          </tr>
<?php
				}
?>
        </tbody>
      </table>
<?php
		} else {
?>
      <br><div class="notice notice-warning inline"><p><?php echo esc_html__('Još niti jedna narudžba nije poslana u Solo.', 'solo-for-woocommerce'); ?></p></div>
<?php
		}
		break;

	case 'podrska':
?>
      <p><?php echo wp_kses_post( __( 'Tehnička podrška za ovaj dodatak nalazi se na <a href="https://github.com/coax/solo-for-woocommerce#podrška" target="_blank" rel="noopener noreferrer">GitHub stranicama</a>.', 'solo-for-woocommerce' ) ); ?></p>
      <p><?php echo esc_html__('Imaš instaliranu verziju', 'solo-for-woocommerce'); ?> <b><?php echo esc_html( SOLO_VERSION ); ?></b></p>
<?php
		// Debug flags.
		$is_wp_debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$is_wp_debug_log_enabled = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

		// Path to debug.log
		$log_file = WP_CONTENT_DIR . '/debug.log';

		// Logging disabled
		if (!$is_wp_debug_enabled || !$is_wp_debug_log_enabled) {
?>
      <div class="notice notice-info inline">
        <p><?php echo esc_html__('Ako imaš problema s dodatkom, omogući <i>debugiranje</i> u WordPressu za dobivanje informacija o tome gdje i kako dolazi do greške. U <i>wp-config.php</i> datoteku dodaj ove linije:<br><code>define(\'WP_DEBUG\', true);</code><br><code>define(\'WP_DEBUG_LOG\', true);</code><br><code>define(\'WP_DEBUG_DISPLAY\', false);</code>', 'solo-for-woocommerce'); ?></p>
      </div>
<?php
		} else {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;

			if ( $wp_filesystem && $wp_filesystem->exists( $log_file ) ) {
				$file_contents = $wp_filesystem->get_contents( $log_file );

				if ( false !== $file_contents ) {
					$filtered_lines = array();
					$raw_lines      = preg_split( "/\r\n|\n|\r/", $file_contents );

					foreach ( $raw_lines as $line ) {
						if ( preg_match( '/^\[\d{2}-\w{3}-\d{4}(?: \d{2}:\d{2}:\d{2})?/', $line ) ) {
							if ( isset( $solo_woocommerce->plugin_name ) && false !== strpos( $line, $solo_woocommerce->plugin_name ) ) {
								$filtered_lines[] = $line;
							}
						}
					}

					if ( ! empty( $filtered_lines ) ) {
						$filtered_lines = array_slice( $filtered_lines, -50, 50 );
						?>
						<p>
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: URL to debug.log (if publicly accessible). */
									__( 'Greške iz <a href="%s" target="_blank" rel="noopener noreferrer">debug.log</a> (zadnjih 50 zapisa):', 'solo-for-woocommerce' ),
									esc_url( content_url( 'debug.log' ) )
								)
						);
							?>
						</p>
						<table class="widefat fixed striped">
							<tbody>
								<?php foreach ( $filtered_lines as $filtered_line ) : ?>
									<?php
									preg_match( '/\[(.*?)\]/', $filtered_line, $matches );

									$date_time = isset( $matches[1] ) ? $matches[1] : '';
									$date_time = str_replace( '/', '-', $date_time );
									$date_time = str_replace( ':', '-', $date_time );
									$date_time = str_replace( ' ', '-', $date_time );

									$date = DateTime::createFromFormat( 'd-M-Y H-i-s', $date_time, new DateTimeZone( 'UTC' ) );
									if ( ! $date ) {
										$date = DateTime::createFromFormat( 'd-M-Y', $date_time, new DateTimeZone( 'UTC' ) );
									}

									$local_timezone = wp_timezone();
									if ( $date ) {
										$date->setTimezone( $local_timezone );
										$formatted_date_time = $date->format( 'd.m.Y. H:i:s' );
									} else {
										$formatted_date_time = $date_time;
									}

									$error_message = trim( str_replace( $matches[0] ?? '', '', $filtered_line ) );
									?>
									<tr>
										<td><code><?php echo esc_html( $formatted_date_time ); ?></code></td>
										<td><code><?php echo esc_html( $error_message ); ?></code></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php
					} else {
						echo '<p>' . esc_html__( 'Nema grešaka u debug.log datoteci vezanih uz Solo for WooCommerce.', 'solo-for-woocommerce' ) . '</p>';
					}
				}
			} else {
				echo '<p>' . esc_html__( 'Datoteka debug.log nije pronađena.', 'solo-for-woocommerce' ) . '</p>';
			}
		}

		break;
endswitch;
?>
    </div>
  </form>
</div>
