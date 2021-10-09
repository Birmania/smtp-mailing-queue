<?php
require_once('SMTPMailingQueueAdmin.php');

class SMTPMailingQueueTools extends SMTPMailingQueueAdmin {

	/**
	 * @var SMTPMailingQueue
	 */
	private $smtpMailingQueue;
	
	/**
	 * @var Object used  to call original pluggeable methods
	 */
	private $originalPluggeable;

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

	public function __construct(SMTPMailingQueue $smtpMailingQueue, OriginalPluggeable $originalPluggeable) {
		parent::__construct();
		$this->smtpMailingQueue = $smtpMailingQueue;
		$this->originalPluggeable = $originalPluggeable;
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
			<li>
				<strong><?php _e('Test Mail', 'smtp-mailing-queue')?></strong>:
				<?php _e('Test your email settings by sendig directly or adding test mail into queue.', 'smtp-mailing-queue')?>
			</li>
			<li>
				<strong><?php _e('Process Queue', 'smtp-mailing-queue')?></strong>:
				<?php _e('Start queue processing manually. Your set queue limit will still be obeyed, if set.', 'smtp-mailing-queue')?>
			</li>
		</ul>
		<h3 class="nav-tab-wrapper">
			<a href="?page=smtp-mailing-queue&tab=tools&tool=testmail" class="nav-tab <?php echo $this->activeTool == 'testmail' ? 'nav-tab-active' : '' ?>">
				<?php _e('Test Mail', 'smtp-mailing-queue')?>
			</a>
			<a href="?page=smtp-mailing-queue&tab=tools&tool=processQueue" class="nav-tab <?php echo $this->activeTool == 'processQueue' ? 'nav-tab-active' : '' ?>">
				<?php _e('Process Queue', 'smtp-mailing-queue')?>
			</a>
		</h3>
		<?php

		switch($this->activeTool) {
			case 'testmail':
				$this->createTestmailForm();
				break;
			case 'processQueue':
				$this->createProcessQueueForm();
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
					<th scope="row"><?php _e('To email address', 'smtp-mailing-queue') ?></th>
					<td>
						<input type="text" name="smq-test_mail[to]" class="regular-text code"
						       value="<?php echo ($this->prefill && isset($_POST['smq-test_mail']['to']) ? $_POST['smq-test_mail']['to'] : '' ) ?>"/>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Cc email addresses', 'smtp-mailing-queue') ?></th>
					<td>
						<input type="text" name="smq-test_mail[cc]" class="regular-text code"
						       value="<?php echo ($this->prefill && isset($_POST['smq-test_mail']['cc']) ? $_POST['smq-test_mail']['cc'] : '' ) ?>"/>
						<p class="description"><?php _e('Multiple addresses can be added separated by comma.', 'smtp-mailing-queue') ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Bcc email addresses', 'smtp-mailing-queue') ?></th>
					<td>
						<input type="text" name="smq-test_mail[bcc]" class="regular-text code"
						       value="<?php echo ($this->prefill && isset($_POST['smq-test_mail']['bcc']) ? $_POST['smq-test_mail']['bcc'] : '' ) ?>"/>
						<p class="description"><?php _e('Multiple addresses can be added separated by comma.', 'smtp-mailing-queue') ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Subject', 'smtp-mailing-queue') ?></th>
					<td>
						<input type="text" name="smq-test_mail[subject]" class="regular-text code"
						       value="<?php echo ($this->prefill && isset($_POST['smq-test_mail']['subject']) ? $_POST['smq-test_mail']['subject'] : '' ) ?>"/>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('Message', 'smtp-mailing-queue') ?></th>
					<td>
						<textarea name="smq-test_mail[message]" class="large-text code" rows="5"><?php echo ($this->prefill && isset($_POST['smq-test_mail']['message']) ? trim($_POST['smq-test_mail']['message']) : '' ) ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e("Don't wait for cronjob", 'smtp-mailing-queue') ?></th>
					<td>
						<input type="checkbox" name="smq-test_mail[dont_wait]" id="dont_wait" value="1" <?php echo ($this->prefill && isset($_POST['smq-test_mail']['dont_wait']) ? 'checked="chencked"' : '') ?>>
						<label for="dont_wait"><?php _e('Send directly without waiting for cronjob to process queue', 'smtp-mailing-queue') ?></label>
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
			$this->showNotice(__('Looks like you\'re not allowed to do this', 'smtp-mailing-queue'));
			return;
		}
		$data = $_POST['smq-test_mail'];

		$error = false;
		if(empty($data['to'])) {
			$this->showNotice(__('Email address required', 'smtp-mailing-queue'));
			$error = true;
		}
		if(empty($data['subject'])) {
			$this->showNotice(__('Subject required', 'smtp-mailing-queue'));
			$error = true;
		}
		if(empty($data['message'])) {
			$this->showNotice(__('Message required', 'smtp-mailing-queue'));
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
		if(isset($data['dont_wait']) && $data['dont_wait'])
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
			$this->showNotice(__('Mail file created. Will be sent when cronjob runs', 'updated', 'smtp-mailing-queue'));
		else
			$this->showNotice(__('Error writing mail data to file', 'smtp-mailing-queue'));
	}

	/**
	 * Sends testmail instead of writing to json file
	 *
	 * @param array $data Testmail data
	 */
	protected function reallySendTestmail($data) {
		if($this->originalPluggeable->wp_mail($data['to'], $data['subject'], $data['message'], $data['headers']))
			$this->showNotice(__('Mail successfully sent.', 'smtp-mailing-queue'), 'updated');
		else
			$this->showNotice(__('Error sending mail', 'smtp-mailing-queue'));
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
		if(!check_admin_referer('smq-process_queue', 'smq-process_queue_nonce')) {
			$this->showNotice(__("Looks like you're not allowed to do this", 'smtp-mailing-queue'));
			return;
		}

		$this->smtpMailingQueue->callProcessQueue();

		$this->showNotice(__('Emails sent', 'smtp-mailing-queue'), 'updated');
	}

}