/**
 * The plugin class.
 *
 * @since      1.0.0
 * @package    OPBCRM
 * @subpackage OPBCRM/includes
 * @author     Your Name <you@example.com>
 */
class OPBCRM {

	public $version;

	/**
	 * The single instance of the class.
	 * @var OPBCRM
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * The activity handler instance.
	 * @var OPBCRM_Activity
	 * @since 1.0.0
	 */
	public $activity = null;

	/**
	 * Main OPBCRM Instance.
	 *
	 * @since 1.0.0
	 * @static
	 * @return OPBCRM - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {
		$this->version = '1.0.0';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_frontend_hooks();

		$this->activity = new OPBCRM_Activity();
	}

	private function load_dependencies() {

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-opbcrm-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-opbcrm-roles.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/frontend/class-opbcrm-frontend-dashboard.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-opbcrm-activity.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/post-types/class-opbcrm-proposal-cpt.php';

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 */
	private function define_admin_hooks() {
		// ... existing code ...
	}

	/**
	 * Register all of the hooks related to the frontend functionality
	 */
	private function define_frontend_hooks() {
		// ... existing code ...
	}

} 