<?php
require_once('SMTPMailingQueueAdmin.php');

class SMTPMailingQueueOptions extends SMTPMailingQueueAdmin{

	/**
	 * @var SMTPMailingQueue
	 */
	private $smtpMailingQueue;

	/**
	 * @var array Stored options
	 */
	private $options;

	/**
	 * @var string Name of this tab's settings
	 */
	private $optionName = 'smtp_mailing_queue_options';

	/**
	 * @var string Slug of this tab
	 */
	private $tabName = 'settings';

	/**
	 * @var bool Are options already sanitized
	 */
	private $optionsSanitized = false;

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
			settings_fields( 'smq_options' );
			do_settings_sections( 'smtp-mailing-queue-options' );
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
			'smq_options',                                  // option_group
			$this->optionName,                              // option_name
			[$this, 'sanitize']                             // sanitize_callback
		);

		add_settings_section(
			'settings_section',                             // id
			'',                                             // title
			[$this, 'section_info'],                        // callback
			'smtp-mailing-queue-options'                    // page
		);

		add_settings_field(
			'from_name',                                    // id
			__('From Name', 'smtp-mailing-queue'),          // title
			[$this, 'from_name_callback'],                  // callback
			'smtp-mailing-queue-options',                   // page
			'settings_section'                              // section
		);

		add_settings_field(
			'from_email',                                   // id
			__('From Email', 'smtp-mailing-queue'),         // title
			[$this, 'from_email_callback'],                 // callback
			'smtp-mailing-queue-options',                   // page
			'settings_section'                              // section
		);

		add_settings_field(
			'encryption',                                   // id
			__('Encryption', 'smtp-mailing-queue'),         // title
			[$this, 'encryption_callback'],                 // callback
			'smtp-mailing-queue-options',                   // page
			'settings_section'                              // section
		);

		add_settings_field(
			'host',                                         // id
			__('Host', 'smtp-mailing-queue'),               // title
			[$this, 'host_callback'],                       // callback
			'smtp-mailing-queue-options',                   // page
			'settings_section'                              // section
		);

		add_settings_field(
			'port',                                         // id
			__('Port', 'smtp-mailing-queue'),               // title
			[$this, 'port_callback'],                       // callback
			'smtp-mailing-queue-options',                   // page
			'settings_section'                              // section
		);

		add_settings_field(
			'use_authentication',                           // id
			__('Use authentication', 'smtp-mailing-queue'), // title
			array( $this, 'use_authentication_callback' ),  // callback
			'smtp-mailing-queue-options',                   // page
			'settings_section'                              // section
		);

		add_settings_field(
			'auth_username',                                // id
			__('Username', 'smtp-mailing-queue'),           // title
			[$this, 'auth_username_callback'],              // callback
			'smtp-mailing-queue-options',                   // page
			'settings_section'                              // section
		);

		add_settings_field(
			'auth_password',                                // id
			__('Password', 'smtp-mailing-queue'),           // title
			[$this, 'auth_password_callback'],              // callback
			'smtp-mailing-queue-options',                   // page
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
		// Fix for issue that options are sanitized twice when no db entry exists
		// "It seems the data is passed through the sanitize function twice.[...]
		// This should only happen when the option is not yet in the wp_options table."
		// @see https://codex.wordpress.org/Function_Reference/register_setting#Notes
		if($this->optionsSanitized)
			return $input;
		$this->optionsSanitized =  true;

		$sanitary_values = array();
		if(isset( $input['queue_limit']))
			$sanitary_values['queue_limit'] = intval( $input['queue_limit'] );
		if(isset( $input['from_name']))
			$sanitary_values['from_name'] = sanitize_text_field($input['from_name']);
		if( isset( $input['from_email'] ) )
			$sanitary_values['from_email'] = sanitize_text_field($input['from_email']);
		if(isset($input['encryption']))
			$sanitary_values['encryption'] = $input['encryption'];
		if(isset($input['host']))
			$sanitary_values['host'] = sanitize_text_field($input['host']);
		if(isset($input['port']))
			$sanitary_values['port'] = sanitize_text_field($input['port']);
		if(isset($input['use_authentication']))
			$sanitary_values['use_authentication'] = 'use_authentication';
		if(isset($input['auth_username']))
			$sanitary_values['auth_username'] = sanitize_text_field($input['auth_username']);
		if(isset($input['auth_password']))
			$sanitary_values['auth_password'] = $this->smtpMailingQueue->encrypt($input['auth_password']);

		return $sanitary_values;
	}

	/**
	 * Prints tab section info
	 */
	public function section_info() {
		?><p>
		<?=__('Enter the SMTP credentials you got from your mail provider. Leave blank, if you don\'t want to  use SMTP.', 'smtp-mailing-queue')?>
		</p><?php
	}

	/**
	 * Prints field for SMTP from name
	 */
	public function from_name_callback() {
		printf(
			'<input class="regular-text" type="text" name="%s[from_name]" id="from_name" value="%s">',
			$this->optionName,
			isset($this->options['from_name']) ? esc_attr($this->options['from_name']) : ''
		);
	}

	/**
	 * Prints field for SMTP from email
	 */
	public function from_email_callback() {
		printf(
			'<input class="regular-text" type="text" name="%s[from_email]" id="from_email" value="%s">',
			$this->optionName,
			isset($this->options['from_email']) ? esc_attr($this->options['from_email']) : ''
		);
	}

	/**
	 * Prints select box for SMTP encryption type
	 */
	public function encryption_callback() {
		?>
		<fieldset><?php $checked = (isset($this->options['encryption']) && $this->options['encryption'] === '') ? 'checked' : '' ; ?>
			<label for="encryption-0"><input type="radio" name="<?php echo $this->optionName ?>[encryption]" id="encryption-0" value="" <?php echo $checked; ?>><?=__('None', 'smtp-mailing-queue')?></label><br>
			<?php $checked = (isset($this->options['encryption']) && $this->options['encryption'] === 'tls') ? 'checked' : '' ; ?>
			<label for="encryption-1"><input type="radio" name="<?php echo $this->optionName ?>[encryption]" id="encryption-1" value="tls" <?php echo $checked; ?>><?=__('TLS', 'smtp-mailing-queue')?></label><br>
			<?php $checked = (isset($this->options['encryption']) && $this->options['encryption'] === 'ssl') ? 'checked' : '' ; ?>
			<label for="encryption-2"><input type="radio" name="<?php echo $this->optionName ?>[encryption]" id="encryption-2" value="ssl" <?php echo $checked; ?>><?=__('SSL', 'smtp-mailing-queue')?></label>
		</fieldset>
		<?php
	}

	/**
	 * Prints field for SMTP host
	 */
	public function host_callback() {
		printf(
			'<input class="regular-text" type="text" name="%s[host]" id="host" value="%s">',
			$this->optionName,
			isset($this->options['host']) ? esc_attr($this->options['host']) : ''
		);
	}

	/**
	 * Prints field for SMTP port
	 */
	public function port_callback() {
		printf(
			'<input class="regular-text" type="text" name="%s[port]" id="port" value="%s">',
			$this->optionName,
			isset($this->options['port']) ? esc_attr($this->options['port']) : ''
		);
	}

	/**
	 * Prints checkbox field for selecting whether to use SMTP authentication or not
	 */
	public function use_authentication_callback() {
		printf(
			'<input type="checkbox" name="%s[use_authentication]" id="use_authentication" value="use_authentication" %s>',
			$this->optionName,
			(isset($this->options['use_authentication']) && $this->options['use_authentication'] === 'use_authentication' ) ? 'checked' : ''
		);
	}

	/**
	 * Prints field for SMTP authentication username
	 */
	public function auth_username_callback() {
		printf(
			'<input class="regular-text" type="text" name="%s[auth_username]" id="auth_username" value="%s">',
			$this->optionName,
			isset($this->options['auth_username']) ? esc_attr($this->options['auth_username']) : ''
		);
	}

	/**
	 * Prints field for SMTP authentication password
	 */
	public function auth_password_callback() {
		printf(
			'<input class="regular-text" type="password" name="%s[auth_password]" id="auth_password" value="%s">',
			$this->optionName,
			isset($this->options['auth_password']) ? esc_attr($this->smtpMailingQueue->decrypt($this->options['auth_password'])) : ''
		);
	}
}