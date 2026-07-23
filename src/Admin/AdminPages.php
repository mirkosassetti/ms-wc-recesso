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
		add_action( 'admin_init', array( $this, 'maybe_handle_bulk_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'set_screen_option_' . RequestsListTable::PER_PAGE_OPTION, array( $this, 'save_screen_option' ), 10, 3 );
		$this->settings->register();
	}

	/**
	 * Register the admin menu.
	 */
	public function menu(): void {
		$hook = add_menu_page(
			__( 'Recesso 54-bis', 'ms-wc-recesso' ),
			__( 'Recesso', 'ms-wc-recesso' ),
			self::CAP,
			RequestsListTable::PAGE,
			array( $this, 'render_requests_page' ),
			'dashicons-undo',
			56
		);

		add_action( 'load-' . $hook, array( $this, 'add_screen_options' ) );

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
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success flag after a redirect.
			$ms_deleted = isset( $_GET['deleted'] ) ? absint( wp_unslash( $_GET['deleted'] ) ) : 0;
			if ( $ms_deleted > 0 ) :
				?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					printf(
						/* translators: %d: number of deleted requests. */
						esc_html( _n( '%d richiesta eliminata.', '%d richieste eliminate.', $ms_deleted, 'ms-wc-recesso' ) ),
						(int) $ms_deleted
					);
					?>
				</p></div>
			<?php endif; ?>
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

	/**
	 * Handle the "delete" bulk action on the requests list (PRG: redirect after).
	 */
	public function maybe_handle_bulk_delete(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing check; the nonce is verified below before any deletion.
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( RequestsListTable::PAGE !== $page ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing check; nonce verified below.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing check; nonce verified below.
		$action2 = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '';
		if ( 'delete' !== $action && 'delete' !== $action2 ) {
			return;
		}

		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		check_admin_referer( 'bulk-ms_wc_recesso_requests' );

		$ids     = isset( $_REQUEST['request_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['request_ids'] ) ) : array();
		$deleted = ! empty( $ids ) ? $this->repository->delete_many( $ids ) : 0;

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => RequestsListTable::PAGE,
					'deleted' => (int) $deleted,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue the admin confirmation script on the requests list page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check for asset loading.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( RequestsListTable::PAGE !== $page ) {
			return;
		}

		wp_enqueue_script(
			'ms-wc-recesso-admin',
			MS_WC_RECESSO_URL . 'assets/js/admin.js',
			array(),
			MS_WC_RECESSO_VERSION,
			true
		);

		wp_localize_script(
			'ms-wc-recesso-admin',
			'msRecessoAdmin',
			array(
				'confirmDelete' => __( 'Eliminare definitivamente le richieste selezionate? L’operazione non è reversibile.', 'ms-wc-recesso' ),
			)
		);
	}

	/**
	 * Register the standard "items per page" screen option on the list page.
	 */
	public function add_screen_options(): void {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'Richieste per pagina', 'ms-wc-recesso' ),
				'default' => 20,
				'option'  => RequestsListTable::PER_PAGE_OPTION,
			)
		);
	}

	/**
	 * Persist the chosen "items per page" value.
	 *
	 * @param mixed  $status Default screen-option value (false).
	 * @param string $option Option name.
	 * @param mixed  $value  Submitted per-page value.
	 */
	public function save_screen_option( $status, $option, $value ): int {
		return max( 1, absint( $value ) );
	}
}
