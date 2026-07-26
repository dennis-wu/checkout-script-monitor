<?php
/**
 * Admin UI: Checkout Scripts (scan + trust + setup), Alerts, Settings.
 *
 * Written for a non-technical store owner: plain language first, the PCI/CSP
 * mechanics tucked behind "why this matters" / "technical details".
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
		add_action( 'admin_post_csm_enable_monitoring', array( $this, 'handle_enable_monitoring' ) );
		add_action( 'admin_post_csm_trust', array( $this, 'handle_trust' ) );
		add_action( 'admin_post_csm_untrust', array( $this, 'handle_untrust' ) );
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
			array( $this, 'render_home' ),
			'dashicons-visibility',
			58
		);
		add_submenu_page( 'checkout-script-monitor', __( 'Checkout Scripts', 'checkout-script-monitor' ), __( 'Checkout Scripts', 'checkout-script-monitor' ), $cap, 'checkout-script-monitor', array( $this, 'render_home' ) );

		// Show a count bubble on Alerts when monitoring is on and there is something new.
		$alerts_label = __( 'Alerts', 'checkout-script-monitor' );
		if ( $this->csp->is_enabled() ) {
			$count = count( (array) $this->csp->recent_violations( 100 ) );
			if ( $count > 0 ) {
				$alerts_label .= ' <span class="awaiting-mod">' . (int) $count . '</span>';
			}
		}
		add_submenu_page( 'checkout-script-monitor', __( 'Alerts', 'checkout-script-monitor' ), $alerts_label, $cap, 'checkout-script-monitor-alerts', array( $this, 'render_alerts' ) );
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
		$this->redirect( 'checkout-script-monitor', 'rebaselined' );
	}

	public function handle_enable_monitoring() {
		$this->guard();
		check_admin_referer( 'csm_enable_monitoring' );
		update_option( CSM_OPT_SETTINGS, array( 'csp_report_only' => 1 ) );
		$this->redirect( 'checkout-script-monitor', 'monitoring_on' );
	}

	public function handle_trust() {
		$this->guard();
		check_admin_referer( 'csm_trust' );
		$uri = isset( $_POST['uri'] ) ? sanitize_text_field( wp_unslash( $_POST['uri'] ) ) : '';
		if ( '' !== $uri ) {
			$this->csp->add_baseline_host( $uri );
			$this->csp->forget_host( $uri );
		}
		$this->redirect( 'checkout-script-monitor-alerts', 'trusted' );
	}

	public function handle_untrust() {
		$this->guard();
		check_admin_referer( 'csm_untrust' );
		$host = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '';
		if ( '' !== $host ) {
			$this->csp->remove_baseline_host( $host );
		}
		$this->redirect( 'checkout-script-monitor', 'untrusted' );
	}

	public function handle_clear_violations() {
		$this->guard();
		check_admin_referer( 'csm_clear_violations' );
		$this->csp->clear_violations();
		$this->redirect( 'checkout-script-monitor-alerts', 'cleared' );
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
			'scanned'       => __( 'Scan complete. Here is what is running on your checkout.', 'checkout-script-monitor' ),
			'saved'         => __( 'Settings saved.', 'checkout-script-monitor' ),
			'rebaselined'   => __( 'Your trusted list is saved.', 'checkout-script-monitor' ),
			'cleared'       => __( 'Alerts cleared.', 'checkout-script-monitor' ),
			'trusted'       => __( 'Added to your trusted list. We will not alert you about it again.', 'checkout-script-monitor' ),
			'untrusted'     => __( 'Removed from your trusted list.', 'checkout-script-monitor' ),
			'monitoring_on' => __( 'Monitoring is on. We will alert you if a new script appears on your checkout.', 'checkout-script-monitor' ),
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

	// ----------------------------------------------------------------- Home.

	public function render_home() {
		$this->guard();
		$snap        = $this->inventory->get_snapshot();
		$baseline    = $this->csp->get_baseline();
		$has_scan    = ! empty( $snap['scanned_at'] );
		$has_trusted = ! empty( $baseline['hosts'] );
		$monitoring  = $this->csp->is_enabled();
		$setup_done  = $has_scan && $has_trusted && $monitoring;
		$post_url    = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Checkout Scripts', 'checkout-script-monitor' ); ?></h1>
			<?php $this->notice_text(); ?>
			<p style="max-width:750px;font-size:14px;">
				<?php esc_html_e( 'This tool watches the code (called scripts) running on your checkout page and tells you when something new appears. Usually that is a routine update, but it can also be a sign your store was changed without your knowledge.', 'checkout-script-monitor' ); ?>
			</p>

			<?php if ( $setup_done ) : ?>
				<div class="notice notice-success inline" style="margin:16px 0;max-width:750px;"><p style="font-size:14px;">
					<strong><?php esc_html_e( 'You are all set.', 'checkout-script-monitor' ); ?></strong>
					<?php esc_html_e( 'Monitoring is on. If a new script appears on your checkout, it will show up under Alerts for you to review.', 'checkout-script-monitor' ); ?>
				</p></div>
			<?php else : ?>
				<?php $this->render_setup_card( $snap, $has_scan, $has_trusted, $monitoring, $post_url ); ?>
			<?php endif; ?>

			<?php if ( $has_scan ) { $this->render_scripts_table( $snap, $post_url ); } ?>
			<?php if ( $has_trusted ) { $this->render_trusted_list( $baseline, $post_url ); } ?>
			<?php $this->render_why(); ?>
		</div>
		<?php
	}

	private function render_setup_card( $snap, $has_scan, $has_trusted, $monitoring, $post_url ) {
		$count = $has_scan ? (int) $snap['total'] : 0;
		$check = '<span class="dashicons dashicons-yes-alt" style="color:#008a20;vertical-align:text-bottom;"></span> ';
		?>
		<div class="card" style="max-width:750px;margin:16px 0;padding:2px 20px 16px;">
			<h2><?php esc_html_e( 'Get set up in 3 steps', 'checkout-script-monitor' ); ?></h2>

			<p style="font-size:14px;margin:14px 0 2px;">
				<?php echo $has_scan ? wp_kses_post( $check ) : ''; ?><strong><?php esc_html_e( 'Step 1 — Check your checkout', 'checkout-script-monitor' ); ?></strong>
			</p>
			<p style="margin:0 0 8px;color:#50575e;"><?php esc_html_e( 'We look at your own store pages and list every script running on them.', 'checkout-script-monitor' ); ?></p>
			<?php if ( ! $has_scan ) : ?>
				<form method="post" action="<?php echo $post_url; // phpcs:ignore ?>" style="margin:0 0 6px;">
					<input type="hidden" name="action" value="csm_scan" />
					<?php wp_nonce_field( 'csm_scan' ); ?>
					<?php submit_button( __( 'Scan my checkout', 'checkout-script-monitor' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<p style="font-size:14px;margin:16px 0 2px;<?php echo $has_scan ? '' : 'opacity:.5;'; ?>">
				<?php echo $has_trusted ? wp_kses_post( $check ) : ''; ?><strong><?php esc_html_e( 'Step 2 — Mark them as trusted', 'checkout-script-monitor' ); ?></strong>
			</p>
			<p style="margin:0 0 8px;color:#50575e;<?php echo $has_scan ? '' : 'opacity:.5;'; ?>"><?php esc_html_e( 'If everything in the list below looks expected, save it as your trusted list. From then on we only flag scripts that are new.', 'checkout-script-monitor' ); ?></p>
			<?php if ( $has_scan && ! $has_trusted ) : ?>
				<form method="post" action="<?php echo $post_url; // phpcs:ignore ?>" style="margin:0 0 6px;">
					<input type="hidden" name="action" value="csm_rebaseline" />
					<?php wp_nonce_field( 'csm_rebaseline' ); ?>
					<?php
					/* translators: %d: number of scripts found. */
					submit_button( sprintf( _n( 'Trust the %d script found', 'Trust the %d scripts found', $count, 'checkout-script-monitor' ), $count ), 'primary', 'submit', false );
					?>
					<p class="description" style="margin-top:6px;"><?php esc_html_e( 'See something you do not recognize? Remove it from your store first, then scan again before you save the list.', 'checkout-script-monitor' ); ?></p>
				</form>
			<?php endif; ?>

			<p style="font-size:14px;margin:16px 0 2px;<?php echo $has_trusted ? '' : 'opacity:.5;'; ?>">
				<?php echo $monitoring ? wp_kses_post( $check ) : ''; ?><strong><?php esc_html_e( 'Step 3 — Turn on monitoring', 'checkout-script-monitor' ); ?></strong>
			</p>
			<p style="margin:0 0 8px;color:#50575e;<?php echo $has_trusted ? '' : 'opacity:.5;'; ?>"><?php esc_html_e( 'We keep watching your checkout and alert you if a new script shows up. This never changes or slows your store.', 'checkout-script-monitor' ); ?></p>
			<?php if ( $has_trusted && ! $monitoring ) : ?>
				<form method="post" action="<?php echo $post_url; // phpcs:ignore ?>" style="margin:0;">
					<input type="hidden" name="action" value="csm_enable_monitoring" />
					<?php wp_nonce_field( 'csm_enable_monitoring' ); ?>
					<?php submit_button( __( 'Turn on monitoring', 'checkout-script-monitor' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_scripts_table( $snap, $post_url ) {
		?>
		<h2><?php esc_html_e( 'What is running on your checkout now', 'checkout-script-monitor' ); ?></h2>
		<p>
			<?php
			/* translators: %s: date/time of the last scan. */
			printf( esc_html__( 'Last checked: %s.', 'checkout-script-monitor' ), esc_html( $snap['scanned_at'] ) );
			?>
			<form method="post" action="<?php echo $post_url; // phpcs:ignore ?>" style="display:inline;margin-left:8px;">
				<input type="hidden" name="action" value="csm_scan" />
				<?php wp_nonce_field( 'csm_scan' ); ?>
				<?php submit_button( __( 'Scan again', 'checkout-script-monitor' ), 'secondary small', 'submit', false ); ?>
			</form>
		</p>

		<?php if ( ! empty( $snap['errors'] ) ) : ?>
			<div class="notice notice-warning inline" style="max-width:750px;"><p>
				<?php esc_html_e( 'Some pages could not be checked:', 'checkout-script-monitor' ); ?>
				<?php
				$parts = array();
				foreach ( $snap['errors'] as $label => $msg ) {
					$parts[] = $label . ': ' . $msg;
				}
				echo esc_html( implode( '; ', $parts ) );
				?>
			</p></div>
		<?php endif; ?>

		<?php if ( empty( $snap['scripts'] ) ) : ?>
			<p><em><?php esc_html_e( 'No outside scripts found on your checkout. That is a simple, low-risk setup.', 'checkout-script-monitor' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px;">
				<thead><tr>
					<th><?php esc_html_e( 'Script', 'checkout-script-monitor' ); ?></th>
					<th><?php esc_html_e( 'Loaded by', 'checkout-script-monitor' ); ?></th>
					<th><?php esc_html_e( 'Tamper check', 'checkout-script-monitor' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $snap['scripts'] as $s ) : ?>
					<tr>
						<td><code style="font-size:12px;"><?php echo esc_html( $s['src'] ); ?></code></td>
						<td>
								<?php echo esc_html( $this->loaded_by( $s ) ); ?>
								<?php if ( ! empty( $s['third_party'] ) ) : ?>
									<span style="color:#996800;font-size:11px;white-space:nowrap;">&middot; <?php echo esc_html__( 'external', 'checkout-script-monitor' ); ?></span>
								<?php endif; ?>
							</td>
						<td><?php
							if ( ! empty( $s['has_sri'] ) ) {
								echo '<span style="color:#008a20;">' . esc_html__( 'Locked', 'checkout-script-monitor' ) . '</span>';
							} else {
								echo '<span style="color:#787c82;" title="' . esc_attr__( 'A tamper check (called SRI) lets the browser reject this script if it has been altered. It is optional and only some providers offer it.', 'checkout-script-monitor' ) . '">' . esc_html__( 'Not set', 'checkout-script-monitor' ) . '</span>';
							}
						?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="max-width:750px;"><?php esc_html_e( '"Loaded by" names the source of each script: a WordPress component or one of your own plugins/themes for first-party scripts, or the outside company for anything marked external. "Tamper check" is an optional extra lock some providers offer; a missing one is common and not itself a problem.', 'checkout-script-monitor' ); ?></p>
		<?php endif; ?>
		<?php
	}

	private function loaded_by( $s ) {
		if ( ! empty( $s['source'] ) ) {
			return $s['source'];
		}
		if ( empty( $s['third_party'] ) ) {
			return __( 'Your store', 'checkout-script-monitor' );
		}
		return __( 'Another company', 'checkout-script-monitor' );
	}

	private function render_trusted_list( $baseline, $post_url ) {
		?>
		<h2><?php esc_html_e( 'Your trusted script sources', 'checkout-script-monitor' ); ?></h2>
		<p style="max-width:750px;">
			<?php
			/* translators: %s: date the trusted list was saved. */
			printf( esc_html__( 'Saved on %s. These are the domains (website addresses) your checkout is allowed to load scripts from. We only alert you about scripts from anywhere else.', 'checkout-script-monitor' ), esc_html( $baseline['created_at'] ) );
			?>
		</p>
		<table class="widefat striped" style="max-width:600px;">
			<thead><tr>
				<th><?php esc_html_e( 'Trusted domain', 'checkout-script-monitor' ); ?></th>
				<th style="width:90px;"></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $baseline['hosts'] as $h ) : ?>
				<tr>
					<td><code><?php echo esc_html( $h ); ?></code></td>
					<td>
						<form method="post" action="<?php echo $post_url; // phpcs:ignore ?>" style="margin:0;">
							<input type="hidden" name="action" value="csm_untrust" />
							<input type="hidden" name="host" value="<?php echo esc_attr( $h ); ?>" />
							<?php wp_nonce_field( 'csm_untrust' ); ?>
							<?php submit_button( __( 'Remove', 'checkout-script-monitor' ), 'secondary small', 'submit', false ); ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo $post_url; // phpcs:ignore ?>" style="margin:12px 0 6px;">
			<input type="hidden" name="action" value="csm_rebaseline" />
			<?php wp_nonce_field( 'csm_rebaseline' ); ?>
			<?php submit_button( __( 'Add anything new from my latest scan', 'checkout-script-monitor' ), 'secondary', 'submit', false ); ?>
		</form>
		<p class="description" style="max-width:750px;"><?php esc_html_e( 'This adds any new domains found in your most recent scan and keeps everything you already trust. It never removes a domain on its own, so a script that is temporarily unavailable will not be dropped. To remove a domain, use Remove next to it.', 'checkout-script-monitor' ); ?></p>
		<?php
	}

	private function render_why() {
		?>
		<details style="margin-top:22px;max-width:750px;">
			<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Why this matters (and the PCI DSS 6.4.3 connection)', 'checkout-script-monitor' ); ?></summary>
			<div style="padding:8px 0;color:#3c434a;">
				<p><?php esc_html_e( 'Attackers who slip a hidden script onto a checkout page can quietly copy your customers\' card details. So knowing exactly what runs on your checkout, and noticing when it changes, is a basic security habit for any online store.', 'checkout-script-monitor' ); ?></p>
				<p><?php esc_html_e( 'It also lines up with a PCI DSS rule (Requirement 6.4.3) that asks merchants to keep a list of the scripts on their payment pages and be able to tell when one changes. This tool helps you do that. It is a readiness and visibility aid; it does not make you PCI compliant, and that decision stays yours.', 'checkout-script-monitor' ); ?></p>
				<p><em><?php esc_html_e( 'One limit worth knowing: the scan sees scripts written into the page. Scripts added later by another script (like a tag manager) may not show here, which is one more reason to turn on monitoring.', 'checkout-script-monitor' ); ?></em></p>
			</div>
		</details>
		<?php
	}

	// --------------------------------------------------------------- Alerts.

	public function render_alerts() {
		$this->guard();
		$enabled  = $this->csp->is_enabled();
		$baseline = $this->csp->get_baseline();
		$rows     = $this->csp->recent_violations( 100 );
		$post_url = esc_url( admin_url( 'admin-post.php' ) );
		$home_url = esc_url( admin_url( 'admin.php?page=checkout-script-monitor' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Alerts', 'checkout-script-monitor' ); ?></h1>
			<?php $this->notice_text(); ?>
			<p style="max-width:750px;font-size:14px;"><?php esc_html_e( 'These are scripts that have appeared on your checkout but are not on your trusted list. Often it is just an update from a tool you already use, but it can also mean something was added without your knowledge. For each one: trust it if you recognize it, or get it removed from your store if you do not.', 'checkout-script-monitor' ); ?></p>

			<?php if ( ! $enabled ) : ?>
				<div class="notice notice-warning inline" style="max-width:750px;"><p><?php
					printf(
						/* translators: %s: Checkout Scripts link. */
						esc_html__( 'Monitoring is off, so nothing is being tracked yet. Turn it on from %s.', 'checkout-script-monitor' ),
						'<a href="' . $home_url . '">' . esc_html__( 'Checkout Scripts', 'checkout-script-monitor' ) . '</a>' // phpcs:ignore
					);
				?></p></div>
			<?php elseif ( empty( $baseline['hosts'] ) ) : ?>
				<div class="notice notice-warning inline" style="max-width:750px;"><p><?php
					printf(
						/* translators: %s: Checkout Scripts link. */
						esc_html__( 'You have not set a trusted list yet, so we cannot tell what is new. Finish setup on %s.', 'checkout-script-monitor' ),
						'<a href="' . $home_url . '">' . esc_html__( 'Checkout Scripts', 'checkout-script-monitor' ) . '</a>' // phpcs:ignore
					);
				?></p></div>
			<?php endif; ?>

			<?php if ( $rows ) : ?>
				<table class="widefat striped" style="max-width:1000px;margin-top:12px;">
					<thead><tr>
						<th><?php esc_html_e( 'New script', 'checkout-script-monitor' ); ?></th>
						<th><?php esc_html_e( 'Times seen', 'checkout-script-monitor' ); ?></th>
						<th><?php esc_html_e( 'Last seen', 'checkout-script-monitor' ); ?></th>
						<th><?php esc_html_e( 'What to do', 'checkout-script-monitor' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><code style="font-size:12px;"><?php echo esc_html( $r->blocked_uri ); ?></code></td>
							<td><?php echo (int) $r->hits; ?></td>
							<td><?php echo esc_html( $r->last_seen ); ?></td>
							<td>
								<form method="post" action="<?php echo $post_url; // phpcs:ignore ?>" style="display:inline;">
									<input type="hidden" name="action" value="csm_trust" />
									<input type="hidden" name="uri" value="<?php echo esc_attr( $r->blocked_uri ); ?>" />
									<?php wp_nonce_field( 'csm_trust' ); ?>
									<?php submit_button( __( 'Trust this', 'checkout-script-monitor' ), 'secondary small', 'submit', false ); ?>
								</form>
								<details style="display:inline-block;margin-left:8px;vertical-align:middle;">
									<summary style="cursor:pointer;color:#2271b1;font-weight:400;"><?php esc_html_e( 'I do not recognize this', 'checkout-script-monitor' ); ?></summary>
									<div style="padding:6px 0;max-width:440px;color:#3c434a;font-weight:400;"><?php esc_html_e( 'If you did not add this and do not recognize the company, treat it as a red flag. Ask whoever manages your website or plugins to find and remove the script, then scan again. Use the free outside check linked at the top of the page if you want a second opinion.', 'checkout-script-monitor' ); ?></div>
								</details>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<form method="post" action="<?php echo $post_url; // phpcs:ignore ?>" style="margin:14px 0;">
					<input type="hidden" name="action" value="csm_clear_violations" />
					<?php wp_nonce_field( 'csm_clear_violations' ); ?>
					<?php submit_button( __( 'Clear all alerts', 'checkout-script-monitor' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php elseif ( $enabled && ! empty( $baseline['hosts'] ) ) : ?>
				<div class="notice notice-success inline" style="margin-top:12px;max-width:750px;"><p><?php esc_html_e( 'All clear. Nothing new has appeared on your checkout since you set your trusted list.', 'checkout-script-monitor' ); ?></p></div>
			<?php else : ?>
				<p><em><?php esc_html_e( 'No alerts yet.', 'checkout-script-monitor' ); ?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	// ------------------------------------------------------------- Settings.

	public function render_settings() {
		$this->guard();
		$enabled  = $this->csp->is_enabled();
		$post_url = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'checkout-script-monitor' ); ?></h1>
			<?php $this->notice_text(); ?>

			<form method="post" action="<?php echo $post_url; // phpcs:ignore ?>">
				<input type="hidden" name="action" value="csm_save_settings" />
				<?php wp_nonce_field( 'csm_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Monitoring', 'checkout-script-monitor' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="csp_report_only" value="1" <?php checked( $enabled ); ?> />
								<?php esc_html_e( 'Watch my checkout and alert me when a new script appears', 'checkout-script-monitor' ); ?>
							</label>
							<p class="description" style="max-width:700px;"><?php esc_html_e( 'This never blocks, changes, or slows anything for your customers. It quietly compares what loads on your cart and checkout against your trusted list and shows anything new under Alerts. Set your trusted list first (see Checkout Scripts).', 'checkout-script-monitor' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', 'checkout-script-monitor' ) ); ?>
			</form>

			<details style="margin-top:16px;max-width:700px;">
				<summary style="cursor:pointer;color:#50575e;"><?php esc_html_e( 'Technical details', 'checkout-script-monitor' ); ?></summary>
				<p style="color:#50575e;"><?php esc_html_e( 'Monitoring sends a Content-Security-Policy-Report-Only header on the cart and checkout, built from your trusted list. Browsers report (but never block) any script outside that list to a private endpoint on your own site (/wp-json/checkout-script-monitor/v1/csp-report). Reports are stored in your own database; the visitor IP address and referrer are dropped and never saved.', 'checkout-script-monitor' ); ?></p>
			</details>
		</div>
		<?php
	}
}
