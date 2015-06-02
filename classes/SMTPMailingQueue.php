<?php
class SMTPMailingQueue {

	/**
	 * @var string Abs path to plugin main file.
	 */
	protected $pluginFile;

	public function __construct($pluginFile) {
		$this->pluginFile = $pluginFile;
		$this->init();
	}

	/**
	 * Adds hooks, actions and filters for plugin.
	 */
	protected function init() {
		// Actions
		if(isset($_GET['smqProcessQueue'])) {
			add_action('phpmailer_init', [$this, 'initMailer']);
			add_action('init', [$this, 'processQueue']);
		}
		add_action('smq_start_queue', [$this, 'callProcessQueue']);

		// Hooks
		register_activation_hook($this->pluginFile, [$this, 'onActivation']);
		register_deactivation_hook($this->pluginFile, [$this, 'onDeactivation']);

		// Filter
		add_filter('plugin_action_links_' . plugin_basename($this->pluginFile), [$this, 'addActionLinksToPluginPage']);
		add_filter('plugin_row_meta', [$this, 'addDonateLinkToPluginPage'], 10, 2);
		add_filter('cron_schedules', [$this, 'addWpCronInterval']);
	}

	/**
	 * Adds settings page link to plugins page.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function addActionLinksToPluginPage($links) {
		$new_links = [sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=smtp-mailing-queue' ),
			'Settings'
		)];
		return array_merge($new_links, $links);
	}

	/**
	 * Adds donate and github link to plugins page
	 *
	 * @param array $links
	 * @param string $file
	 *
	 * @return array
	 */
	public function addDonateLinkToPluginPage($links, $file) {
		if(strpos($file, plugin_basename($this->pluginFile)) !== false) {
			$new_links = [sprintf(
				'<a target="_blank" href="%s">%s</a>',
				'https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=KRBU2JDQUMWP4',
				'Donate'
			)];
			$links = array_merge($links, $new_links);
		}
		return $links;
	}

	/**
	 * Gets called on plugin activation.
	 */
	public function onActivation() {
		$this->refreshWpCron();
		$this->setOptionsDefault();
	}

	/**
	 * Sets default options for advanced settings.
	 * No default options for normal settings needed.
	 */
	protected function setOptionsDefault() {
		// advanced settings
		$advancedDefault = [
			'queue_limit'       => 10,
			'wpcron_interval'   => 300,
			'dont_use_wpcron'   => false,
			'process_key'       => wp_generate_password(16, false, false)
		];

		$advanced = get_option('smtp_mailing_queue_advanced');
		$advanced = is_array($advanced) ? $advanced : array();
		update_option('smtp_mailing_queue_advanced', array_merge($advancedDefault, $advanced));
	}

	/**
	 * (Re)sets wp_cron, e.g. on activation and interval update.
	 */
	public function refreshWpCron() {
		if(wp_next_scheduled('smq_start_queue'))
			wp_clear_scheduled_hook( 'smq_start_queue' );
		wp_schedule_event( time(), 'smq', 'smq_start_queue' );
	}

	/**
	 * Gets called on plugin deactivation.
	 */
	public function onDeactivation() {
		// remove plugin from wp_cron
		wp_clear_scheduled_hook( 'smq_start_queue' );
	}

	/**
	 * Calls URL for processing the mailing queue.
	 */
	public function callProcessQueue() {
		wp_remote_get($this->getCronLink());
	}

	/**
	 * Generates link for starting processing queue.
	 *
	 * @return string
	 */
	public function getCronLink() {
		$key = get_option('smtp_mailing_queue_advanced')['process_key'];
		$wpUrl = get_bloginfo("wpurl");
		return $wpUrl . '?smqProcessQueue&key=' . $key;
	}

	/**
	 * Adds custom interval based on interval settings to wp_cron.
	 *
	 * @param array $schedules
	 *
	 * @return array
	 */
	public function addWpCronInterval($schedules) {
		$interval = get_option('smtp_mailing_queue_advanced')['wpcron_interval'];
		$schedules['smq'] = [
			'interval' => $interval,
			'display' => 'Interval for sending mail'
		];
		return $schedules;
	}

	/**
	 * Writes mail data to json file.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $message
	 * @param array|string $headers
	 * @param array $attachments
	 *
	 * @return bool
	 */
	public function storeMail($to, $subject, $message, $headers = '', $attachments = array()) {
		$time = time();
		$data = compact('to', 'subject', 'message', 'headers', 'attachments', 'time');
		$fileName = $this->getUploadDir() . microtime(true) . '.json';
		$handle = @fopen($fileName, "w");
		if(!$handle)
			return false;
		fwrite($handle, json_encode($data));
		fclose($handle);
		return true;
	}

	/**
	 * Creates upload dir if it not existing.
	 * Adds .htaccess protection to upload dir.
	 *
	 * @return string upload dir
	 */
	protected function getUploadDir() {
		$dir = wp_upload_dir()['basedir'] . '/smtp-mailing-queue/';
		$created = wp_mkdir_p($dir);
		if($created) {
			$handle = @fopen($dir . '.htaccess', "w");
			fwrite($handle, 'DENY FROM ALL');
			fclose($handle);
		}
		return $dir;
	}

	/**
	 * Loads mail data from json files.
	 *
	 * @param bool $ignoreLimit
	 *
	 * @return array Mail data
	 */
	public function loadDataFromFiles($ignoreLimit = false) {
		$advancedOptions = get_option('smtp_mailing_queue_advanced');
		$emails = [];
		$i = 0;
		foreach (glob($this->getUploadDir() . '*.json') as $filename) {
			$emails[ $filename ] = json_decode( file_get_contents( $filename ), true );
			$i++;
			if(!$ignoreLimit && !empty($advancedOptions['queue_limit']) && $i >= $advancedOptions['queue_limit'])
				break;
		}
		return $emails;
	}

	/**
	 * Processes mailing queue.
	 *
	 * @param bool $checkKey
	 */
	public function processQueue($checkKey = true) {
		$advancedOptions = get_option('smtp_mailing_queue_advanced');
		if($checkKey && (!isset($_GET['key']) || $advancedOptions['process_key'] != $_GET['key']))
			return;

		$mails = $this->loadDataFromFiles();
		foreach($mails as $file => $data) {
			if($this->sendMail($data))
				$this->deleteFile($file);
			else
				die('email not sent');
			// @todo: else log error or so
		}
		exit;
	}

	/**
	 * (Really) send mails (if $_GET['smqProcessQueue'] is set).
	 *
	 * @param array $data mail data
	 *
	 * @return bool Success
	 */
	public function sendMail($data) {
		return wp_mail($data['to'], $data['subject'], $data['message'], $data['headers'], $data['attachments']);
	}

	/**
	 * Deletes file from uploads folder
	 *
	 * @param string $file Absolute path to file
	 */
	protected function deleteFile($file) {
		unlink($file);
	}

	/**
	 * Sets WordPress phpmailer to SMTP and sets all options.
	 *
	 * @param \PHPMailer $phpmailer
	 */
	public function initMailer($phpmailer) {
		$options = get_option('smtp_mailing_queue_options');

		if(!$options)
			return;

		if(empty($options['host']))
			return;

		// Set mailer to SMTP
		$phpmailer->isSMTP();

		// Set sender info
		$phpmailer->From = $options['from_email'];
		$phpmailer->FromName = $options['from_name'];

		// Set encryption type
		$phpmailer->SMTPSecure = $options['encryption'];

		// Set host
		$phpmailer->Host = $options['host'];
		$phpmailer->Port = $options['port'] ? $options['port'] : 25;

		// todo: fix me
		// temporary hard coded fix. should be a setting (and should be logged in case of timeout)
		$phpmailer->Timeout = 30;

		// Set authentication data
		if(isset($options['use_authentication'])) {
			$phpmailer->SMTPAuth = TRUE;
			$phpmailer->Username = $options['auth_username'];
			$phpmailer->Password = $this->decrypt($options['auth_password']);
		}
	}

	/**
	 * Encrypts a string (e.g. SMTP password) with mcrypt if installed.
	 * Fallback to base64 for "obfuscation" (well, not really).
	 *
	 * @see http://wordpress.stackexchange.com/a/25792/45882
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function encrypt($str){
		if(!function_exists('mcrypt_get_iv_size') || !function_exists('mcrypt_create_iv') || !function_exists('mcrypt_encrypt'))
			return base64_encode($str);
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$h_key = hash('sha256', AUTH_SALT, TRUE);
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $h_key, $str, MCRYPT_MODE_ECB, $iv));
	}

	/**
	 * Decrypts a string (e.g. SMTP password) with mcrypt, if installed.
	 * Fallback to base64 for "obfuscation" (well, not really).
	 *
	 * @see http://wordpress.stackexchange.com/a/25792/45882
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function decrypt($str){
		if(!function_exists('mcrypt_get_iv_size') || !function_exists('mcrypt_create_iv') || !function_exists('mcrypt_encrypt'))
			return base64_decode($str);
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$h_key = hash('sha256', AUTH_SALT, TRUE);
		return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $h_key, base64_decode($str), MCRYPT_MODE_ECB, $iv));
	}
}