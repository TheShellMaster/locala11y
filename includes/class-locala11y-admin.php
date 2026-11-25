<?php
/**
 * Admin UI, CSV Export, Manual Checklist, and Front-End Admin-Bar Quick Audit.
 *
 * @package LocalA11y
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LocalA11y_Admin
 */
class LocalA11y_Admin {

	/**
	 * Option key for manual EAA testing checklist.
	 *
	 * @var string
	 */
	const CHECKLIST_OPTION = 'locala11y_manual_checklist';

	/**
	 * Hook admin menus, assets, handlers, and admin-bar quick audit.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_locala11y_save_statement', array( __CLASS__, 'handle_save_statement' ) );
		add_action( 'admin_post_locala11y_publish_statement', array( __CLASS__, 'handle_publish_statement' ) );
		add_action( 'admin_post_locala11y_save_checklist', array( __CLASS__, 'handle_save_checklist' ) );
		add_action( 'admin_post_locala11y_export_csv', array( __CLASS__, 'handle_export_csv' ) );
		add_action( 'admin_post_locala11y_clear_scans', array( __CLASS__, 'handle_clear_scans' ) );

		// Admin-only front-end quick audit button (zero bytes for visitors).
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_button' ), 95 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_admin_assets' ) );
	}

	/**
	 * Register LocalA11y admin pages.
	 *
	 * @return void
	 */
	public static function register_menus() {
		add_menu_page(
			__( 'LocalA11y Audit', 'locala11y' ),
			__( 'LocalA11y', 'locala11y' ),
			'manage_options',
			'locala11y',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-universal-access-alt',
			81
		);

		add_submenu_page(
			'locala11y',
			__( 'Dashboard & Scanner', 'locala11y' ),
			__( 'Dashboard & Scanner', 'locala11y' ),
			'manage_options',
			'locala11y',
			array( __CLASS__, 'render_dashboard_page' )
		);

		add_submenu_page(
			'locala11y',
			__( 'Findings Explorer', 'locala11y' ),
			__( 'Findings Explorer', 'locala11y' ),
			'manage_options',
			'locala11y-findings',
			array( __CLASS__, 'render_findings_page' )
		);

		add_submenu_page(
			'locala11y',
			__( 'EAA Accessibility Statement', 'locala11y' ),
			__( 'EAA Statement', 'locala11y' ),
			'manage_options',
			'locala11y-statement',
			array( __CLASS__, 'render_statement_page' )
		);

		add_submenu_page(
			'locala11y',
			__( 'Manual EAA Checklist', 'locala11y' ),
			__( 'Manual Checklist', 'locala11y' ),
			'manage_options',
			'locala11y-checklist',
			array( __CLASS__, 'render_checklist_page' )
		);
	}

	/**
	 * Enqueue admin CSS & JS only on LocalA11y screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'locala11y' ) ) {
			return;
		}

		wp_enqueue_style(
			'locala11y-admin',
			LOCALA11Y_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			LOCALA11Y_VERSION
		);

		wp_enqueue_script(
			'locala11y-admin-scanner',
			LOCALA11Y_PLUGIN_URL . 'assets/js/admin-scanner.js',
			array(),
			LOCALA11Y_VERSION,
			true
		);

		$parsed_home = wp_parse_url( home_url() );
		$site_origin = '';
		if ( ! empty( $parsed_home['scheme'] ) && ! empty( $parsed_home['host'] ) ) {
			$site_origin = $parsed_home['scheme'] . '://' . $parsed_home['host'];
			if ( ! empty( $parsed_home['port'] ) ) {
				$site_origin .= ':' . (int) $parsed_home['port'];
			}
		}

		wp_localize_script(
			'locala11y-admin-scanner',
			'localA11yAdmin',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'locala11y/v1' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'axeUrl'     => esc_url_raw( LOCALA11Y_PLUGIN_URL . 'assets/vendor/axe-core/axe.min.js' ),
				'siteOrigin' => esc_url_raw( $site_origin ),
				'batchLimit' => max( 1, absint( apply_filters( 'locala11y_max_batch_urls', 100 ) ) ),
				'i18n'       => array(
					'loadingTargets'   => __( 'Loading published pages…', 'locala11y' ),
					'noTargetsFound'   => __( 'No published pages found.', 'locala11y' ),
					'noTargets'        => __( 'Select at least one page to scan.', 'locala11y' ),
					'scanning'         => __( 'Scanning', 'locala11y' ),
					'completed'        => __( 'Scan completed.', 'locala11y' ),
					'cancelled'        => __( 'Scan cancelled.', 'locala11y' ),
					'frameError'       => __( 'Could not access page iframe (check X-Frame-Options / security headers).', 'locala11y' ),
					'saved'            => __( 'Status updated.', 'locala11y' ),
					'pagesCount'       => __( 'page(s)', 'locala11y' ),
					'invalidCustomUrl' => __( 'Enter a valid URL on this same site.', 'locala11y' ),
					'duplicateUrl'     => __( 'This URL is already in the target list.', 'locala11y' ),
					'limitReached'     => __( 'Maximum batch size reached (100 URLs per browser run for stability).', 'locala11y' ),
					'copied'           => __( 'Copied!', 'locala11y' ),
					'copySelector'     => __( 'Copy selector', 'locala11y' ),
					'skippedNotice'    => __( 'Some pages could not be scanned in the background iframe (often due to X-Frame-Options or CSP headers). Open them directly and click "Audit A11y" in the admin bar:', 'locala11y' ),
					'reloadResults'    => __( 'Refresh page to view saved results', 'locala11y' ),
				),
			)
		);
	}

	/**
	 * Add "Audit A11y" button to front-end Admin Bar for admins only.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
	 * @return void
	 */
	public static function add_admin_bar_button( $wp_admin_bar ) {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'locala11y-quick-audit',
				'title' => '<span class="ab-icon dashicons dashicons-universal-access-alt" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Audit A11y', 'locala11y' ) . '</span>',
				'href'  => '#locala11y-audit',
				'meta'  => array(
					'title' => __( 'Run a local WCAG 2.2 accessibility audit on this page', 'locala11y' ),
				),
			)
		);
	}

	/**
	 * Load front-end quick audit drawer ONLY for logged-in administrators.
	 * Zero assets are ever loaded for normal visitors.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_admin_assets() {
		if ( is_admin() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}

		wp_enqueue_style(
			'locala11y-frontend-audit',
			LOCALA11Y_PLUGIN_URL . 'assets/css/frontend-audit.css',
			array(),
			LOCALA11Y_VERSION
		);

		wp_enqueue_script(
			'locala11y-axe-core',
			LOCALA11Y_PLUGIN_URL . 'assets/vendor/axe-core/axe.min.js',
			array(),
			LOCALA11Y_AXE_VERSION,
			true
		);

		wp_enqueue_script(
			'locala11y-frontend-audit',
			LOCALA11Y_PLUGIN_URL . 'assets/js/frontend-audit.js',
			array( 'locala11y-axe-core' ),
			LOCALA11Y_VERSION,
			true
		);

		wp_localize_script(
			'locala11y-frontend-audit',
			'localA11yFront',
			array(
				'dashboardUrl' => esc_url_raw( admin_url( 'admin.php?page=locala11y' ) ),
				'i18n'         => array(
					'title'             => __( 'LocalA11y — Page Audit (WCAG 2.2 AA)', 'locala11y' ),
					'running'           => __( 'Running local axe-core audit…', 'locala11y' ),
					'noIssues'          => __( 'No automated WCAG 2.2 AA violations detected on this page. Remember to also complete manual keyboard and screen-reader checks.', 'locala11y' ),
					'violations'        => __( 'Violations', 'locala11y' ),
					'needsReview'       => __( 'Needs Manual Review', 'locala11y' ),
					'highlight'         => __( 'Highlight element', 'locala11y' ),
					'close'             => __( 'Close', 'locala11y' ),
					'rerun'             => __( 'Re-run', 'locala11y' ),
					'guideLink'         => __( 'WCAG remediation guide ↗', 'locala11y' ),
					'manualReviewTitle' => __( 'Items needing manual review', 'locala11y' ),
					'honestNotice'      => __( 'Automated checks detect ~30–50% of WCAG criteria. Always pair with manual testing.', 'locala11y' ),
				),
			)
		);
	}

	/**
	 * Render Dashboard & Local Scanner page.
	 *
	 * @return void
	 */
	public static function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$summary     = LocalA11y_DB::get_summary();
		$latest_scan = $summary['latest_scan'];
		$counts      = $summary['open_counts'];
		$scans       = LocalA11y_DB::get_scans( 10 );
		$batch_limit = max( 1, absint( apply_filters( 'locala11y_max_batch_urls', 100 ) ) );
		?>
		<div class="wrap locala11y-wrap">
			<h1><?php esc_html_e( 'LocalA11y — Local WCAG 2.2 & EAA Accessibility Audit', 'locala11y' ); ?></h1>
			<p class="locala11y-subtitle">
				<?php esc_html_e( '100% local browser auditing using bundled axe-core. Zero external SaaS, zero tracking, zero fake accessibility overlays.', 'locala11y' ); ?>
			</p>

			<div class="locala11y-honest-box" role="note">
				<strong><?php esc_html_e( 'Honest compliance note:', 'locala11y' ); ?></strong>
				<?php esc_html_e( 'No plugin can provide or guarantee 100% legal compliance with WCAG, the European Accessibility Act (EAA), RGAA, or the ADA. Automated checks catch roughly 30–50% of barriers; combine this scanner with the built-in Manual Checklist and fix issues directly in your theme or content.', 'locala11y' ); ?>
			</div>

			<div class="locala11y-kpis">
				<div class="locala11y-kpi locala11y-kpi--critical">
					<span class="locala11y-kpi__value"><?php echo absint( $counts['critical'] ); ?></span>
					<span class="locala11y-kpi__label"><?php esc_html_e( 'Critical (Open)', 'locala11y' ); ?></span>
				</div>
				<div class="locala11y-kpi locala11y-kpi--serious">
					<span class="locala11y-kpi__value"><?php echo absint( $counts['serious'] ); ?></span>
					<span class="locala11y-kpi__label"><?php esc_html_e( 'Serious (Open)', 'locala11y' ); ?></span>
				</div>
				<div class="locala11y-kpi locala11y-kpi--moderate">
					<span class="locala11y-kpi__value"><?php echo absint( $counts['moderate'] ); ?></span>
					<span class="locala11y-kpi__label"><?php esc_html_e( 'Moderate (Open)', 'locala11y' ); ?></span>
				</div>
				<div class="locala11y-kpi locala11y-kpi--minor">
					<span class="locala11y-kpi__value"><?php echo absint( $counts['minor'] ); ?></span>
					<span class="locala11y-kpi__label"><?php esc_html_e( 'Minor (Open)', 'locala11y' ); ?></span>
				</div>
			</div>

			<div class="locala11y-card">
				<h2><?php esc_html_e( 'Run a Local Accessibility Audit', 'locala11y' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %d: maximum URLs per browser batch */
						esc_html__( 'Select published content below (up to %d URLs per browser run). Keep this tab open while your browser audits each page locally.', 'locala11y' ),
						absint( $batch_limit )
					);
					?>
				</p>

				<div class="locala11y-controls">
					<label>
						<span><?php esc_html_e( 'Standard', 'locala11y' ); ?></span>
						<select id="locala11y-wcag-level">
							<option value="wcag22aa" selected><?php esc_html_e( 'WCAG 2.2 Level AA (EAA Recommended)', 'locala11y' ); ?></option>
							<option value="wcag21aa"><?php esc_html_e( 'WCAG 2.1 Level AA', 'locala11y' ); ?></option>
							<option value="wcag2aa"><?php esc_html_e( 'WCAG 2.0 Level AA', 'locala11y' ); ?></option>
							<option value="wcag2a"><?php esc_html_e( 'WCAG 2.0 Level A', 'locala11y' ); ?></option>
						</select>
					</label>

					<label>
						<span><?php esc_html_e( 'Viewport', 'locala11y' ); ?></span>
						<select id="locala11y-viewport">
							<option value="desktop" selected><?php esc_html_e( 'Desktop (1280px)', 'locala11y' ); ?></option>
							<option value="mobile"><?php esc_html_e( 'Mobile (390px)', 'locala11y' ); ?></option>
						</select>
					</label>

					<div class="locala11y-control-buttons">
						<button type="button" id="locala11y-start-scan" class="button button-primary">
							<?php esc_html_e( 'Start Local Audit', 'locala11y' ); ?>
						</button>
						<button type="button" id="locala11y-cancel-scan" class="button" disabled>
							<?php esc_html_e( 'Cancel', 'locala11y' ); ?>
						</button>
					</div>
				</div>

				<div id="locala11y-progress-wrap" class="locala11y-progress-wrap" hidden aria-live="polite">
					<div class="locala11y-progress-bar">
						<div id="locala11y-progress-fill" class="locala11y-progress-fill" style="width:0%"></div>
					</div>
					<p id="locala11y-progress-text"></p>
				</div>

				<div id="locala11y-scan-warnings" class="notice notice-warning" hidden aria-live="polite"></div>

				<div class="locala11y-custom-url-row">
					<label for="locala11y-custom-url"><?php esc_html_e( 'Add a custom URL on this site (e.g., Shop, Checkout, Archive):', 'locala11y' ); ?></label>
					<div class="locala11y-custom-url-inputs">
						<input type="url" id="locala11y-custom-url" class="regular-text" placeholder="<?php echo esc_attr( home_url( '/shop/' ) ); ?>">
						<button type="button" id="locala11y-add-url" class="button button-secondary"><?php esc_html_e( 'Add URL', 'locala11y' ); ?></button>
					</div>
				</div>

				<div class="locala11y-targets-header">
					<label>
						<input type="checkbox" id="locala11y-select-all" checked>
						<strong><?php esc_html_e( 'Select / deselect all targets', 'locala11y' ); ?></strong>
					</label>
					<span id="locala11y-targets-count"></span>
				</div>

				<div id="locala11y-targets-list" class="locala11y-targets-list">
					<p><?php esc_html_e( 'Loading published pages…', 'locala11y' ); ?></p>
				</div>
			</div>

			<div class="locala11y-card">
				<div class="locala11y-card-head">
					<h2><?php esc_html_e( 'Recent Scans', 'locala11y' ); ?></h2>
					<?php if ( ! empty( $scans ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-locala11y-confirm="<?php echo esc_attr__( 'Delete all stored scan history and findings?', 'locala11y' ); ?>">
							<input type="hidden" name="action" value="locala11y_clear_scans">
							<?php wp_nonce_field( 'locala11y_clear_scans', 'locala11y_nonce' ); ?>
							<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Clear Scan History', 'locala11y' ); ?></button>
						</form>
					<?php endif; ?>
				</div>

				<?php if ( empty( $scans ) ) : ?>
					<p><?php esc_html_e( 'No scans yet. Click "Start Local Audit" above to run your first WCAG 2.2 audit.', 'locala11y' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Scan #', 'locala11y' ); ?></th>
								<th><?php esc_html_e( 'Date (UTC)', 'locala11y' ); ?></th>
								<th><?php esc_html_e( 'Standard', 'locala11y' ); ?></th>
								<th><?php esc_html_e( 'Viewport', 'locala11y' ); ?></th>
								<th><?php esc_html_e( 'Pages', 'locala11y' ); ?></th>
								<th><?php esc_html_e( 'Violations', 'locala11y' ); ?></th>
								<th><?php esc_html_e( 'Manual Review', 'locala11y' ); ?></th>
								<th><?php esc_html_e( 'Status', 'locala11y' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'locala11y' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $scans as $scan ) : ?>
								<tr>
									<td>#<?php echo absint( $scan['id'] ); ?></td>
									<td><?php echo esc_html( $scan['created_at'] ); ?></td>
									<td><?php echo esc_html( strtoupper( $scan['wcag_level'] ) ); ?></td>
									<td><?php echo esc_html( ucfirst( $scan['viewport'] ) ); ?></td>
									<td><?php echo absint( $scan['scanned_pages'] ) . ' / ' . absint( $scan['total_pages'] ); ?></td>
									<td><strong><?php echo absint( $scan['violations_count'] ); ?></strong></td>
									<td><?php echo absint( $scan['incomplete_count'] ); ?></td>
									<td><?php echo esc_html( ucfirst( $scan['status'] ) ); ?></td>
									<td>
										<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=locala11y-findings&scan_id=' . absint( $scan['id'] ) ) ); ?>">
											<?php esc_html_e( 'View Findings', 'locala11y' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $latest_scan ) ) : ?>
				<p>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=locala11y-statement' ) ); ?>">
						<?php esc_html_e( 'Next step: Generate your EAA Accessibility Statement →', 'locala11y' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Findings Explorer page.
	 *
	 * @return void
	 */
	public static function render_findings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin filter query args.
		$scan_id = isset( $_GET['scan_id'] ) ? absint( $_GET['scan_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$impact = isset( $_GET['impact'] ) ? sanitize_key( wp_unslash( $_GET['impact'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$scans = LocalA11y_DB::get_scans( 20 );
		if ( 0 === $scan_id && ! empty( $scans[0]['id'] ) ) {
			$scan_id = (int) $scans[0]['id'];
		}

		$findings = $scan_id
			? LocalA11y_DB::get_findings(
				array(
					'scan_id' => $scan_id,
					'impact'  => $impact,
					'status'  => $status,
					'search'  => $search,
					'limit'   => 250,
				)
			)
			: array();

		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=locala11y_export_csv&scan_id=' . absint( $scan_id ) ),
			'locala11y_export_csv'
		);
		?>
		<div class="wrap locala11y-wrap">
			<div class="locala11y-card-head">
				<h1><?php esc_html_e( 'Findings Explorer', 'locala11y' ); ?></h1>
				<?php if ( $scan_id ) : ?>
					<div>
						<a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Export CSV Report', 'locala11y' ); ?>
						</a>
						<button type="button" class="button locala11y-print-btn">
							<?php esc_html_e( 'Print / Save PDF', 'locala11y' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>

			<form method="get" class="locala11y-filters">
				<input type="hidden" name="page" value="locala11y-findings">

				<select name="scan_id" aria-label="<?php esc_attr_e( 'Select scan', 'locala11y' ); ?>">
					<?php foreach ( $scans as $s ) : ?>
						<option value="<?php echo absint( $s['id'] ); ?>" <?php selected( $scan_id, (int) $s['id'] ); ?>>
							<?php
							printf(
								/* translators: 1: Scan ID, 2: UTC Date */
								esc_html__( 'Scan #%1$d (%2$s)', 'locala11y' ),
								absint( $s['id'] ),
								esc_html( $s['created_at'] )
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>

				<select name="impact" aria-label="<?php esc_attr_e( 'Filter by severity', 'locala11y' ); ?>">
					<option value=""><?php esc_html_e( 'All Severities', 'locala11y' ); ?></option>
					<option value="critical" <?php selected( $impact, 'critical' ); ?>><?php esc_html_e( 'Critical', 'locala11y' ); ?></option>
					<option value="serious" <?php selected( $impact, 'serious' ); ?>><?php esc_html_e( 'Serious', 'locala11y' ); ?></option>
					<option value="moderate" <?php selected( $impact, 'moderate' ); ?>><?php esc_html_e( 'Moderate', 'locala11y' ); ?></option>
					<option value="minor" <?php selected( $impact, 'minor' ); ?>><?php esc_html_e( 'Minor', 'locala11y' ); ?></option>
				</select>

				<select name="status" aria-label="<?php esc_attr_e( 'Filter by status', 'locala11y' ); ?>">
					<option value=""><?php esc_html_e( 'All Statuses', 'locala11y' ); ?></option>
					<option value="open" <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Open', 'locala11y' ); ?></option>
					<option value="reviewed" <?php selected( $status, 'reviewed' ); ?>><?php esc_html_e( 'Reviewed', 'locala11y' ); ?></option>
					<option value="fixed" <?php selected( $status, 'fixed' ); ?>><?php esc_html_e( 'Fixed', 'locala11y' ); ?></option>
				</select>

				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search rule, page, selector…', 'locala11y' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'locala11y' ); ?></button>
			</form>

			<?php if ( empty( $findings ) ) : ?>
				<div class="locala11y-card">
					<p><?php esc_html_e( 'No findings match your current filters.', 'locala11y' ); ?></p>
				</div>
			<?php else : ?>
				<table class="widefat striped locala11y-findings-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Severity', 'locala11y' ); ?></th>
							<th><?php esc_html_e( 'Issue & Guidance', 'locala11y' ); ?></th>
							<th><?php esc_html_e( 'Page', 'locala11y' ); ?></th>
							<th><?php esc_html_e( 'Selector & HTML', 'locala11y' ); ?></th>
							<th><?php esc_html_e( 'Status', 'locala11y' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $findings as $f ) : ?>
							<tr>
								<td>
									<span class="locala11y-badge locala11y-badge--<?php echo esc_attr( $f['impact'] ); ?>">
										<?php echo esc_html( ucfirst( $f['impact'] ) ); ?>
									</span>
									<?php if ( 'incomplete' === $f['finding_type'] ) : ?>
										<div class="locala11y-subtag"><?php esc_html_e( 'Manual check', 'locala11y' ); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<strong><?php echo esc_html( $f['help_text'] ); ?></strong>
									<div class="locala11y-meta">
										<code><?php echo esc_html( $f['rule_id'] ); ?></code>
										<?php if ( ! empty( $f['wcag_tags'] ) ) : ?>
											· <?php echo esc_html( $f['wcag_tags'] ); ?>
										<?php endif; ?>
									</div>
									<?php if ( ! empty( $f['failure_summary'] ) ) : ?>
										<p class="locala11y-summary"><?php echo esc_html( $f['failure_summary'] ); ?></p>
									<?php endif; ?>
									<?php if ( ! empty( $f['help_url'] ) ) : ?>
										<a href="<?php echo esc_url( $f['help_url'] ); ?>" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'WCAG remediation guide ↗', 'locala11y' ); ?>
										</a>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo esc_url( $f['page_url'] ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $f['page_title'] ? $f['page_title'] : $f['page_url'] ); ?>
									</a>
								</td>
								<td>
									<div class="locala11y-selector-row">
										<code class="locala11y-code"><?php echo esc_html( $f['selector'] ); ?></code>
										<?php if ( ! empty( $f['selector'] ) ) : ?>
											<button type="button" class="button button-small locala11y-copy-btn" data-copy="<?php echo esc_attr( $f['selector'] ); ?>">
												<?php esc_html_e( 'Copy selector', 'locala11y' ); ?>
											</button>
										<?php endif; ?>
									</div>
									<?php if ( ! empty( $f['html_snippet'] ) ) : ?>
										<pre class="locala11y-snippet"><?php echo esc_html( $f['html_snippet'] ); ?></pre>
									<?php endif; ?>
								</td>
								<td>
									<select class="locala11y-status-select" data-finding-id="<?php echo absint( $f['id'] ); ?>" aria-label="<?php esc_attr_e( 'Finding status', 'locala11y' ); ?>">
										<option value="open" <?php selected( $f['status'], 'open' ); ?>><?php esc_html_e( 'Open', 'locala11y' ); ?></option>
										<option value="reviewed" <?php selected( $f['status'], 'reviewed' ); ?>><?php esc_html_e( 'Reviewed', 'locala11y' ); ?></option>
										<option value="fixed" <?php selected( $f['status'], 'fixed' ); ?>><?php esc_html_e( 'Fixed', 'locala11y' ); ?></option>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render EAA Accessibility Statement generator page.
	 *
	 * @return void
	 */
	public static function render_statement_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s       = LocalA11y_Statement::get_settings();
		$page_id = absint( $s['statement_page_id'] );
		$page_url = ( $page_id && get_post( $page_id ) && 'trash' !== get_post_status( $page_id ) ) ? get_permalink( $page_id ) : '';
		?>
		<div class="wrap locala11y-wrap">
			<h1><?php esc_html_e( 'EAA / WCAG 2.2 Accessibility Statement Generator', 'locala11y' ); ?></h1>
			<p class="locala11y-subtitle">
				<?php esc_html_e( 'Assist your site with a clear, transparent Accessibility Statement template aligned with the European Accessibility Act (EU Directive 2019/882) and WCAG 2.2 Level AA. Embed anywhere using shortcode [locala11y_statement].', 'locala11y' ); ?>
			</p>

			<div class="locala11y-honest-box" role="note">
				<strong><?php esc_html_e( 'Legal notice:', 'locala11y' ); ?></strong>
				<?php esc_html_e( 'No plugin can provide or guarantee 100% legal compliance. This generator provides a self-assessment template to assist your team and does not constitute legal advice. Website operators remain responsible for verifying applicable national EAA, RGAA, or ADA requirements.', 'locala11y' ); ?>
			</div>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['updated'] ) ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Accessibility Statement settings saved.', 'locala11y' ); ?></p></div>
			<?php endif; ?>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['published'] ) && $page_url ) :
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php esc_html_e( 'Accessibility Statement page published!', 'locala11y' ); ?>
						<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View page ↗', 'locala11y' ); ?></a>
					</p>
				</div>
			<?php endif; ?>

			<div class="locala11y-grid-2">
				<div class="locala11y-card">
					<h2><?php esc_html_e( 'Statement Configuration', 'locala11y' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="locala11y_save_statement">
						<?php wp_nonce_field( 'locala11y_save_statement', 'locala11y_nonce' ); ?>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="la-lang"><?php esc_html_e( 'Statement Language', 'locala11y' ); ?></label></th>
								<td>
									<select id="la-lang" name="language">
										<option value="en" <?php selected( $s['language'], 'en' ); ?>>English</option>
										<option value="fr" <?php selected( $s['language'], 'fr' ); ?>>Français</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="la-org"><?php esc_html_e( 'Organization / Site Owner', 'locala11y' ); ?></label></th>
								<td><input id="la-org" type="text" name="organization_name" class="regular-text" value="<?php echo esc_attr( $s['organization_name'] ); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="la-url"><?php esc_html_e( 'Website URL', 'locala11y' ); ?></label></th>
								<td><input id="la-url" type="url" name="website_url" class="regular-text" value="<?php echo esc_attr( $s['website_url'] ); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="la-std"><?php esc_html_e( 'Target Standard', 'locala11y' ); ?></label></th>
								<td><input id="la-std" type="text" name="standard" class="regular-text" value="<?php echo esc_attr( $s['standard'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="la-status"><?php esc_html_e( 'Conformance Status', 'locala11y' ); ?></label></th>
								<td>
									<select id="la-status" name="conformance_status">
										<option value="fully" <?php selected( $s['conformance_status'], 'fully' ); ?>><?php esc_html_e( 'Fully conformant', 'locala11y' ); ?></option>
										<option value="partially" <?php selected( $s['conformance_status'], 'partially' ); ?>><?php esc_html_e( 'Partially conformant (recommended during remediation)', 'locala11y' ); ?></option>
										<option value="non" <?php selected( $s['conformance_status'], 'non' ); ?>><?php esc_html_e( 'Non-conformant', 'locala11y' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="la-email"><?php esc_html_e( 'Contact Email', 'locala11y' ); ?></label></th>
								<td><input id="la-email" type="email" name="contact_email" class="regular-text" value="<?php echo esc_attr( $s['contact_email'] ); ?>" required></td>
							</tr>
							<tr>
								<th scope="row"><label for="la-phone"><?php esc_html_e( 'Contact Phone (optional)', 'locala11y' ); ?></label></th>
								<td><input id="la-phone" type="text" name="contact_phone" class="regular-text" value="<?php echo esc_attr( $s['contact_phone'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="la-resp"><?php esc_html_e( 'Typical Response Time', 'locala11y' ); ?></label></th>
								<td><input id="la-resp" type="text" name="response_time" class="regular-text" value="<?php echo esc_attr( $s['response_time'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="la-lim"><?php esc_html_e( 'Known Limitations (optional notes)', 'locala11y' ); ?></label></th>
								<td><textarea id="la-lim" name="known_limitations" rows="3" class="large-text"><?php echo esc_textarea( $s['known_limitations'] ); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Auto-list Open Scan Issues', 'locala11y' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="include_scan_issues" value="1" <?php checked( ! empty( $s['include_scan_issues'] ) ); ?>>
										<?php esc_html_e( 'Automatically include top open findings from your latest local scan in Known Limitations.', 'locala11y' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="la-enf"><?php esc_html_e( 'National Enforcement Body (optional)', 'locala11y' ); ?></label></th>
								<td><textarea id="la-enf" name="enforcement_body" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'e.g., Défenseur des droits / national EAA authority link', 'locala11y' ); ?>"><?php echo esc_textarea( $s['enforcement_body'] ); ?></textarea></td>
							</tr>
							<tr>
								<th scope="row"><label for="la-date"><?php esc_html_e( 'Last Review Date', 'locala11y' ); ?></label></th>
								<td><input id="la-date" type="date" name="last_reviewed" value="<?php echo esc_attr( $s['last_reviewed'] ); ?>"></td>
							</tr>
						</table>

						<?php submit_button( __( 'Save Statement Settings', 'locala11y' ) ); ?>
					</form>

					<hr>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="locala11y_publish_statement">
						<?php wp_nonce_field( 'locala11y_publish_statement', 'locala11y_nonce' ); ?>
						<button type="submit" class="button button-secondary">
							<?php echo $page_url ? esc_html__( 'Update Published Statement Page', 'locala11y' ) : esc_html__( 'Create & Publish Accessibility Statement Page', 'locala11y' ); ?>
						</button>
						<?php if ( $page_url ) : ?>
							<a class="button button-link" href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Open published page ↗', 'locala11y' ); ?>
							</a>
						<?php endif; ?>
					</form>
				</div>

				<div class="locala11y-card">
					<h2><?php esc_html_e( 'Live Preview', 'locala11y' ); ?></h2>
					<p><code>[locala11y_statement]</code></p>
					<div class="locala11y-preview">
						<?php echo wp_kses_post( LocalA11y_Statement::render_shortcode() ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Manual EAA testing items (criteria automated tools cannot fully verify).
	 *
	 * @return array
	 */
	public static function get_manual_checklist_items() {
		return array(
			'keyboard_nav'       => array(
				'wcag'  => 'WCAG 2.1.1 / 2.1.2 (A)',
				'title' => __( 'Full keyboard-only navigation (Tab / Shift+Tab / Enter / Esc)', 'locala11y' ),
				'desc'  => __( 'Verify every menu, dropdown, modal, cart drawer, and form can be operated without a mouse and has no keyboard trap.', 'locala11y' ),
			),
			'focus_visible'      => array(
				'wcag'  => 'WCAG 2.4.7 / 2.4.11 (AA)',
				'title' => __( 'Visible focus indicator not hidden behind sticky headers', 'locala11y' ),
				'desc'  => __( 'Check that the currently focused link or button is clearly visible and never completely covered by a sticky bar or cookie banner.', 'locala11y' ),
			),
			'screen_reader'      => array(
				'wcag'  => 'WCAG 1.3.1 / 4.1.2 (A)',
				'title' => __( 'Screen reader walkthrough (NVDA / VoiceOver)', 'locala11y' ),
				'desc'  => __( 'Listen to key templates (Homepage, Navigation, Contact/Checkout form) to confirm meaningful headings, landmarks, and announcements.', 'locala11y' ),
			),
			'meaningful_alt'     => array(
				'wcag'  => 'WCAG 1.1.1 (A)',
				'title' => __( 'Meaningful alt text (not just non-empty)', 'locala11y' ),
				'desc'  => __( 'Automated tools only detect missing alt attributes. Confirm informative images describe their purpose and decorative images use alt="".', 'locala11y' ),
			),
			'reflow_zoom'        => array(
				'wcag'  => 'WCAG 1.4.4 / 1.4.10 (AA)',
				'title' => __( '200% text resize & 400% zoom reflow without horizontal scrolling', 'locala11y' ),
				'desc'  => __( 'Zoom the browser to 200% and 400% at 1280px width; ensure no overlapping text or clipped buttons.', 'locala11y' ),
			),
			'captions_media'     => array(
				'wcag'  => 'WCAG 1.2.2 / 1.2.3 (A/AA)',
				'title' => __( 'Captions and transcripts for audio & video', 'locala11y' ),
				'desc'  => __( 'Confirm embedded videos have accurate synchronized captions and audio content has text transcripts.', 'locala11y' ),
			),
			'error_recovery'     => array(
				'wcag'  => 'WCAG 3.3.1 / 3.3.3 (A/AA)',
				'title' => __( 'Clear form error identification & recovery', 'locala11y' ),
				'desc'  => __( 'Submit forms with empty or invalid fields and confirm clear text instructions explain how to fix each error.', 'locala11y' ),
			),
			'target_size'        => array(
				'wcag'  => 'WCAG 2.5.8 (AA — 2.2 New)',
				'title' => __( 'Minimum 24×24px interactive target spacing', 'locala11y' ),
				'desc'  => __( 'Check mobile icon buttons, pagination links, and close icons to ensure comfortable tap targets.', 'locala11y' ),
			),
			'accessible_auth'    => array(
				'wcag'  => 'WCAG 3.3.8 (AA — 2.2 New)',
				'title' => __( 'Accessible authentication (no paste-blocking or cognitive-only puzzles)', 'locala11y' ),
				'desc'  => __( 'Ensure password managers and copy-paste work on login/checkout forms.', 'locala11y' ),
			),
			'statement_linked'   => array(
				'wcag'  => 'EAA Directive 2019/882',
				'title' => __( 'Accessibility Statement published and linked in site footer', 'locala11y' ),
				'desc'  => __( 'Publish your statement via LocalA11y → EAA Statement and add a visible footer link.', 'locala11y' ),
			),
		);
	}

	/**
	 * Render Manual Checklist page.
	 *
	 * @return void
	 */
	public static function render_checklist_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$items = self::get_manual_checklist_items();
		$saved = get_option( self::CHECKLIST_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$completed = 0;
		foreach ( $items as $key => $meta ) {
			if ( ! empty( $saved[ $key ] ) ) {
				++$completed;
			}
		}
		?>
		<div class="wrap locala11y-wrap">
			<h1><?php esc_html_e( 'Manual WCAG 2.2 & EAA Verification Checklist', 'locala11y' ); ?></h1>
			<p class="locala11y-subtitle">
				<?php
				printf(
					/* translators: 1: completed count, 2: total count */
					esc_html__( 'Progress: %1$d / %2$d manual checks verified. Complete these alongside automated scans for a genuine accessibility audit.', 'locala11y' ),
					absint( $completed ),
					absint( count( $items ) )
				);
				?>
			</p>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['updated'] ) ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Manual checklist progress saved.', 'locala11y' ); ?></p></div>
			<?php endif; ?>

			<div class="locala11y-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="locala11y_save_checklist">
					<?php wp_nonce_field( 'locala11y_save_checklist', 'locala11y_nonce' ); ?>

					<ul class="locala11y-checklist">
						<?php foreach ( $items as $key => $item ) : ?>
							<li class="locala11y-checklist__item">
								<label>
									<input type="checkbox" name="checks[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $saved[ $key ] ) ); ?>>
									<span>
										<strong><?php echo esc_html( $item['title'] ); ?></strong>
										<span class="locala11y-wcag-pill"><?php echo esc_html( $item['wcag'] ); ?></span>
										<br>
										<span class="description"><?php echo esc_html( $item['desc'] ); ?></span>
									</span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php submit_button( __( 'Save Checklist Progress', 'locala11y' ) ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Save EAA statement settings handler.
	 *
	 * @return void
	 */
	public static function handle_save_statement() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'locala11y' ) );
		}
		check_admin_referer( 'locala11y_save_statement', 'locala11y_nonce' );

		LocalA11y_Statement::save_settings( wp_unslash( $_POST ) );
		wp_safe_redirect( admin_url( 'admin.php?page=locala11y-statement&updated=1' ) );
		exit;
	}

	/**
	 * Publish EAA statement page handler.
	 *
	 * @return void
	 */
	public static function handle_publish_statement() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'locala11y' ) );
		}
		check_admin_referer( 'locala11y_publish_statement', 'locala11y_nonce' );

		LocalA11y_Statement::publish_statement_page();
		wp_safe_redirect( admin_url( 'admin.php?page=locala11y-statement&published=1' ) );
		exit;
	}

	/**
	 * Save manual checklist handler.
	 *
	 * @return void
	 */
	public static function handle_save_checklist() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'locala11y' ) );
		}
		check_admin_referer( 'locala11y_save_checklist', 'locala11y_nonce' );

		$items = self::get_manual_checklist_items();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized key-by-key against allowlist below.
		$raw   = isset( $_POST['checks'] ) && is_array( $_POST['checks'] ) ? wp_unslash( $_POST['checks'] ) : array();
		$clean = array();

		foreach ( array_keys( $items ) as $key ) {
			$clean[ $key ] = ! empty( $raw[ $key ] ) ? 1 : 0;
		}

		update_option( self::CHECKLIST_OPTION, $clean );
		wp_safe_redirect( admin_url( 'admin.php?page=locala11y-checklist&updated=1' ) );
		exit;
	}

	/**
	 * Clear scan history handler.
	 *
	 * @return void
	 */
	public static function handle_clear_scans() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'locala11y' ) );
		}
		check_admin_referer( 'locala11y_clear_scans', 'locala11y_nonce' );

		LocalA11y_DB::clear_all();
		wp_safe_redirect( admin_url( 'admin.php?page=locala11y' ) );
		exit;
	}

	/**
	 * Neutralize spreadsheet formula injection characters in CSV cells.
	 *
	 * @param string $value Raw cell value.
	 * @return string
	 */
	private static function safe_csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Export findings as RFC-4180 CSV.
	 *
	 * @return void
	 */
	public static function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'locala11y' ) );
		}
		check_admin_referer( 'locala11y_export_csv' );

		$scan_id  = isset( $_GET['scan_id'] ) ? absint( $_GET['scan_id'] ) : 0;
		$findings = LocalA11y_DB::get_findings(
			array(
				'scan_id' => $scan_id,
				'limit'   => 500,
			)
		);

		$filename = 'locala11y-audit-scan-' . $scan_id . '-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		$output = fopen( 'php://output', 'w' );
		if ( ! $output ) {
			exit;
		}

		// UTF-8 BOM ensures Excel opens European/French characters cleanly.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $output, "\xEF\xBB\xBF" );

		fputcsv(
			$output,
			array( 'Severity', 'Type', 'Rule ID', 'WCAG Tags', 'Issue', 'Failure Summary', 'Page Title', 'Page URL', 'CSS Selector', 'Status', 'Guide URL' )
		);

		foreach ( $findings as $f ) {
			fputcsv(
				$output,
				array(
					self::safe_csv_cell( $f['impact'] ),
					self::safe_csv_cell( $f['finding_type'] ),
					self::safe_csv_cell( $f['rule_id'] ),
					self::safe_csv_cell( $f['wcag_tags'] ),
					self::safe_csv_cell( $f['help_text'] ),
					self::safe_csv_cell( $f['failure_summary'] ),
					self::safe_csv_cell( $f['page_title'] ),
					self::safe_csv_cell( $f['page_url'] ),
					self::safe_csv_cell( $f['selector'] ),
					self::safe_csv_cell( $f['status'] ),
					self::safe_csv_cell( $f['help_url'] ),
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}
}
