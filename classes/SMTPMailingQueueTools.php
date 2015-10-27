<?php
require_once('SMTPMailingQueueAdmin.php');

class SMTPMailingQueueTools extends SMTPMailingQueueAdmin {

	/**
	 * @var string Slug of this tab
	 */
	private $tabName = 'tools';

	/**
	 * @var string Slug of active tool
	 */
	private $activeTool;

	/**
	 * @var bool Prefill form fields after error
	 */
	private $prefill = false;

	public function __construct() {
		parent::__construct();
		$this->init();
	}

	/**
	 * Sets active tool slug.
	 * Loads content for active tool if this tab is active
	 *
	 */
	private function init() {
		if(!is_admin() || $this->activeTab !== $this->tabName)
			return;
		$this->activeTool = isset($_GET['tool']) ? $_GET['tool'] : 'testmail';

		if(isset($_POST['smq-test_mail']))
			add_action('init', [$this, 'sendTestMail']);
		if(isset($_POST['smq-process_queue']))
			add_action('init', [$this, 'startProcessQueue']);
		add_action( 'admin_menu', [$this, 'add_plugin_page']);
	}

	/**
	 * Prints tab header
	 */
	public function loadPageContent() {
		?>
		<ul>
			<li><strong>Test Mail</strong>: Test your email settings by sendig directly or adding test mail into queue.</li>
			<li><strong>Process Queue</strong>: Start queue processing manually. Your set queue limit will still be obeyed, if set.</li>
			<li><strong>List Queue</strong>: Show all mails in mailing queue.</li>
		</ul>
		<h3 class="nav-tab-wrapper">
			<a href="?page=smtp-mailing-queue&tab=tools&tool=testmail" class="nav-tab <?php echo $this->activeTool == 'testmail' ? 'nav-tab-active' : '' ?>">Test Mail</a>
			<a href="?page=smtp-mailing-queue&tab=tools&tool=processQueue" class="nav-tab <?php echo $this->activeTool == 'processQueue' ? 'nav-tab-active' : '' ?>">Process Queue</a>
			<a href="?page=smtp-mailing-queue&tab=tools&tool=listQueue" class="nav-tab <?php echo $this->activeTool == 'listQueue' ? 'nav-tab-active' : '' ?>">List Queue</a>
		</h3>
		<?php

		switch($this->activeTool) {
			case 'testmail':
				$this->createTestmailForm();
				break;
			case 'processQueue':
				$this->createProcessQueueForm();
				break;
			case 'listQueue':
				$this->createListQueue();
				break;
		}
	}

	/**
	 * Prints testmail form
	 */
	private function createTestmailForm() {
		?>
		<form method="post" action="">
			<table class="form-table">
				<tr valign="top">
					<th scope="row">To email address</th>
					<td>
						<input type="text" name="smq-test_mail[to]" class="regular-text code"
						       value="<?php echo ($this->prefill && isset($_POST['smq-test_mail']['to']) ? $_POST['smq-test_mail']['to'] : '' ) ?>"/>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Cc email addresses</th>
					<td>
						<input type="text" name="smq-test_mail[cc]" class="regular-text code"
						       value="<?php echo ($this->prefill && isset($_POST['smq-test_mail']['cc']) ? $_POST['smq-test_mail']['cc'] : '' ) ?>"/>
						<p class="description">Multiple addresses can be added separated by comma.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Bcc email addresses</th>
					<td>
						<input type="text" name="smq-test_mail[bcc]" class="regular-text code"
						       value="<?php echo ($this->prefill && isset($_POST['smq-test_mail']['bcc']) ? $_POST['smq-test_mail']['bcc'] : '' ) ?>"/>
						<p class="description">Multiple addresses can be added separated by comma.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Subject</th>
					<td>
						<input type="text" name="smq-test_mail[subject]" class="regular-text code"
						       value="<?php echo ($this->prefill && isset($_POST['smq-test_mail']['subject']) ? $_POST['smq-test_mail']['subject'] : '' ) ?>"/>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Message</th>
					<td>
						<textarea name="smq-test_mail[message]" class="large-text code" rows="5"><?php echo ($this->prefill && isset($_POST['smq-test_mail']['message']) ? trim($_POST['smq-test_mail']['message']) : '' ) ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row">Don't wait for cronjob</th>
					<td>
						<input type="checkbox" name="smq-test_mail[dont_wait]" id="dont_wait" value="1" <?php echo ($this->prefill && isset($_POST['smq-test_mail']['dont_wait']) ? 'checked="chencked"' : '') ?>>
						<label for="dont_wait">Send directly without waiting for cronjob to process queue</label>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" class="button button-primary" value="Send Test Email" />
				<?php wp_nonce_field('smq-test_mail', 'smq-test_mail_nonce'); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Prints form for starting queue processing
	 */
	private function createProcessQueueForm() {
		?>
		<form method="post" action="">
			<p class="submit">
				<input type="hidden" name="smq-process_queue" value="1"/>
				<input type="submit" class="button button-primary" value="Start Process Queue" />
				<?php wp_nonce_field('smq-process_queue', 'smq-process_queue_nonce'); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Processes testmmail form
	 */
	public function sendTestMail() {
		if(!check_admin_referer('smq-test_mail', 'smq-test_mail_nonce')) {
			$this->showNotice('Looks like you\'re not allowed to do this');
			return;
		}
		$data = $_POST['smq-test_mail'];

		$error = false;
		if(empty($data['to'])) {
			$this->showNotice('Email address required');
			$error = true;
		}
		if(empty($data['subject'])) {
			$this->showNotice('Subject required');
			$error = true;
		}
		if(empty($data['message'])) {
			$this->showNotice('Message required');
			$error = true;
		}
		if($error) {
			$this->prefill = true;
			return;
		}

		$data['headers'] = [];
		$cc = array_filter(array_map('trim', explode(',', $data['cc'])));
		foreach ($cc as $email)
			$data['headers'][] = 'Cc:' . $email;
		$bcc = array_filter(array_map('trim', explode(',', $data['bcc'])));
		foreach ($bcc as $email)
			$data['headers'][] = 'Bcc:' . $email;
		if($data['dont_wait'])
			$this->reallySendTestmail($data);
		else
			$this->writeTestmailToFile($data);
	}

	/**
	 * Writes testmail data to json file
	 *
	 * @param array $data Testmail data
	 */
	protected function writeTestmailToFile($data) {
		if(wp_mail( $data['to'], $data['subject'], $data['message'], $data['headers']))
			$this->showNotice('Mail file created. Will be sent when cronjob runs', 'updated');
		else
			$this->showNotice('Error writing mail data to file');
	}

	/**
	 * Sends testmail instead of writing to json file
	 *
	 * @param array $data Testmail data
	 */
	protected function reallySendTestmail($data) {
		require_once('SMTPMailingQueueOriginal.php');
		if(SMTPMailingQueueOriginal::wp_mail($data['to'], $data['subject'], $data['message'], $data['headers']))
			$this->showNotice('Mail successfully sent.', 'updated');
		else
			$this->showNotice('Error sending mail');
	}

	/**
	 * Shows wp-styled (error|updated) messages
	 *
	 * @param string $message
	 * @param string $type
	 */
	protected function showNotice($message, $type = 'error') {
		add_action('admin_notices', function() use ($message, $type) {
			echo "<div class='$type'><p>$message</p></div>";
		});
	}

	/**
	 * Processes starting queue processing form
	 */
	public function startProcessQueue() {
		global $smtpMailingQueue;

		if(!check_admin_referer('smq-process_queue', 'smq-process_queue_nonce')) {
			$this->showNotice('Looks like you\'re not allowed to do this');
			return;
		}

		$smtpMailingQueue->callProcessQueue();

		$this->showNotice('Emails sent', 'updated');
	}

	/**
	 * Prints table with mailing queue
	 */
	private function createListQueue() {
		global $smtpMailingQueue;
		$data = $smtpMailingQueue->loadDataFromFiles(true);
		if(!$data) {
			echo '<p>No mails in queue</p>';
			return;
		}
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th>Time</th>
					<th>To</th>
					<th>Subject</th>
					<th>Message</th>
					<th>Headers</th>
					<th>Attachments</th>
				</tr>
			</thead>
			<?php $i = 1; ?>
			<?php foreach($data as $mail): ?>
				<?php
				$dt = new DateTime("now", new DateTimeZone($this->getTimezoneString()));
				$dt->setTimestamp($mail['time']);
				?>
				<tr class="<?php echo ($i % 2) ? 'alternate' : ''; ?>">
					<td><?php echo $dt->format('F dS Y, H:i') ?></td>
					<td><?php echo $mail['to'] ?></td>
					<td><?php echo $mail['subject'] ?></td>
					<td><?php echo nl2br($mail['message']) ?></td>
					<td><?php echo is_array($mail['headers']) ?  implode('<br />', $mail['headers']) : $mail['headers']; ?></td>
					<td><?php echo implode('<br />', $mail['attachments']); ?></td>
				</tr>
				<?php $i++; ?>
			<?php endforeach; ?>
		</table>
		<?php
	}




	/**
	 * Finds valid timezone for timezone_string setting in wp
	 *
	 * @return string Valid timezone
	 *
	 * @see: https://www.skyverge.com/blog/down-the-rabbit-hole-wordpress-and-timezones/
	 */
	protected function getTimezoneString() {

			// if site timezone string exists, return it
			if ( $timezone = get_option( 'timezone_string' ) )
				return $timezone;

			// get UTC offset, if it isn't set then return UTC
			if ( 0 === ( $utc_offset = get_option( 'gmt_offset', 0 ) ) )
				return 'UTC';

			// adjust UTC offset from hours to seconds
			$utc_offset *= 3600;

			// attempt to guess the timezone string from the UTC offset
			if ( $timezone = timezone_name_from_abbr( '', $utc_offset, 0 ) ) {
				return $timezone;
			}

			// last try, guess timezone string manually
			$is_dst = date( 'I' );

			foreach ( timezone_abbreviations_list() as $abbr ) {
				foreach ( $abbr as $city ) {
					if ( $city['dst'] == $is_dst && $city['offset'] == $utc_offset )
						return $city['timezone_id'];
				}
			}

			// fallback to UTC
			return 'UTC';
	}
}