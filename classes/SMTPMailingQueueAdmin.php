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
			__('SMTP Mailing Queue', 'smtp-mailing-queue'), // page_title
			__('SMTP Mailing Queue', 'smtp-mailing-queue'), // menu_title
			'manage_options',                               // capability
			'smtp-mailing-queue',                           // menu_slug
			[$this, 'create_admin_page']                    // callback function
		);
	}

	/**
	 * Creates header of admin settings page
	 * Expects loadPageContent() to exist in child class
	 */
	public function create_admin_page() {
		?><div class="wrap">
			<h2><?=__('SMTP Mailing Queue', 'smtp-mailing-queue')?></h2>

			<h2 class="nav-tab-wrapper">
				<a href="?page=smtp-mailing-queue&tab=settings" class="nav-tab <?php echo $this->activeTab == 'settings' ? 'nav-tab-active' : '' ?>"><?=__('SMTP Settings', 'smtp-mailing-queue')?></a>
				<a href="?page=smtp-mailing-queue&tab=advanced" class="nav-tab <?php echo $this->activeTab == 'advanced' ? 'nav-tab-active' : '' ?>"><?=__('Advanced Settings', 'smtp-mailing-queue')?></a>
				<a href="?page=smtp-mailing-queue&tab=tools" class="nav-tab <?php echo $this->activeTab == 'tools' ? 'nav-tab-active' : '' ?>"><?=__('Tools', 'smtp-mailing-queue')?></a>
			</h2>

			<?php call_user_func([$this, 'loadPageContent']); ?>
		</div><?php
	}

}

