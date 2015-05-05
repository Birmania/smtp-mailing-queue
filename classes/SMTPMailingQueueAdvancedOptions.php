<?php
require_once('SMTPMailingQueueAdmin.php');

class SMTPMailingQueueAdvancedOptions extends SMTPMailingQueueAdmin {

	/**
	 * @var array Stored options
	 */
	private $options;

	/**
	 * @var string Slug of this tab's settings
	 */
	private $optionName = 'smtp_mailing_queue_advanced';

	/**
	 * @var string Name of this tab
	 */
	private $tabName = 'advanced';

	public function __construct() {
		parent::__construct();
		$this->init();
	}

	/**
	 * Loads content if this tab is active
	 */
	private function init() {
		$this->options = get_option( $this->optionName );
		if(is_admin() && $this->activeTab == $this->tabName)
			add_action('admin_menu', [$this, 'add_plugin_page']);
		add_action('admin_init', [$this, 'page_init']);
	}

	/**
	 * Prints page content
	 */
	public function loadPageContent() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'smq_advanced' );
			do_settings_sections( 'smtp-mailing-queue-advanced' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Loads settings fields
	 */
	public function page_init() {
		register_setting(
			'smq_advanced',                                 // option_group
			$this->optionName,                              // option_name
			[$this, 'sanitize']                             // sanitize_callback
		);

		add_settings_section(
			'settings_section',                             // id
			'',                                             // title
			[$this, 'section_info'],                        // callback
			'smtp-mailing-queue-advanced'                   // page
		);

		add_settings_field(
			'queue_limit',                                  // id
			'Queue Limit',                                  // title
			[$this, 'queue_limit_callback'],                // callback
			'smtp-mailing-queue-advanced',                  // page
			'settings_section'                              // section
		);

		add_settings_field(
			'process_key',                                  // id
			'Secret Key',                                   // title
			[$this, 'process_key_callback'],                // callback
			'smtp-mailing-queue-advanced',                  // page
			'settings_section'                              // section
		);

		add_settings_field(
			'dont_use_wpcron',                              // id
			'Don\'t use wp_cron',                           // title
			[$this, 'dont_use_wpcron_callback'],            // callback
			'smtp-mailing-queue-advanced',                  // page
			'settings_section'                              // section
		);

		add_settings_field(
			'wpcron_interval',                              // id
			'wp_cron interval',                             // title
			[$this, 'wpcron_interval_callback'],            // callback
			'smtp-mailing-queue-advanced',                  // page
			'settings_section'                              // section
		);
	}

	/**
	 * Sanitizes settings form input
	 *
	 * @param array $input
	 *
	 * @return array
	 */
	public function sanitize($input) {
		global $smtpMailingQueue;
		$sanitary_values = array();
		if(isset( $input['queue_limit']))
			$sanitary_values['queue_limit'] = intval($input['queue_limit']);
		if(isset( $input['wpcron_interval'])) {
			$sanitary_values['wpcron_interval'] = intval($input['wpcron_interval']);
			$smtpMailingQueue->refreshWpCron();
		}
		if(isset($input['dont_use_wpcron'])) {
			$sanitary_values['dont_use_wpcron'] = 'dont_use_wpcron';
			wp_clear_scheduled_hook('smq_start_queue');
		} else
			$smtpMailingQueue->refreshWpCron();
		if(isset($input['process_key']))
			$sanitary_values['process_key'] = sanitize_text_field($input['process_key']);

		return $sanitary_values;
	}

	/**
	 * Prints tab section info
	 *
	 * @param array $arg
	 */
	public function section_info($arg) {
		// place holder function
	}

	/**
	 * Prints queue limit field
	 */
	public function queue_limit_callback() {
		printf(
			'<input class="small-text" type="number" name="' . $this->optionName . '[queue_limit]" id="queue_limit" value="%s">',
			isset($this->options['queue_limit']) ? esc_attr($this->options['queue_limit']) : ''
		);
		echo '<p class="description">Set the amount of mails sent per cronjob processing.</p>';
	}

	/**
	 * Prints interval field
	 */
	public function wpcron_interval_callback() {
		printf(
			'<input class="small-text" type="number" name="' . $this->optionName . '[wpcron_interval]" id="wpcron_interval" value="%s">',
			isset($this->options['wpcron_interval']) ? esc_attr($this->options['wpcron_interval']) : '60'
		);
		echo '<p class="description">Choose how often wp_cron is started (in seconds).</p>';
	}

	/**
	 * Prints checkbox field for selecting whether to use wp_cron or not
	 */
	public function dont_use_wpcron_callback() {
		global $smtpMailingQueue;
		printf(
			'<input type="checkbox" name="' . $this->optionName . '[dont_use_wpcron]" id="dont_use_wpcron" value="dont_use_wpcron" %s> <label for="dont_use_wpcron">Use a real cronjob instead of wp_cron.</label>',
			(isset($this->options['dont_use_wpcron']) && $this->options['dont_use_wpcron'] === 'dont_use_wpcron') ? 'checked' : ''
		);
		echo '<p class="description">Call <strong>' . $smtpMailingQueue->getCronLink() . '</strong> in cronjob to start processing queue.</p>';
	}

	/**
	 * Prints field for secret key
	 */
	public function process_key_callback() {
		printf(
			'<input class="regular-text" type="text" name="' . $this->optionName . '[process_key]" id="process_key" value="%s">',
			isset($this->options['process_key']) ? esc_attr($this->options['process_key']) : ''
		);
		echo '<p class="description">Set a key needed to start queue manually or via cronjob.</p>';
	}
}