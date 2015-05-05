<?php
class SMTPMailingQueueAdmin {

	/**
	 * Slug of currently active tab
	 *
	 * @var string
	 */
	protected $activeTab;

	public function __construct() {
		if(is_admin())
			$this->init();
	}

	/**
	 * Sets property for currently active tab
	 */
	private function init() {
		$this->activeTab = isset($_GET['tab']) ? $_GET[ 'tab' ] : 'settings';
	}

	/**
	 * Adds plugin settings page to admin
	 */
	public function add_plugin_page() {
		add_options_page(
			'SMTP Mailing Queue',               // page_title
			'SMTP Mailing Queue',               // menu_title
			'manage_options',                   // capability
			'smtp-mailing-queue',               // menu_slug
			[$this, 'create_admin_page']        // callback function
		);
	}

	/**
	 * Creates header of admin settings page
	 * Expects loadPageContent() to exist in child class
	 */
	public function create_admin_page() {
		?><div class="wrap">
			<h2>SMTP Mailing Queue</h2>

			<h2 class="nav-tab-wrapper">
				<a href="?page=smtp-mailing-queue&tab=settings" class="nav-tab <?php echo $this->activeTab == 'settings' ? 'nav-tab-active' : '' ?>">SMTP Settings</a>
				<a href="?page=smtp-mailing-queue&tab=advanced" class="nav-tab <?php echo $this->activeTab == 'advanced' ? 'nav-tab-active' : '' ?>">Advanced Settings</a>
				<a href="?page=smtp-mailing-queue&tab=tools" class="nav-tab <?php echo $this->activeTab == 'tools' ? 'nav-tab-active' : '' ?>">Tools</a>
			</h2>

			<?php call_user_func([$this, 'loadPageContent']); ?>
		</div><?php
	}

}

