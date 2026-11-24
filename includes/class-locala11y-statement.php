<?php
/**
 * EAA & WCAG 2.2 Accessibility Statement Generator.
 *
 * Provides shortcode [locala11y_statement] and one-click page publishing.
 *
 * @package LocalA11y
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LocalA11y_Statement
 */
class LocalA11y_Statement {

	/**
	 * Option key for statement settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'locala11y_statement_settings';

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'locala11y_statement', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Default statement settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'language'            => 'en',
			'organization_name'   => get_bloginfo( 'name' ),
			'website_url'         => home_url( '/' ),
			'standard'            => 'WCAG 2.2 Level AA / EN 301 549 (EAA)',
			'conformance_status'  => 'partially',
			'contact_email'       => get_option( 'admin_email' ),
			'contact_phone'       => '',
			'response_time'       => '5 business days',
			'enforcement_body'    => '',
			'known_limitations'   => '',
			'include_scan_issues' => 1,
			'last_reviewed'       => gmdate( 'Y-m-d' ),
			'statement_page_id'   => 0,
		);
	}

	/**
	 * Get merged statement settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Sanitize and save statement settings.
	 *
	 * @param array $raw Raw POST array.
	 * @return array Sanitized settings.
	 */
	public static function save_settings( $raw ) {
		$current = self::get_settings();

		$language = isset( $raw['language'] ) && in_array( $raw['language'], array( 'en', 'fr' ), true )
			? $raw['language']
			: 'en';

		$conformance = isset( $raw['conformance_status'] ) && in_array( $raw['conformance_status'], array( 'fully', 'partially', 'non' ), true )
			? $raw['conformance_status']
			: 'partially';

		$clean = array(
			'language'            => $language,
			'organization_name'   => sanitize_text_field( $raw['organization_name'] ?? '' ),
			'website_url'         => esc_url_raw( $raw['website_url'] ?? home_url( '/' ) ),
			'standard'            => sanitize_text_field( $raw['standard'] ?? 'WCAG 2.2 Level AA / EN 301 549 (EAA)' ),
			'conformance_status'  => $conformance,
			'contact_email'       => sanitize_email( $raw['contact_email'] ?? '' ),
			'contact_phone'       => sanitize_text_field( $raw['contact_phone'] ?? '' ),
			'response_time'       => sanitize_text_field( $raw['response_time'] ?? '5 business days' ),
			'enforcement_body'    => sanitize_textarea_field( $raw['enforcement_body'] ?? '' ),
			'known_limitations'   => sanitize_textarea_field( $raw['known_limitations'] ?? '' ),
			'include_scan_issues' => ! empty( $raw['include_scan_issues'] ) ? 1 : 0,
			'last_reviewed'       => sanitize_text_field( $raw['last_reviewed'] ?? gmdate( 'Y-m-d' ) ),
			'statement_page_id'   => absint( $current['statement_page_id'] ?? 0 ),
		);

		update_option( self::OPTION_KEY, $clean );
		return $clean;
	}

	/**
	 * Create or update the published WordPress Accessibility Statement page.
	 *
	 * @return int|\WP_Error Page ID or WP_Error.
	 */
	public static function publish_statement_page() {
		$settings = self::get_settings();
		$page_id  = absint( $settings['statement_page_id'] );
		$title    = ( 'fr' === $settings['language'] )
			? __( 'Déclaration d’accessibilité', 'locala11y' )
			: __( 'Accessibility Statement', 'locala11y' );

		$post_data = array(
			'post_title'   => $title,
			'post_content' => '[locala11y_statement]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		);

		if ( $page_id > 0 && get_post( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
			$post_data['ID'] = $page_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( ! is_wp_error( $result ) ) {
			$settings['statement_page_id'] = (int) $result;
			update_option( self::OPTION_KEY, $settings );
		}

		return $result;
	}

	/**
	 * Render the Accessibility Statement HTML.
	 *
	 * @return string
	 */
	public static function render_shortcode() {
		$s  = self::get_settings();
		$fr = ( 'fr' === $s['language'] );

		$status_labels = $fr
			? array(
				'fully'     => 'Totalement conforme : le contenu respecte intégralement la norme d’accessibilité sans exception.',
				'partially' => 'Partiellement conforme : certaines parties du contenu ne respectent pas encore entièrement la norme d’accessibilité.',
				'non'       => 'Non conforme : le contenu ne respecte pas encore la norme d’accessibilité et un plan de mise en conformité est en cours.',
			)
			: array(
				'fully'     => 'Fully conformant: the content fully conforms to the accessibility standard without any exceptions.',
				'partially' => 'Partially conformant: some parts of the content do not yet fully conform to the accessibility standard.',
				'non'       => 'Non-conformant: the content does not yet conform to the accessibility standard and remediation work is underway.',
			);

		$status_text = $status_labels[ $s['conformance_status'] ] ?? $status_labels['partially'];
		$limitations = ! empty( $s['include_scan_issues'] ) ? LocalA11y_DB::get_top_open_limitations( 8 ) : array();

		ob_start();
		?>
		<section class="locala11y-statement" aria-labelledby="locala11y-statement-title">
			<h2 id="locala11y-statement-title">
				<?php echo esc_html( $fr ? 'Déclaration d’accessibilité' : 'Accessibility Statement' ); ?>
			</h2>

			<p>
				<?php
				if ( $fr ) {
					printf(
						/* translators: 1: Organization name, 2: Website URL */
						esc_html__( '%1$s s’engage à rendre son site web (%2$s) accessible conformément aux exigences d’accessibilité applicables (European Accessibility Act / Directive UE 2019/882).', 'locala11y' ),
						esc_html( $s['organization_name'] ),
						esc_html( $s['website_url'] )
					);
				} else {
					printf(
						/* translators: 1: Organization name, 2: Website URL */
						esc_html__( '%1$s is committed to ensuring digital accessibility for people with disabilities on %2$s, in line with the European Accessibility Act (EU Directive 2019/882) and WCAG guidelines.', 'locala11y' ),
						esc_html( $s['organization_name'] ),
						esc_html( $s['website_url'] )
					);
				}
				?>
			</p>

			<h3><?php echo esc_html( $fr ? 'État de conformité' : 'Conformance Status' ); ?></h3>
			<p>
				<strong><?php echo esc_html( $s['standard'] ); ?></strong> —
				<?php echo esc_html( $status_text ); ?>
			</p>

			<?php if ( ! empty( $s['known_limitations'] ) || ! empty( $limitations ) ) : ?>
				<h3><?php echo esc_html( $fr ? 'Limitations connues et points en cours de correction' : 'Known Limitations & Ongoing Remediation' ); ?></h3>
				<?php if ( ! empty( $s['known_limitations'] ) ) : ?>
					<p><?php echo nl2br( esc_html( $s['known_limitations'] ) ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $limitations ) ) : ?>
					<p>
						<?php
						echo esc_html(
							$fr
								? 'Notre dernier audit automatisé interne a identifié les points suivants actuellement en cours de correction :'
								: 'Our latest internal automated audit identified the following items currently being remediated:'
						);
						?>
					</p>
					<ul>
						<?php foreach ( $limitations as $item ) : ?>
							<li>
								<strong><?php echo esc_html( $item['help_text'] ); ?></strong>
								(<?php echo esc_html( $item['rule_id'] ); ?> —
								<?php
								printf(
									/* translators: %d: number of detected instances */
									esc_html( $fr ? '%d occurrence(s)' : '%d instance(s)' ),
									absint( $item['occurrences'] )
								);
								?>)
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php endif; ?>

			<h3><?php echo esc_html( $fr ? 'Retour d’information et contact' : 'Feedback & Contact Information' ); ?></h3>
			<p>
				<?php
				echo esc_html(
					$fr
						? 'Si vous rencontrez un obstacle d’accessibilité sur ce site, contactez-nous :'
						: 'If you encounter an accessibility barrier on this website, please let us know:'
				);
				?>
			</p>
			<ul>
				<?php if ( ! empty( $s['contact_email'] ) ) : ?>
					<li>
						<?php echo esc_html( $fr ? 'E-mail :' : 'Email:' ); ?>
						<a href="<?php echo esc_url( 'mailto:' . $s['contact_email'] ); ?>"><?php echo esc_html( $s['contact_email'] ); ?></a>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $s['contact_phone'] ) ) : ?>
					<li>
						<?php echo esc_html( $fr ? 'Téléphone :' : 'Phone:' ); ?>
						<?php echo esc_html( $s['contact_phone'] ); ?>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $s['response_time'] ) ) : ?>
					<li>
						<?php echo esc_html( $fr ? 'Délai de réponse habituel :' : 'Typical response time:' ); ?>
						<?php echo esc_html( $s['response_time'] ); ?>
					</li>
				<?php endif; ?>
			</ul>

			<?php if ( ! empty( $s['enforcement_body'] ) ) : ?>
				<h3><?php echo esc_html( $fr ? 'Voies de recours' : 'Enforcement Procedure' ); ?></h3>
				<p><?php echo nl2br( esc_html( $s['enforcement_body'] ) ); ?></p>
			<?php endif; ?>

			<h3><?php echo esc_html( $fr ? 'Méthode d’évaluation' : 'Assessment Approach' ); ?></h3>
			<p>
				<?php
				echo esc_html(
					$fr
						? 'Ce site fait l’objet d’une auto-évaluation combinant des audits automatisés locaux (axe-core) et des vérifications manuelles (navigation clavier, contraste et lecteur d’écran).'
						: 'This website is evaluated through self-assessment combining local automated audits (axe-core) and manual accessibility testing (keyboard navigation, contrast, and screen reader checks).'
				);
				?>
			</p>

			<p>
				<em>
					<?php
					printf(
						/* translators: %s: review date */
						esc_html( $fr ? 'Dernière mise à jour de cette déclaration : %s.' : 'This statement was last reviewed on %s.' ),
						esc_html( $s['last_reviewed'] )
					);
					?>
				</em>
			</p>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
