<?php
/**
 * Admin menu, requests list/detail screens and status-change handling.
 *
 * @package MS\WcRecesso
 */

namespace MS\WcRecesso\Admin;

use MS\WcRecesso\Domain\WithdrawalService;
use MS\WcRecesso\Model\RequestRepository;
use MS\WcRecesso\Model\RequestStatus;
use MS\WcRecesso\Model\WithdrawalRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Recesso" admin menu and renders the requests list and detail
 * views, plus the administrative status transitions.
 */
final class AdminPages {

	private const CAP           = 'manage_woocommerce';
	private const CHANGE_ACTION = 'ms_wc_recesso_change_status';

	/**
	 * Repository.
	 *
	 * @var RequestRepository
	 */
	private RequestRepository $repository;

	/**
	 * Service.
	 *
	 * @var WithdrawalService
	 */
	private WithdrawalService $service;

	/**
	 * Settings page.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new RequestRepository();
		$this->service    = new WithdrawalService( $this->repository );
		$this->settings   = new SettingsPage();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_' . self::CHANGE_ACTION, array( $this, 'handle_change_status' ) );
		$this->settings->register();
	}

	/**
	 * Register the admin menu.
	 */
	public function menu(): void {
		add_menu_page(
			__( 'Recesso 54-bis', 'ms-wc-recesso' ),
			__( 'Recesso', 'ms-wc-recesso' ),
			self::CAP,
			RequestsListTable::PAGE,
			array( $this, 'render_requests_page' ),
			'dashicons-undo',
			56
		);

		add_submenu_page(
			RequestsListTable::PAGE,
			__( 'Richieste di recesso', 'ms-wc-recesso' ),
			__( 'Richieste', 'ms-wc-recesso' ),
			self::CAP,
			RequestsListTable::PAGE,
			array( $this, 'render_requests_page' )
		);

		add_submenu_page(
			RequestsListTable::PAGE,
			__( 'Impostazioni recesso', 'ms-wc-recesso' ),
			__( 'Impostazioni', 'ms-wc-recesso' ),
			self::CAP,
			SettingsPage::PAGE,
			array( $this->settings, 'render' )
		);
	}

	/**
	 * Render the requests page: detail when a request is selected, else list.
	 */
	public function render_requests_page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation via UUID in the URL.
		$uuid = isset( $_GET['request'] ) ? sanitize_text_field( wp_unslash( $_GET['request'] ) ) : '';

		if ( '' !== $uuid ) {
			$this->render_detail( $uuid );
			return;
		}

		$this->render_list();
	}

	/**
	 * Render the list view.
	 */
	private function render_list(): void {
		$table = new RequestsListTable( $this->repository );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Richieste di recesso', 'ms-wc-recesso' ); ?></h1>
			<hr class="wp-header-end" />
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( RequestsListTable::PAGE ); ?>" />
				<?php
				$table->search_box( __( 'Cerca', 'ms-wc-recesso' ), 'ms-wc-recesso-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the detail view for a request.
	 *
	 * @param string $uuid Request UUID.
	 */
	private function render_detail( string $uuid ): void {
		$request = $this->repository->get_by_uuid( $uuid );

		if ( ! $request instanceof WithdrawalRequest ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Richiesta non trovata.', 'ms-wc-recesso' ) . '</p></div>';
			return;
		}

		$logs  = $this->repository->get_logs( $request->id );
		$order = null !== $request->order_id ? wc_get_order( $request->order_id ) : null;

		require MS_WC_RECESSO_DIR . 'templates/admin/request-detail.php';
	}

	/**
	 * Handle an administrative status change.
	 */
	public function handle_change_status(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'ms-wc-recesso' ) );
		}

		check_admin_referer( self::CHANGE_ACTION );

		$uuid   = isset( $_POST['request'] ) ? sanitize_text_field( wp_unslash( $_POST['request'] ) ) : '';
		$target = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';

		$request = '' !== $uuid ? $this->repository->get_by_uuid( $uuid ) : null;
		$status  = RequestStatus::tryFrom( $target );

		if ( $request instanceof WithdrawalRequest && $status instanceof RequestStatus ) {
			try {
				$this->service->transition_status( $request, $status, get_current_user_id() );
			} catch ( \RuntimeException $e ) {
				unset( $e );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => RequestsListTable::PAGE,
					'request' => rawurlencode( $uuid ),
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
