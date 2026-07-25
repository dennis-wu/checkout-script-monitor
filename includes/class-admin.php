<?php
/**
 * Admin UI: inventory / 6.4.3 readout, violations, settings.
 *
 * @package Checkout_Script_Monitor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CSM_Admin.
 */
class CSM_Admin {

	/**
	 * @var CSM_Inventory
	 */
	private $inventory;

	/**
	 * @var CSM_CSP
	 */
	private $csp;

	public function __construct( CSM_Inventory $inventory, CSM_CSP $csp ) {
		$this->inventory = $inventory;
		$this->csp       = $csp;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_csm_scan', array( $this, 'handle_scan' ) );
		add_action( 'admin_post_csm_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_csm_rebaseline', array( $this, 'handle_rebaseline' ) );
		add_action( 'admin_post_csm_clear_violations', array( $this, 'handle_clear_violations' ) );
		add_action( 'admin_post_csm_dismiss_notice', array( $this, 'handle_dismiss_notice' ) );
		add_action( 'admin_notices', array( $this, 'funnel_notice' ) );
	}

	public function menu() {
		$cap = 'manage_options';
		add_menu_page(
			__( 'Checkout Script Monitor', 'checkout-script-monitor' ),
			__( 'Checkout Script Monitor', 'checkout-script-monitor' ),
			$cap,
			'checkout-script-monitor',
			array( $this, 'render_inventory' ),
			'dashicons-visibility',
			58
		);
		add_submenu_page( 'checkout-script-monitor', __( 'Inventory & 6.4.3', 'checkout-script-monitor' ), __( 'Inventory & 6.4.3', 'checkout-script-monitor' ), $cap, 'checkout-script-monitor', array( $this, 'render_inventory' ) );
		add_submenu_page( 'checkout-script-monitor', __( 'Violations', 'checkout-script-monitor' ), __( 'Violations', 'checkout-script-monitor' ), $cap, 'checkout-script-monitor-violations', array( $this, 'render_violations' ) );
		add_submenu_page( 'checkout-script-monitor', __( 'Settings', 'checkout-script-monitor' ), __( 'Settings', 'checkout-script-monitor' ), $cap, 'checkout-script-monitor-settings', array( $this, 'render_settings' ) );
	}

	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'checkout-script-monitor' ) );
		}
	}

	private function redirect( $page, $notice = '' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => $page,
					'csm_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------- Handlers.

	public function handle_scan() {
		$this->guard();
		check_admin_referer( 'csm_scan' );
		$this->inventory->scan();
		$this->redirect( 'checkout-script-monitor', 'scanned' );
	}

	public function handle_save_settings() {
		$this->guard();
		check_admin_referer( 'csm_settings' );
		$enabled = isset( $_POST['csp_report_only'] ) ? 1 : 0;
		update_option( CSM_OPT_SETTINGS, array( 'csp_report_only' => $enabled ) );
		$this->redirect( 'checkout-script-monitor-settings', 'saved' );
	}

	public function handle_rebaseline() {
		$this->guard();
		check_admin_referer( 'csm_rebaseline' );
		$this->csp->rebaseline();
		$this->redirect( 'checkout-script-monitor-settings', 'rebaselined' );
	}

	public function handle_clear_violations() {
		$this->guard();
		check_admin_referer( 'csm_clear_violations' );
		$this->csp->clear_violations();
		$this->redirect( 'checkout-script-monitor-violations', 'cleared' );
	}

	public function handle_dismiss_notice() {
		$this->guard();
		check_admin_referer( 'csm_dismiss_notice' );
		update_option( CSM_OPT_NOTICE, 1 );
		$this->redirect( 'checkout-script-monitor', '' );
	}

	// --------------------------------------------------------------- Notices.

	private function notice_text() {
		$k = isset( $_GET['csm_notice'] ) ? sanitize_key( wp_unslash( $_GET['csm_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map = array(
			'scanned'     => __( 'Scan complete.', 'checkout-script-monitor' ),
			'saved'       => __( 'Settings saved.', 'checkout-script-monitor' ),
			'rebaselined' => __( 'Baseline updated from your latest scan.', 'checkout-script-monitor' ),
			'cleared'     => __( 'Violations cleared.', 'checkout-script-monitor' ),
		);
		if ( isset( $map[ $k ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $k ] ) . '</p></div>';
		}
	}

	public function funnel_notice() {
		if ( get_option( CSM_OPT_NOTICE ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'checkout-script-monitor' ) ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=csm_dismiss_notice' ), 'csm_dismiss_notice' );
		echo '<div class="notice notice-info"><p>';
		echo wp_kses_post(
			sprintf(
				/* translators: 1: Webpage Security Checker link, 2: SAQ A Readiness AI Advisor link. */
				__( 'Want an outside view of your checkout? Run a free scan with the %1$s, or see where you stand with the %2$s.', 'checkout-script-monitor' ),
				'<a href="https://cybershieldstudio.com/tools/page-checker" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Webpage Security Checker', 'checkout-script-monitor' ) . '</a>',
				'<a href="https://cybershieldstudio.com/tools/saq-a-ready" target="_blank" rel="noopener noreferrer">' . esc_html__( 'SAQ A Readiness AI Advisor', 'checkout-script-monitor' ) . '</a>'
			)
		);
		echo ' <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'checkout-script-monitor' ) . '</a>';
		echo '</p></div>';
	}

	// --------------------------------------------------------------- Screens.

	public function render_inventory() {
		$this->guard();
		$snap = $this->inventory->get_snapshot();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Checkout Script Monitor — Inventory & PCI DSS 6.4.3', 'checkout-script-monitor' ); ?></h1>
			<?php $this->notice_text(); ?>
			<p><?php esc_html_e( 'This scans your own store pages and lists the scripts loading on them, so you can see what runs on your checkout and where you stand on Requirement 6.4.3. It is a readiness and visibility tool. It does not make you PCI compliant; that decision stays yours.', 'checkout-script-monitor' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="csm_scan" />
				<?php wp_nonce_field( 'csm_scan' ); ?>
				<?php submit_button( __( 'Scan my store', 'checkout-script-monitor' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( empty( $snap ) || empty( $snap['scanned_at'] ) ) : ?>
				<p><em><?php esc_html_e( 'No scan yet. Run a scan to build your inventory.', 'checkout-script-monitor' ); ?></em></p>
			<?php else : ?>
				<p><?php
					/* translators: %s: date/time of the last scan. */
					printf( esc_html__( 'Last scanned: %s', 'checkout-script-monitor' ), esc_html( $snap['scanned_at'] ) );
				?></p>

				<?php if ( ! empty( $snap['errors'] ) ) : ?>
					<div class="notice notice-warning inline"><p>
						<?php esc_html_e( 'Some pages could not be scanned:', 'checkout-script-monitor' ); ?>
						<?php
						$parts = array();
						foreach ( $snap['errors'] as $label => $msg ) {
							$parts[] = $label . ': ' . $msg;
						}
						echo esc_html( implode( '; ', $parts ) );
						?>
					</p></div>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Summary', 'checkout-script-monitor' ); ?></h2>
				<ul style="list-style:disc;margin-left:20px;">
					<li><?php printf( esc_html__( 'External scripts found: %d', 'checkout-script-monitor' ), (int) $snap['total'] ); ?></li>
					<li><?php printf( esc_html__( 'Third-party scripts: %d', 'checkout-script-monitor' ), (int) $snap['third_party'] ); ?></li>
					<li><?php printf( esc_html__( 'Missing an integrity (SRI) check: %d', 'checkout-script-monitor' ), (int) $snap['missing_sri'] ); ?></li>
				</ul>

				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Script', 'checkout-script-monitor' ); ?></th>
						<th><?php esc_html_e( 'Origin', 'checkout-script-monitor' ); ?></th>
						<th><?php esc_html_e( 'Vendor / category', 'checkout-script-monitor' ); ?></th>
						<th><?php esc_html_e( 'Integrity (SRI)', 'checkout-script-monitor' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $snap['scripts'] as $s ) : ?>
						<tr>
							<td><code><?php echo esc_html( $s['src'] ); ?></code></td>
							<td><?php echo $s['third_party'] ? esc_html__( 'Third party', 'checkout-script-monitor' ) : esc_html__( 'First party', 'checkout-script-monitor' ); ?></td>
							<td><?php
								if ( ! empty( $s['vendor'] ) ) {
									echo esc_html( $s['vendor'] . ' (' . $s['category'] . ')' );
								} elseif ( ! empty( $s['category'] ) ) {
									echo esc_html( $s['category'] );
								} else {
									echo '&mdash;';
								}
							?></td>
							<td><?php echo ! empty( $s['has_sri'] ) ? '&#10003;' : '<strong style="color:#b32d2e;">' . esc_html__( 'missing', 'checkout-script-monitor' ) . '</strong>'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'What Requirement 6.4.3 asks', 'checkout-script-monitor' ); ?></h2>
				<p><?php esc_html_e( 'PCI DSS 6.4.3 asks merchants to keep an inventory of every script on their payment pages, confirm each one is there on purpose, and be able to tell when a script has changed. In plain terms: know what runs on your checkout, and notice when it changes.', 'checkout-script-monitor' ); ?></p>
				<p><?php esc_html_e( 'Start with the list above. Remove anything you do not recognise or no longer use. For third-party scripts you keep, ask whether they belong on the checkout at all, and add an integrity (SRI) check where the provider supports it. Then turn on CSP Report-Only monitoring in Settings to be told when a new or changed script appears.', 'checkout-script-monitor' ); ?></p>
				<p><em><?php esc_html_e( 'Note: this scan sees scripts written into the page HTML. Scripts injected later by other scripts (for example by a tag manager) may not appear here. A full external scan can catch those.', 'checkout-script-monitor' ); ?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_violations() {
		$this->guard();
		$enabled  = $this->csp->is_enabled();
		$baseline = $this->csp->get_baseline();
		$rows     = $this->csp->recent_violations( 100 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Checkout Script Monitor — Violations (drift)', 'checkout-script-monitor' ); ?></h1>
			<?php $this->notice_text(); ?>
			<p><?php esc_html_e( 'When CSP Report-Only is on, your visitors\' browsers report any script that is not in your confirmed baseline. Nothing is blocked; these are reports only. A new or changed script here is worth a look: it may be a legitimate addition, or a sign something changed on your checkout without you knowing.', 'checkout-script-monitor' ); ?></p>

			<?php if ( ! $enabled ) : ?>
				<div class="notice notice-warning inline"><p><?php
					printf(
						/* translators: %s: Settings link. */
						esc_html__( 'CSP Report-Only is off. Turn it on in %s to start collecting drift reports.', 'checkout-script-monitor' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=checkout-script-monitor-settings' ) ) . '">' . esc_html__( 'Settings', 'checkout-script-monitor' ) . '</a>'
					);
				?></p></div>
			<?php elseif ( empty( $baseline['hosts'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'No baseline yet. Run a scan, then set your baseline in Settings.', 'checkout-script-monitor' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $rows ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0;">
					<input type="hidden" name="action" value="csm_clear_violations" />
					<?php wp_nonce_field( 'csm_clear_violations' ); ?>
					<?php submit_button( __( 'Clear violations', 'checkout-script-monitor' ), 'secondary', 'submit', false ); ?>
				</form>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Reported resource', 'checkout-script-monitor' ); ?></th>
						<th><?php esc_html_e( 'Directive', 'checkout-script-monitor' ); ?></th>
						<th><?php esc_html_e( 'Reports', 'checkout-script-monitor' ); ?></th>
						<th><?php esc_html_e( 'Last seen', 'checkout-script-monitor' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><code><?php echo esc_html( $r->blocked_uri ); ?></code></td>
							<td><?php echo esc_html( $r->directive ); ?></td>
							<td><?php echo (int) $r->hits; ?></td>
							<td><?php echo esc_html( $r->last_seen ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><em><?php esc_html_e( 'No drift reported yet.', 'checkout-script-monitor' ); ?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_settings() {
		$this->guard();
		$enabled  = $this->csp->is_enabled();
		$baseline = $this->csp->get_baseline();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Checkout Script Monitor — Settings', 'checkout-script-monitor' ); ?></h1>
			<?php $this->notice_text(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="csm_save_settings" />
				<?php wp_nonce_field( 'csm_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'CSP Report-Only monitoring', 'checkout-script-monitor' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="csp_report_only" value="1" <?php checked( $enabled ); ?> />
								<?php esc_html_e( 'Emit a Content-Security-Policy-Report-Only header on the cart and checkout, built from your confirmed baseline.', 'checkout-script-monitor' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Report-Only never blocks anything on your site. It only reports scripts that are not in your baseline, so you can catch drift. Your baseline is the scripts already on your own site, not a list of sources we vouch for. If something unwanted is already loading, it will be in the baseline, so review your inventory first.', 'checkout-script-monitor' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'checkout-script-monitor' ) ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Baseline', 'checkout-script-monitor' ); ?></h2>
			<?php if ( empty( $baseline['hosts'] ) ) : ?>
				<p><em><?php esc_html_e( 'No baseline set. Run a scan (Inventory), then set your baseline below.', 'checkout-script-monitor' ); ?></em></p>
			<?php else : ?>
				<p><?php
					/* translators: %s: date the baseline was set. */
					printf( esc_html__( 'Baseline set on %s. Allowed script hosts:', 'checkout-script-monitor' ), esc_html( $baseline['created_at'] ) );
				?></p>
				<ul style="list-style:disc;margin-left:20px;">
					<?php foreach ( $baseline['hosts'] as $h ) : ?>
						<li><code><?php echo esc_html( $h ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="csm_rebaseline" />
				<?php wp_nonce_field( 'csm_rebaseline' ); ?>
				<?php submit_button( __( 'Set baseline from my latest scan', 'checkout-script-monitor' ), 'secondary', 'submit', false ); ?>
				<p class="description"><?php esc_html_e( 'Take the hosts from your most recent scan as the confirmed baseline. Do this after you have reviewed the inventory and removed anything you do not want.', 'checkout-script-monitor' ); ?></p>
			</form>
		</div>
		<?php
	}
}
