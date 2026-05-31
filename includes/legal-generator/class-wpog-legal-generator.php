<?php
/**
 * Legal Document Prompt Generator — data layer.
 *
 * Owns the {prefix}wpog_legal_prompts table (saved wizard prompts), the
 * pre-fill bridge that reads existing plugin configuration, and the static
 * reference maps (supervisory authorities, third-party data-transfer map)
 * shared with the wizard UI.
 *
 * The wizard itself runs entirely client-side (admin/assets/legal-generator.js);
 * the plugin never sends any data to an external AI service. Saving a prompt is
 * the only server interaction, handled through WPOG_Legal_REST.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPOG_Legal_Generator {

	/** Documents the wizard can be asked to generate. */
	const DOCS = array( 'privacy', 'cookie', 'terms' );

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wpog_legal_prompts';
	}

	/* --------------------------------------------------------------------- */
	/* Schema                                                                */
	/* --------------------------------------------------------------------- */

	public static function install_table() {
		global $wpdb;
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			prompt_name VARCHAR(255) NOT NULL,
			form_data LONGTEXT NOT NULL,
			prompt_text LONGTEXT NOT NULL,
			documents VARCHAR(64) NOT NULL DEFAULT '',
			language VARCHAR(5) NOT NULL DEFAULT 'IT',
			created_by BIGINT UNSIGNED DEFAULT 0,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			KEY created_at (created_at),
			KEY created_by (created_by)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Lazily create the table on first use so sites that upgrade without a
	 * (de)activation cycle still get the schema.
	 */
	public static function ensure_table() {
		static $checked = false;
		if ( $checked ) {
			return;
		}
		global $wpdb;
		$table    = self::table_name();
		$suppress = $wpdb->suppress_errors( true );
		$exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		$wpdb->suppress_errors( $suppress );
		if ( $exists !== $table ) {
			self::install_table();
		}
		$checked = true;
	}

	/* --------------------------------------------------------------------- */
	/* CRUD                                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * Persist a prompt. Returns the new row id or false on failure.
	 *
	 * @param array $data { prompt_name, form_data (array), prompt_text, documents (array|string), language }
	 */
	public static function save( array $data ) {
		global $wpdb;
		self::ensure_table();

		$name = sanitize_text_field( $data['prompt_name'] ?? '' );
		if ( '' === $name ) {
			$name = __( 'Untitled prompt', 'wp-opengdpr' );
		}
		$name = substr( $name, 0, 255 );

		$form_data   = is_array( $data['form_data'] ?? null ) ? $data['form_data'] : array();
		$prompt_text = (string) ( $data['prompt_text'] ?? '' );

		$documents = $data['documents'] ?? array();
		if ( is_string( $documents ) ) {
			$documents = array_filter( array_map( 'trim', explode( ',', $documents ) ) );
		}
		$documents = array_values( array_intersect( (array) $documents, self::DOCS ) );

		$language = strtoupper( sanitize_text_field( $data['language'] ?? 'IT' ) );
		$language = substr( $language, 0, 5 );

		$now = current_time( 'mysql' );

		$ok = $wpdb->insert(
			self::table_name(),
			array(
				'prompt_name' => $name,
				'form_data'   => wp_json_encode( $form_data ),
				'prompt_text' => $prompt_text,
				'documents'   => implode( ',', $documents ),
				'language'    => $language,
				'created_by'  => get_current_user_id(),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return false === $ok ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Return a list of saved prompts (most recent first). Excludes the heavy
	 * form_data/prompt_text payloads unless $with_payload is true.
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		self::ensure_table();

		$args = array_merge( array(
			'per_page' => 50,
			'page'     => 1,
		), $args );

		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );
		$table    = self::table_name();

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, prompt_name, documents, language, created_by, created_at, updated_at
				 FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array( 'rows' => (array) $rows, 'total' => $total );
	}

	/** Return one prompt (full payload, form_data decoded) or null. */
	public static function get( $id ) {
		global $wpdb;
		self::ensure_table();
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', (int) $id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row['form_data'] = json_decode( (string) $row['form_data'], true );
		if ( ! is_array( $row['form_data'] ) ) {
			$row['form_data'] = array();
		}
		return $row;
	}

	public static function delete( $id ) {
		global $wpdb;
		self::ensure_table();
		return (bool) $wpdb->delete( self::table_name(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/* --------------------------------------------------------------------- */
	/* Pre-fill bridge                                                       */
	/* --------------------------------------------------------------------- */

	/**
	 * Collect pre-fill data from existing plugin modules and the WP site so the
	 * wizard can suggest sensible defaults. All values are scalar/array safe to
	 * pass through wp_localize_script.
	 */
	public static function prefill_data() {
		$general   = WPOG_Settings::get( 'general' );
		$cats      = WPOG_Settings::get( 'categories' );
		$scripts   = WPOG_Settings::get( 'scripts' );
		$form_c    = WPOG_Settings::get( 'form_consent' );
		$terms_c   = WPOG_Settings::get( 'terms_consent' );

		$has_cookies = static function ( $cat ) use ( $cats ) {
			return ! empty( $cats[ $cat ]['cookies'] );
		};

		// Flatten configured custom scripts to their category hints so the
		// wizard can pre-tick matching third-party services.
		$script_categories = array();
		foreach ( (array) $scripts as $s ) {
			if ( ! empty( $s['category'] ) ) {
				$script_categories[] = sanitize_key( $s['category'] );
			}
		}

		return array(
			'site_url'     => get_site_url(),
			'admin_email'  => sanitize_email( (string) get_option( 'admin_email' ) ),
			'blog_name'    => sanitize_text_field( (string) get_option( 'blogname' ) ),
			'cookie' => array(
				'analytics_enabled'  => $has_cookies( 'analytics' ) ? 1 : 0,
				'marketing_enabled'  => $has_cookies( 'marketing' ) ? 1 : 0,
				'functional_enabled' => $has_cookies( 'functional' ) ? 1 : 0,
				'script_categories'  => array_values( array_unique( $script_categories ) ),
				'privacy_url'        => esc_url_raw( (string) ( $general['privacy_url'] ?? '' ) ),
				'cookie_url'         => esc_url_raw( (string) ( $general['cookie_url'] ?? '' ) ),
				'policy_version'     => sanitize_text_field( (string) ( $general['policy_version'] ?? '1.0' ) ),
			),
			'privacy' => array(
				'privacy_version' => sanitize_text_field( (string) ( $form_c['privacy_policy_version'] ?? '1.0' ) ),
			),
			'terms' => array(
				'enabled'       => ! empty( $terms_c['enabled'] ) ? 1 : 0,
				'terms_page_id' => (int) ( $terms_c['terms_page_id'] ?? 0 ),
				'minimum_age'   => (int) ( $terms_c['minimum_age'] ?? 18 ),
			),
			'has_ecommerce' => class_exists( 'WooCommerce' ) ? 1 : 0,
			'has_cf7'       => ( class_exists( 'WPCF7' ) || ! empty( $form_c['cf7_enabled'] ) ) ? 1 : 0,
		);
	}

	/* --------------------------------------------------------------------- */
	/* Reference maps (shared with the wizard for accurate document drafting) */
	/* --------------------------------------------------------------------- */

	/**
	 * Data-protection supervisory authority per ISO country code. Used to
	 * auto-populate the complaint authority in the generated documents.
	 */
	public static function supervisory_authorities() {
		return array(
			'IT' => array( 'name' => 'Garante per la Protezione dei Dati Personali', 'url' => 'https://www.garanteprivacy.it', 'address' => 'Piazza Venezia 11, 00187 Roma, Italy' ),
			'DE' => array( 'name' => 'Bundesbeauftragter für den Datenschutz und die Informationsfreiheit (BfDI)', 'url' => 'https://www.bfdi.bund.de', 'address' => 'Graurheindorfer Str. 153, 53117 Bonn, Germany' ),
			'FR' => array( 'name' => "Commission Nationale de l'Informatique et des Libertés (CNIL)", 'url' => 'https://www.cnil.fr', 'address' => '3 Place de Fontenoy, 75007 Paris, France' ),
			'ES' => array( 'name' => 'Agencia Española de Protección de Datos (AEPD)', 'url' => 'https://www.aepd.es', 'address' => 'C/ Jorge Juan 6, 28001 Madrid, Spain' ),
			'NL' => array( 'name' => 'Autoriteit Persoonsgegevens (AP)', 'url' => 'https://www.autoriteitpersoonsgegevens.nl', 'address' => 'Hoge Nieuwstraat 8, 2514 EL Den Haag, Netherlands' ),
			'BE' => array( 'name' => 'Autorité de protection des données (APD/GBA)', 'url' => 'https://www.dataprotectionauthority.be', 'address' => 'Rue de la Presse 35, 1000 Bruxelles, Belgium' ),
			'AT' => array( 'name' => 'Österreichische Datenschutzbehörde (DSB)', 'url' => 'https://www.dsb.gv.at', 'address' => 'Barichgasse 40-42, 1030 Wien, Austria' ),
			'PT' => array( 'name' => 'Comissão Nacional de Proteção de Dados (CNPD)', 'url' => 'https://www.cnpd.pt', 'address' => 'Av. D. Carlos I 134, 1200-651 Lisboa, Portugal' ),
			'IE' => array( 'name' => 'Data Protection Commission (DPC)', 'url' => 'https://www.dataprotection.ie', 'address' => '21 Fitzwilliam Square South, Dublin 2, Ireland' ),
			'PL' => array( 'name' => 'Urząd Ochrony Danych Osobowych (UODO)', 'url' => 'https://uodo.gov.pl', 'address' => 'ul. Stawki 2, 00-193 Warszawa, Poland' ),
			'SE' => array( 'name' => 'Integritetsskyddsmyndigheten (IMY)', 'url' => 'https://www.imy.se', 'address' => 'Box 8114, 104 20 Stockholm, Sweden' ),
			'DK' => array( 'name' => 'Datatilsynet', 'url' => 'https://www.datatilsynet.dk', 'address' => 'Carl Jacobsens Vej 35, 2500 Valby, Denmark' ),
			'FI' => array( 'name' => 'Tietosuojavaltuutetun toimisto', 'url' => 'https://tietosuoja.fi', 'address' => 'PL 800, 00531 Helsinki, Finland' ),
			'GR' => array( 'name' => 'Hellenic Data Protection Authority (HDPA)', 'url' => 'https://www.dpa.gr', 'address' => 'Kifissias 1-3, 11523 Athens, Greece' ),
			'CZ' => array( 'name' => 'Úřad pro ochranu osobních údajů (ÚOOÚ)', 'url' => 'https://www.uoou.cz', 'address' => 'Pplk. Sochora 27, 170 00 Praha 7, Czech Republic' ),
			'RO' => array( 'name' => 'Autoritatea Națională de Supraveghere a Prelucrării Datelor cu Caracter Personal (ANSPDCP)', 'url' => 'https://www.dataprotection.ro', 'address' => 'B-dul G-ral. Gh. Magheru 28-30, București, Romania' ),
			'HU' => array( 'name' => 'Nemzeti Adatvédelmi és Információszabadság Hatóság (NAIH)', 'url' => 'https://www.naih.hu', 'address' => 'Falk Miksa utca 9-11, 1055 Budapest, Hungary' ),
			'BG' => array( 'name' => 'Commission for Personal Data Protection (CPDP)', 'url' => 'https://www.cpdp.bg', 'address' => '2 Prof. Tsvetan Lazarov Blvd., 1592 Sofia, Bulgaria' ),
			'HR' => array( 'name' => 'Agencija za zaštitu osobnih podataka (AZOP)', 'url' => 'https://azop.hr', 'address' => 'Selska cesta 136, 10000 Zagreb, Croatia' ),
			'SK' => array( 'name' => 'Úrad na ochranu osobných údajov Slovenskej republiky', 'url' => 'https://dataprotection.gov.sk', 'address' => 'Hraničná 12, 820 07 Bratislava, Slovakia' ),
			'SI' => array( 'name' => 'Informacijski pooblaščenec (IP)', 'url' => 'https://www.ip-rs.si', 'address' => 'Dunajska cesta 22, 1000 Ljubljana, Slovenia' ),
			'LU' => array( 'name' => 'Commission nationale pour la protection des données (CNPD)', 'url' => 'https://cnpd.public.lu', 'address' => '15 Boulevard du Jazz, 4370 Belvaux, Luxembourg' ),
			'LT' => array( 'name' => 'Valstybinė duomenų apsaugos inspekcija (VDAI)', 'url' => 'https://vdai.lrv.lt', 'address' => 'L. Sapiegos g. 17, 10312 Vilnius, Lithuania' ),
			'LV' => array( 'name' => 'Datu valsts inspekcija (DVI)', 'url' => 'https://www.dvi.gov.lv', 'address' => 'Elijas iela 17, Riga, LV-1050, Latvia' ),
			'EE' => array( 'name' => 'Andmekaitse Inspektsioon (AKI)', 'url' => 'https://www.aki.ee', 'address' => 'Tatari 39, 10134 Tallinn, Estonia' ),
			'CY' => array( 'name' => 'Office of the Commissioner for Personal Data Protection', 'url' => 'http://www.dataprotection.gov.cy', 'address' => '1 Iasonos Street, 1082 Nicosia, Cyprus' ),
			'MT' => array( 'name' => 'Information and Data Protection Commissioner (IDPC)', 'url' => 'https://idpc.org.mt', 'address' => 'Floor 2, Airways House, Triq il-Kbira, Sliema SLM 1549, Malta' ),
			'NO' => array( 'name' => 'Datatilsynet (Norway)', 'url' => 'https://www.datatilsynet.no', 'address' => 'Postboks 458 Sentrum, 0105 Oslo, Norway' ),
			'IS' => array( 'name' => 'Persónuvernd', 'url' => 'https://www.personuvernd.is', 'address' => 'Rauðarárstígur 10, 105 Reykjavík, Iceland' ),
			'LI' => array( 'name' => 'Datenschutzstelle (DSS)', 'url' => 'https://www.datenschutzstelle.li', 'address' => 'Städtle 38, 9490 Vaduz, Liechtenstein' ),
			'GB' => array( 'name' => "Information Commissioner's Office (ICO)", 'url' => 'https://ico.org.uk', 'address' => 'Wycliffe House, Water Lane, Wilmslow, Cheshire SK9 5AF, United Kingdom' ),
			'CH' => array( 'name' => 'Eidgenössischer Datenschutz- und Öffentlichkeitsbeauftragter (FDPIC)', 'url' => 'https://www.edoeb.admin.ch', 'address' => 'Feldeggweg 1, 3003 Bern, Switzerland' ),
		);
	}
}
