<?php

class SMTPMailingQueue
{

	/**
	 * @var string Abs path to plugin main file.
	 */
	protected $pluginFile;
	
	/**
	 * @var Object used  to call original pluggeable methods
	 */
	private $originalPluggeable;

	/**
	 * @var string
	 */
	public $pluginVersion = '1.4.0';

	public function __construct($pluginFile = null, OriginalPluggeable $originalPluggeable)
	{
		if ($pluginFile)
			$this->pluginFile = $pluginFile;
		$this->originalPluggeable = $originalPluggeable;
		$this->init();
	}

	/**
	 * Adds hooks, actions and filters for plugin.
	 */
	protected function init()
	{
		// Actions
		add_action('phpmailer_init', [$this, 'initMailer']);

		if (isset($_GET['smqProcessQueue'])) {
			add_action('init', function () {
				$this->processQueue();
			});
		}

		add_action('init', function () {
			load_plugin_textdomain('smtp-mailing-queue', false, 'smtp-mailing-queue/languages/');
		});
		
		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);

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
	 * Enqueue JS scripts for admin
	 */
	public function enqueueAdminScripts()
	{
		wp_enqueue_script( 'smq-select', plugins_url( '/js/select.js', $this->pluginFile ), ['jquery']);
	}

	/**
	 * Adds settings page link to plugins page.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function addActionLinksToPluginPage($links)
	{
		$new_links = [sprintf(
			'<a href="%s">%s</a>',
			admin_url('options-general.php?page=smtp-mailing-queue'),
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
	public function addDonateLinkToPluginPage($links, $file)
	{
		if (strpos($file, plugin_basename($this->pluginFile)) !== false) {
			$new_links = [sprintf(
				'<a target="_blank" href="%s">%s</a>',
				'https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=KRBU2JDQUMWP4',
				__('Donate', 'smtp-mailing-queue')
			)];
			$links = array_merge($links, $new_links);
		}
		return $links;
	}

	/**
	 * Gets called on plugin activation.
	 */
	public function onActivation()
	{
		$this->refreshWpCron();
		$this->setOptionsDefault();
	}

	/**
	 * Sets default options for advanced settings.
	 * No default options for normal settings needed.
	 */
	protected function setOptionsDefault()
	{
		// advanced settings
		$advancedDefault = [
			'queue_limit' => 10,
			'wpcron_interval' => 300,
			'dont_use_wpcron' => false,
			'process_key' => wp_generate_password(16, false, false),
			'min_recipients' => 1,
			'max_retry' => 10
		];

		$advanced = get_option('smtp_mailing_queue_advanced');
		$advanced = is_array($advanced) ? $advanced : array();
		update_option('smtp_mailing_queue_advanced', array_merge($advancedDefault, $advanced));
	}

	/**
	 * (Re)sets wp_cron, e.g. on activation and interval update.
	 */
	public function refreshWpCron()
	{
		if (wp_next_scheduled('smq_start_queue'))
			wp_clear_scheduled_hook('smq_start_queue');
		wp_schedule_event(time(), 'smq', 'smq_start_queue');
	}

	/**
	 * Gets called on plugin deactivation.
	 */
	public function onDeactivation()
	{
		// remove plugin from wp_cron
		wp_clear_scheduled_hook('smq_start_queue');
	}

	/**
	 * Calls URL for processing the mailing queue.
	 */
	public function callProcessQueue()
	{
		wp_remote_get($this->getCronLink());
	}

	/**
	 * Generates link for starting processing queue.
	 *
	 * @return string
	 */
	public function getCronLink()
	{
		$wpUrl = get_bloginfo("wpurl");
		if (function_exists('idn_to_ascii')) {
			$parts = parse_url($wpUrl);
			$host = $parts['host'];
			if (preg_match('/[\x80-\xFF]/', $host)) {
				$puny = idn_to_ascii($host);
				if ($puny !== false) {
					$parts['host'] = $puny;
					$wpUrl = $this->composeUrl($parts);
				}
			}
		}
		$advanced = get_option('smtp_mailing_queue_advanced');
		$processKey = isset($advanced['process_key']) ? $advanced['process_key'] : '';

		return $wpUrl . '?smqProcessQueue&key=' . $processKey;
	}

	/**
	 * Build wordpress blog url from components acquired via parse_url
	 *
	 * @param $parsed_url
	 *
	 * @return string
	 */
	function composeUrl($parsed_url)
	{
		$scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
		return "$scheme$host$port$path";
	}

	/**
	 * Adds custom interval based on interval settings to wp_cron.
	 *
	 * @param array $schedules
	 *
	 * @return array
	 */
	public function addWpCronInterval($schedules)
	{
		$advanced = get_option('smtp_mailing_queue_advanced');
		$interval = isset($advanced['wpcron_interval']) ? $advanced['wpcron_interval']: null;
		if (isset($interval)) {
			$schedules['smq'] = [
				'interval' => $interval,
				'display' => __('Interval for sending mail', 'smtp-mailing-queue')
			];
		}
		return $schedules;
	}

	/**
	 * Writes mail data to json file or sends mail directly.
	 *
	 * @param string|array $to
	 * @param string $subject
	 * @param string $message
	 * @param array|string $headers
	 * @param array $attachments
	 *
	 * @return bool
	 */
	public function wp_mail($to, $subject, $message, $headers = '', $attachments = array())
	{
		$advancedOptions = get_option('smtp_mailing_queue_advanced');
		$minRecipients = isset($advancedOptions['min_recipients']) ? $advancedOptions['min_recipients'] : 1;

		if (is_array($to))
			$to = implode(',', $to);

		if (count(explode(',', $to)) >= $minRecipients)
			return self::storeMail($to, $subject, $message, $headers, $attachments);
		else {
			return $this->originalPluggeable->wp_mail($to, $subject, $message, $headers, $attachments);
		}
	}

	/**
	 * Writes mail data to json file.
	 *
	 * @param string $to
	 * @param string $subject
	 * @param string $message
	 * @param array|string $headers
	 * @param array $attachments
	 * @param string $time
	 *
	 * @return bool
	 */
	public static function storeMail($to, $subject, $message, $headers = '', $attachments = array(), $time = null)
	{
		require_once __DIR__ . '/PHPMailer/class-phpmailer.php';

		// Store attachments
		require_once('SMTPMailingQueueAttachments.php');
		$attachments = SMTPMailingQueueAttachments::storeAttachments($attachments);

		$time = $time ?: time();
		$failures = 0;
		$data = compact('to', 'subject', 'message', 'headers', 'attachments', 'time', 'failures');

		$fileName = self::getUploadDir() . uniqid('', true) . '.json';

		return self::writeDataToFile($fileName, $data);
	}

	/**
	 * Write the mail to filesystem
	 *
	 * @param string $filepath
	 * @param array $data mail data
	 *
	 * @return boolean true if write success, false if not
	 */
	public static function writeDataToFile($filepath, $data)
	{
		$handle = @fopen($filepath, "w");
		if (!$handle)
			return false;
		fwrite($handle, json_encode($data));
		fclose($handle);

		return true;
	}

	/**
	 * Creates upload dir if it not existing.
	 * Adds htaccess protection to upload dir.
	 *
	 * @param string $type
	 *
	 * @return string upload dir
	 */
	public static function getUploadDir($type = false)
	{
		$dir = wp_upload_dir()['basedir'] . '/smtp-mailing-queue/';
		$created = wp_mkdir_p($dir);
		if ($created) {
			$handle = @fopen($dir . '.htaccess', "w");
			fwrite($handle, 'DENY FROM ALL');
			fclose($handle);
		}

		if ('invalid' == $type) {
			$dir = $dir . 'invalid/';
			wp_mkdir_p($dir);
		}
		if ('attachments' == $type) {
			$dir = $dir . 'attachments/';
			wp_mkdir_p($dir);
		}

		return $dir;
	}

	/**
	 * Loads mail data from json files.
	 *
	 * @param bool $ignoreLimit
	 * @param bool $invalid Load invalid emails
	 *
	 * @return array Mail data
	 */
	public function loadDataFromFiles($ignoreLimit = false, $invalid = false)
	{
		$advancedOptions = get_option('smtp_mailing_queue_advanced');
		$emails = [];
		$i = 0;

		if ($invalid) {
			$uploadType = 'invalid';
		} else {
			$uploadType = '';
		}

		foreach (glob(self::getUploadDir($uploadType) . '*.json') as $filename) {
			$emails[$filename] = $this->loadDataFromFile($filename);
			$i++;
			if (!$ignoreLimit && !empty($advancedOptions['queue_limit']) && $i >= $advancedOptions['queue_limit'])
				break;
		}
		return $emails;
	}
	
	/**
	 * Load one mail from file
	 * @param string $file
	 */
	 public function loadDataFromFile($file)
	 {
		 return json_decode(file_get_contents($file), true);
	 }

	/**
	 * Processes mailing queue.
	 *
	 * @param bool $checkKey
	 */
	public function processQueue($checkKey = true)
	{
		$advancedOptions = get_option('smtp_mailing_queue_advanced');
		$processKeyOption = isset($advancedOptions['process_key']) ? $advancedOptions['process_key'] : null;
		if ($checkKey && (!isset($_GET['key']) || !isset($processKeyOption) || $processKeyOption != $_GET['key']))
			return;

		$max_retry = isset($advancedOptions['max_retry']) ? $advancedOptions['max_retry'] : 10;
		$mails = $this->loadDataFromFiles();

		foreach ($mails as $file => $data) {
			if ($this->sendMail($data)) {
				$this->deleteMail($file);
			} else {
				// Increment the failures counter
				$data['failures']++;
				self::writeDataToFile($file, $data);

				// If failures reach max retry counter, move mail to invalid
				if ($data['failures'] > $max_retry) {
					rename($file, self::getUploadDir('invalid') . substr($file, strrpos($file, "/") + 1));
				}
			}
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
	public function sendMail($data)
	{
		return wp_mail($data['to'], $data['subject'], $data['message'], $data['headers'], $data['attachments']);
	}

	/**
	 * Deletes email and attachments
	 *
	 * @param string $file Absolute path to file
	 */
	public function deleteMail($file)
	{
		if (file_exists($file))
		{
			$email = $this->loadDataFromFile($file);
			$this->deleteFile($file);
			if (array_key_exists('attachments', $email)) {
				require_once('SMTPMailingQueueAttachments.php');
				SMTPMailingQueueAttachments::removeAttachments($email['attachments']);
			}
		}
	}


	/**
	 *
	 */
	public function retryMail($file) {
		if (file_exists($file))
		{
			$email = $this->loadDataFromFile($file);
			$email['failures'] = 0;
			self::writeDataToFile($file, $email);
			rename($file, self::getUploadDir() . substr($file, strrpos($file, "/") + 1));
		}
	}

	/**
	 * Deletes file from uploads folder
	 *
	 * @param string $file Absolute path to file
	 */
	public function deleteFile($file)
	{
		unlink($file);
	}

	/**
	 * Sets WordPress phpmailer to SMTP and sets all options.
	 *
	 * @param PHPMailer $phpmailer
	 */
	public function initMailer($phpmailer)
	{
		$options = get_option('smtp_mailing_queue_options');

		if (!$options)
			return;

		if (empty($options['host']))
			return;

		// Set mailer to SMTP
		$phpmailer->isSMTP();

//		$phpmailer->SMTPDebug = 1;

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
		if (isset($options['use_authentication'])) {
			$phpmailer->SMTPAuth = TRUE;
			$phpmailer->Username = $options['auth_username'];
			$phpmailer->Password = $this->decrypt($options['auth_password']);
		}
	}

	/**
	 * Encrypts a string (e.g. SMTP password) with openssl_encrypt if installed.
	 * Fallback to base64 for "obfuscation" (well, not really).
	 *
	 * @see http://wordpress.stackexchange.com/a/25792/45882
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function encrypt($str)
	{
		if (!function_exists('openssl_random_pseudo_bytes') || !function_exists('openssl_cipher_iv_length') || !function_exists('openssl_encrypt'))
			return base64_encode($str);

		$h_key = hash('sha256', AUTH_SALT, TRUE);
		$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
		$encrypted = openssl_encrypt($str, 'aes-256-cbc', $h_key, 0, $iv);
		return base64_encode($encrypted . '::' . $iv);
	}

	/**
	 * Decrypts a string (e.g. SMTP password) with openssl_decrypt, if installed.
	 * Fallback to base64 for "obfuscation" (well, not really).
	 *
	 * @see http://wordpress.stackexchange.com/a/25792/45882
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function decrypt($str)
	{
		if (!function_exists('openssl_decrypt'))
			return base64_decode($str);

		$h_key = hash('sha256', AUTH_SALT, TRUE);
		list($encrypted_data, $iv) = explode('::', base64_decode($str), 2);
		return openssl_decrypt($encrypted_data, 'aes-256-cbc', $h_key, 0, $iv);
	}

	/**
	 * @Deprecated
	 *
	 * Decrypts a string (e.g. SMTP password) with mcrypt, if installed.
	 * Fallback to base64 for "obfuscation" (well, not really).
	 *
	 * @see http://wordpress.stackexchange.com/a/25792/45882
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function decrypt_1_1_0($str)
	{
		if (!function_exists('mcrypt_get_iv_size') || !function_exists('mcrypt_create_iv') || !function_exists('mcrypt_encrypt'))
			return base64_decode($str);
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$h_key = hash('sha256', AUTH_SALT, TRUE);
		return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $h_key, base64_decode($str), MCRYPT_MODE_ECB, $iv));
	}
}