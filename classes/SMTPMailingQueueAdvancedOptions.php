<?php
require_once('SMTPMailingQueueAdmin.php');

class SMTPMailingQueueAdvancedOptions extends SMTPMailingQueueAdmin {

	/**
	 * @var SMTPMailingQueue
	 */
	private $smtpMailingQueue;

	/**
	 * @var array Stored options
	 */
	private $options;

	/**
	 * @var string Slug of this tab's settings
	 */
	public $optionName = 'smtp_mailing_queue_advanced';

	/**
	 * @var string Name of this tab
	 */
	private $tabName = 'advanced';

	public function __construct(SMTPMailingQueue $smtpMailingQueue) {
		parent::__construct();
		$this->smtpMailingQueue = $smtpMailingQueue;
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
			'smq_advanced',                                         // option_group
			$this->optionName,                                      // option_name
			[$this, 'sanitize']                                     // sanitize_callback
		);

		add_settings_section(
			'settings_section',                                     // id
			'',                                                     // title
			[$this, 'section_info'],                                // callback
			'smtp-mailing-queue-advanced'                           // page
		);

		add_settings_field(
			'queue_limit',                                          // id
			__('Queue Limit', 'smtp-mailing-queue'),                // title
			[$this, 'queue_limit_callback'],                        // callback
			'smtp-mailing-queue-advanced',                          // page
			'settings_section'                                      // section
		);

		add_settings_field(
			'process_key',                                          // id
			__('Secret Key', 'smtp-mailing-queue'),                 // title
			[$this, 'process_key_callback'],                        // callback
			'smtp-mailing-queue-advanced',                          // page
			'settings_section'                                      // section
		);

		add_settings_field(
			'dont_use_wpcron',                                      // id
			__('Don\'t use wp_cron', 'smtp-mailing-queue'),         // title
			[$this, 'dont_use_wpcron_callback'],                    // callback
			'smtp-mailing-queue-advanced',                          // page
			'settings_section'                                      // section
		);

		add_settings_field(
			'wpcron_interval',                                      // id
			__('wp_cron interval', 'smtp-mailing-queue'),           // title
			[$this, 'wpcron_interval_callback'],                    // callback
			'smtp-mailing-queue-advanced',                          // page
			'settings_section'                                      // section
		);

		add_settings_field(
			'min_recipients',                                       // id
			__('Min. recipients to enqueue', 'smtp-mailing-queue'), // title
			[$this, 'min_recipients_callback'],                     // callback
			'smtp-mailing-queue-advanced',                          // page
			'settings_section'                                      // section
		);
		
		add_settings_field(
			'max_retry',                                             // id
			__('Max. retry for mail sending', 'smtp-mailing-queue'), // title
			[$this, 'max_retry_callback'],                           // callback
			'smtp-mailing-queue-advanced',                           // page
			'settings_section'                                       // section
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
		$sanitary_values = array();
		if(isset( $input['queue_limit']))
			$sanitary_values['queue_limit'] = intval($input['queue_limit']);
		if(isset( $input['wpcron_interval'])) {
			$sanitary_values['wpcron_interval'] = intval($input['wpcron_interval']);
			$this->smtpMailingQueue->refreshWpCron();
		}
		if(isset($input['dont_use_wpcron'])) {
			$sanitary_values['dont_use_wpcron'] = 'dont_use_wpcron';
			wp_clear_scheduled_hook('smq_start_queue');
		} else
			$this->smtpMailingQueue->refreshWpCron();
		if(isset($input['process_key']))
			$sanitary_values['process_key'] = sanitize_text_field($input['process_key']);

		if(isset($input['min_recipients']))
			$sanitary_values['min_recipients'] = intval($input['min_recipients']);

		if(isset($input['max_retry']))
			$sanitary_values['max_retry'] = intval($input['max_retry']);
		
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
			'<input class="small-text" type="number" name="%s[queue_limit]" id="queue_limit" value="%s">',
			$this->optionName,
			isset($this->options['queue_limit']) ? esc_attr($this->options['queue_limit']) : ''
		);
		echo '<p class="description">' . __('Amount of mails processed per cronjob execution.', 'smtp-mailing-queue') . '</p>';
	}

	/**
	 * Prints queue limit field
	 */
	public function min_recipients_callback() {
		printf(
			'<input class="small-text" type="number" name="%s[min_recipients]" id="min_recipients" value="%s">',
			$this->optionName,
			isset($this->options['min_recipients']) ? esc_attr($this->options['min_recipients']) : ''
		);
		echo '<p class="description">' . __('Minimum amount of recipients required to enqueue mail instead of sending immediately.', 'smtp-mailing-queue') . '</p>';
	}
	
	/**
	 * Prints queue limit field
	 */
	public function max_retry_callback() {
		printf(
			'<input class="small-text" type="number" name="%s[max_retry]" id="max_retry" value="%s">',
			$this->optionName,
			isset($this->options['max_retry']) ? esc_attr($this->options['max_retry']) : ''
		);
		echo '<p class="description">' . __('Maximum number of retry for mail sending.', 'smtp-mailing-queue') . '</p>';
	}

	/**
	 * Prints interval field
	 */
	public function wpcron_interval_callback() {
		printf(
			'<input class="small-text" type="number" name="%s[wpcron_interval]" id="wpcron_interval" value="%s">',
			$this->optionName,
			isset($this->options['wpcron_interval']) ? esc_attr($this->options['wpcron_interval']) : '60'
		);
		echo '<p class="description">' . __('Time in seconds wp_cron waits until next execution.', 'smtp-mailing-queue') . '</p>';
	}

	/**
	 * Prints checkbox field for selecting whether to use wp_cron or not
	 */
	public function dont_use_wpcron_callback() {
		printf(
			'<input type="checkbox" name="%s[dont_use_wpcron]" id="dont_use_wpcron" value="dont_use_wpcron" %s> <label for="dont_use_wpcron">' . __('Use a real cronjob instead of wp_cron.', 'smtp-mailing-queue') . '</label>',
			$this->optionName,
			(isset($this->options['dont_use_wpcron']) && $this->options['dont_use_wpcron'] === 'dont_use_wpcron') ? 'checked' : ''
		);
		echo '<p class="description">' . sprintf(__('Call %s in cron to start processing queue.', 'smtp-mailing-queue'), '<strong>' . $this->smtpMailingQueue->getCronLink() . '</strong>') . '</p>';
	}

	/**
	 * Prints field for secret key
	 */
	public function process_key_callback() {
		printf(
			'<input class="regular-text" type="text" name="%s[process_key]" id="process_key" value="%s">',
			$this->optionName,
			isset($this->options['process_key']) ? esc_attr($this->options['process_key']) : ''
		);
		echo '<p class="description">' . __('This key is needed to start processing the queue manually or via cronjob.', 'smtp-mailing-queue') . '</p>';
	}
}