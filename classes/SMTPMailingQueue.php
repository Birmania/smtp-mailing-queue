<?php

require_once('SMTPMailingQueueEnums.php');

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
	public $pluginVersion = '2.0.1';

	public function __construct($pluginFile, OriginalPluggeable $originalPluggeable)
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
		
		if (isset($_GET['smqDismissNotice'])) {
			delete_option('smtp_mailing_queue_notice');
		}

		add_action('init', function () {
			load_plugin_textdomain('smtp-mailing-queue', false, 'smtp-mailing-queue/languages/');
		});

		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);

		add_action('smq_start_queue', [$this, 'callProcessQueue']);
		add_action('smq_sanity_checks', [$this, 'doSanityChecks']);

		add_action( 'admin_notices', [$this, 'displayAdminNotice'] );

		// Hooks
		register_activation_hook($this->pluginFile, [$this, 'onActivation']);
		register_deactivation_hook($this->pluginFile, [$this, 'onDeactivation']);
		
		// Always reschedule sanity check as some users experimented random missing wp cron event
		$this->scheduleSanityChecks();

		// Filter
		add_filter('wp_mail_from', [$this, 'initFrom']);
		add_filter('wp_mail_from_name', [$this, 'initFromName']);
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
		$this->setOptionsDefault();
		$this->refreshWpCron();
	}

	/**
	 * Gets called on plugin deactivation.
	 */
	public function onDeactivation()
	{
		// remove plugin from wp_cron
		wp_clear_scheduled_hook('smq_sanity_checks');
		wp_clear_scheduled_hook('smq_start_queue');
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
			'max_retry' => 10,
			'sent_storage_size' => 0
		];

		$advanced = get_option('smtp_mailing_queue_advanced');
		$advanced = is_array($advanced) ? $advanced : array();
		update_option('smtp_mailing_queue_advanced', array_merge($advancedDefault, $advanced));
	}

	public function scheduleSanityChecks()
	{
		if (!wp_next_scheduled('smq_sanity_checks'))
			wp_schedule_event(time(), 'hourly', 'smq_sanity_checks');
	}
	
	/**
	 * (Re)sets wp_cron, e.g. on interval update.
	 */
	public function resetWpCron()
	{
		// Clear existing queue processing scheduled event and trigger refresh that will resolve the missing event
		wp_clear_scheduled_hook('smq_start_queue');
		$this->refreshWpCron();
	}
	
	/**
	 * Refresh wpCron to ensure that queued hook is always here.
	 
	 */
	public function refreshWpCron()
	{
		$advanced = get_option('smtp_mailing_queue_advanced');
		$advanced = is_array($advanced) ? $advanced : array();
		
		$dontUseWpcron = isset($advanced['dont_use_wpcron']) ? $advanced['dont_use_wpcron']: false;
		
		if ($dontUseWpcron) {
			wp_clear_scheduled_hook('smq_start_queue');
		} else {
			if (!wp_next_scheduled('smq_start_queue'))
				wp_schedule_event(time(), 'smq', 'smq_start_queue');
		}
	}

	/**
	 * Function to check plugin sanity
	 */
	public function doSanityChecks()
	{
		$this->refreshWpCron();
	}

	/**
	 * Calls URL for processing the mailing queue.
	 */
	public function callProcessQueue()
	{
		$startTime = microtime(true);
		$response = wp_remote_get($this->getCronLink(), 
			array(
				'timeout' => $this->getQueueProcessingTimeout(),
			)
		);
		$endTime = microtime(true);
		$executionTime = ($endTime - $startTime);
		
		$currentMaxProcessingDelay = get_transient('smtp_mailing_queue_max_processing_delay');
		if (!isset($currentMaxProcessingDelay) || $currentMaxProcessingDelay < $executionTime) {
			set_transient('smtp_mailing_queue_max_processing_delay', $executionTime, 60*60*24);
		}

		if (is_wp_error($response)) {
			$notice = [
				'class' => 'notice-warning',
				'message' => sprintf(__("Error encountered while processing queue : '%s'", "smtp-mailing-queue"), $response->get_error_message()),
			];
			set_transient('smtp_mailing_queue_processing_notice', $notice, 60*60*24);
		}
	}

	public function displayAdminNotice()
	{
		$notice = get_option('smtp_mailing_queue_notice');
		if ($notice) {
			printf('<div class="notice %1$s"><p>%2$s</p><a href="?smqDismissNotice">Dismiss</a></div>', esc_attr($notice['class']), esc_html($notice['message']));
		}
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
		return $wpUrl . '?smqProcessQueue&key=' . $processKey . '&time=' . time();
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
	 * Run $callback with the $handler disabled for the $hook action/filter
	 * Thanks @rodruiz (https://gist.github.com/westonruter/6647252?permalink_comment_id=2668616#gistcomment-2668616)
	 * @param string $hooks filter names
	 * @param callable $callback function execited while filter disabled 
	 * @return mixed value returned by $callback
	 */
	private static function withoutFilters( $hooks, $callback ) {
		global $wp_filter;
		
		$wp_hooks = array();
		foreach ( (array) $hooks as $hook ) {
			// Remove and cache the filter
			if ( isset( $wp_filter[ $hook ] ) && $wp_filter[ $hook ] instanceof WP_Hook ) {
				$wp_hooks[$hook] = $wp_filter[ $hook ];
				unset( $wp_filter[ $hook ] );
			}
		}
		
		$retval = call_user_func( $callback );
		
		foreach ( (array) $wp_hooks as $hook => $wp_hook ) {
			// Add back the filter
			if ( $wp_hook instanceof WP_Hook  ) {
				$wp_filter[ $hook ] = $wp_hook;
			}
		}
		return $retval;
	}
	
	/**
	 * Some plugins are vicious in the way they set some headers through temporary wp_filters.
	 * Due to this, we should apply filters on wp_mail call and inhibit them on real wp_mail call.
	 * 
	 * Note : This method is heavily inspired from original pluggeable wp_mail.
	 *
	 * @return array
	 */
	private static function resolveHeaders( $headers = '' ) {
		// Headers.
		if ( empty( $headers ) ) {
			$headers = array();
		} else {
			if ( ! is_array( $headers ) ) {
				// Explode the headers out, so this function can take
				// both string headers and an array of headers.
				$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
			} else {
				$tempheaders = $headers;
			}
			$headers = array();

			// If it's actually got contents.
			if ( ! empty( $tempheaders ) ) {
				// Iterate through the raw headers.
				foreach ( (array) $tempheaders as $header ) {
					if ( strpos( $header, ':' ) === false ) {
						if ( false !== stripos( $header, 'boundary=' ) ) {
							$parts    = preg_split( '/boundary=/i', trim( $header ) );
							$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
						}
						continue;
					}
					// Explode them out.
					list( $name, $content ) = explode( ':', trim( $header ), 2 );

					// Cleanup crew.
					$name    = trim( $name );
					$content = trim( $content );

					switch ( strtolower( $name ) ) {
						// Mainly for legacy -- process a "From:" header if it's there.
						case 'from':
							$bracket_pos = strpos( $content, '<' );
							if ( false !== $bracket_pos ) {
								// Text before the bracketed email is the "From" name.
								if ( $bracket_pos > 0 ) {
									$from_name = substr( $content, 0, $bracket_pos - 1 );
									$from_name = str_replace( '"', '', $from_name );
									$from_name = trim( $from_name );
								}

								$from_email = substr( $content, $bracket_pos + 1 );
								$from_email = str_replace( '>', '', $from_email );
								$from_email = trim( $from_email );

								// Avoid setting an empty $from_email.
							} elseif ( '' !== trim( $content ) ) {
								$from_email = trim( $content );
							}
							break;
						case 'content-type':
							if ( strpos( $content, ';' ) !== false ) {
								list( $type, $charset_content ) = explode( ';', $content );
								$content_type                   = trim( $type );
								if ( false !== stripos( $charset_content, 'charset=' ) ) {
									$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset_content ) );
								} elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
									$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset_content ) );
									$charset  = '';
								}

								// Avoid setting an empty $content_type.
							} elseif ( '' !== trim( $content ) ) {
								$content_type = trim( $content );
							}
							break;
						default:
							// Add it to our grand headers array.
							$headers[] = $header;
							break;
					}
				}
			}
		}

		// Set "From" name and email.

		// If we don't have a name from the input headers.
		if ( ! isset( $from_name ) ) {
			$from_name = 'WordPress';
		}

		/*
		 * If we don't have an email from the input headers, default to wordpress@$sitename
		 * Some hosts will block outgoing mail from this address if it doesn't exist,
		 * but there's no easy alternative. Defaulting to admin_email might appear to be
		 * another option, but some hosts may refuse to relay mail from an unknown domain.
		 * See https://core.trac.wordpress.org/ticket/5007.
		 */
		if ( ! isset( $from_email ) ) {
			// Get the site domain and get rid of www.
			$sitename = wp_parse_url( network_home_url(), PHP_URL_HOST );
			if ( 'www.' === substr( $sitename, 0, 4 ) ) {
				$sitename = substr( $sitename, 4 );
			}

			$from_email = 'wordpress@' . $sitename;
		}

		/**
		 * Filters the email address to send from.
		 *
		 * @since 2.2.0
		 *
		 * @param string $from_email Email address to send from.
		 */
		$from_email = apply_filters( 'wp_mail_from', $from_email );

		/**
		 * Filters the name to associate with the "from" email address.
		 *
		 * @since 2.3.0
		 *
		 * @param string $from_name Name associated with the "from" email address.
		 */
		$from_name = apply_filters( 'wp_mail_from_name', $from_name );

		// Set Content-Type and charset.

		// If we don't have a content-type from the input headers.
		if ( ! isset( $content_type ) ) {
			$content_type = 'text/plain';
		}

		$content_type = apply_filters( 'wp_mail_content_type', $content_type );

		// If we don't have a charset from the input headers.
		if ( ! isset( $charset ) ) {
			$charset = get_bloginfo( 'charset' );
		}

		$charset = apply_filters( 'wp_mail_charset', $charset );
		
		// Rebuild headers
		if ( isset( $content_type ) ) {
			$concrete_content_type = sprintf("%s; ", $content_type);
			
			if ( isset( $charset ) ) {
				$concrete_content_type .= sprintf('%s; ', $charset);
			}
			
			if ( isset($boundary) ) {
				$concrete_content_type .= sprintf('boundary="%s; "', $boundary);
			}
			
			$headers[] = sprintf('Content-Type: %s', $concrete_content_type);
		}

		if (isset($from_name)) {
			$concrete_from = sprintf("%s <%s>", $from_name, $from_email);
		} else {
			$concrete_from = sprintf("%s", $from_email);
		}
		$headers[] = sprintf('From: %s', $concrete_from);

		return $headers;
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

		// Do wp_mail filters here as some plugins set filters only during wp_mail send code execution
		$atts = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );
		$pre_wp_mail = apply_filters( 'pre_wp_mail', null, $atts );

		if ( null !== $pre_wp_mail ) {
			return $pre_wp_mail;
		}

		$to = $atts['to'];
		$subject = $atts['subject'];
		$message = $atts['message'];
		$headers = self::resolveHeaders($atts['headers']);
		$attachments = $atts['attachments'];

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
	public static function getUploadDir($type = UploadType::Queued)
	{
		$dir = wp_upload_dir()['basedir'] . '/smtp-mailing-queue/';
		$created = wp_mkdir_p($dir);
		if ($created) {
			$handle = @fopen($dir . '.htaccess', "w");
			fwrite($handle, 'DENY FROM ALL');
			fclose($handle);
		}

		if (UploadType::Sent == $type) {
			$dir = $dir . 'sent/';
			wp_mkdir_p($dir);
		}
		
		if (UploadType::Invalid == $type) {
			$dir = $dir . 'invalid/';
			wp_mkdir_p($dir);
		}
		if (UploadType::Attachment == $type) {
			$dir = $dir . 'attachments/';
			wp_mkdir_p($dir);
		}

		// Default $dir is UploadType::Queued
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
	public function loadDataFromFiles($ignoreLimit = false, $uploadType = UploadType::Queued)
	{
		$advancedOptions = get_option('smtp_mailing_queue_advanced');
		$emails = [];
		$i = 0;

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

		$maxRetry = isset($advancedOptions['max_retry']) ? $advancedOptions['max_retry'] : 10;
		$mails = $this->loadDataFromFiles();

		foreach ($mails as $file => $data) {
			if ($this->sendMail($data)) {
				// Store current date as sent date
				$data['sent_time'] = time();
				self::writeDataToFile($file, $data);
				
				rename($file, self::getUploadDir(UploadType::Sent) . substr($file, strrpos($file, "/") + 1));
				$this->purgeSentMails();
			} else {
				// Increment the failures counter
				$data['failures']++;
				self::writeDataToFile($file, $data);

				// If failures reach max retry counter, move mail to invalid
				if ($data['failures'] > $maxRetry) {
					rename($file, self::getUploadDir(UploadType::Invalid) . substr($file, strrpos($file, "/") + 1));
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
		// Filters have already been applied before storing the file so we should disable all them
		return $this->withoutFilters(
			['wp_mail', 'pre_wp_mail', 'wp_mail_from', 'wp_mail_from_name', 'wp_mail_content_type', 'wp_mail_charset'],
			function () use ($data) {
				return wp_mail($data['to'], $data['subject'], $data['message'], $data['headers'], $data['attachments']);
			}
		);
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
	
	public function purgeSentMails() {
		$advancedOptions = get_option('smtp_mailing_queue_advanced');
		$sentStorageSize = isset($advancedOptions['sent_storage_size']) ? $advancedOptions['sent_storage_size'] : 0;
		
		$mails = $this->loadDataFromFiles(false, UploadType::Sent);
		
		if ($sentStorageSize) {
			$mailsToPurge = array_slice($mails, 0, -$sentStorageSize);
		} else {
			$mailsToPurge = $mails;
		}
		
		foreach ($mailsToPurge as $file => $data) {
			$this->deleteMail($file);
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
	 * Init from mail using filter as phpmailer_init occured after wp checks
	 */
	public function initFrom($from) {
		$options = get_option('smtp_mailing_queue_options');
		
		if (!$options || empty($options['from_email']))
			return $from;

		return $options['from_email'];
	}
	
	/**
	 * Init from name using filter as phpmailer_init occured after wp checks
	 */
	public function initFromName($fromName) {
		$options = get_option('smtp_mailing_queue_options');
		
		if (!$options || empty($options['from_name']))
			return $fromName;

		return $options['from_name'];
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

		// Set host
		if (!empty($options['host'])) {
			// Set mailer to SMTP
			$phpmailer->isSMTP();
			
			//		$phpmailer->SMTPDebug = 1;
			
			// Set encryption type
			$phpmailer->SMTPSecure = $options['encryption'];
			
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
	
	/**
	 * Time available before processing mails timeout, in seconds.
	*/
	public function getQueueProcessingTimeout() {
		return WP_CRON_LOCK_TIMEOUT / 2;
	}
}