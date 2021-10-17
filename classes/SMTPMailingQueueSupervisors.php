<?php

require_once('SMTPMailingQueueEnums.php');

class SMTPMailingQueueSupervisors extends SMTPMailingQueueAdmin {

	/**
	 * @var SMTPMailingQueue
	 */
	private $smtpMailingQueue;

	/**
	 * @var string Slug of this tab
	 */
	private $tabName = 'supervisors';

	/**
	 * @var bool Prefill form fields after error
	 */
	private $prefill = false;

	public function __construct(SMTPMailingQueue $smtpMailingQueue) {
		parent::__construct();
		$this->smtpMailingQueue = $smtpMailingQueue;
		$this->init();
	}

	/**
	 * Sets active supervisors slug.
	 * Loads content for active supervisors if this tab is active
	 *
	 */
	private function init() {
		if(!is_admin() || $this->activeTab !== $this->tabName)
			return;
		$this->activeSupervisor = isset($_GET['supervisor']) ? $_GET['supervisor'] : 'processing';

		if(isset($_POST['smq-purge_all_invalid']))
			add_action('init', [$this, 'purgeAllInvalid']);
		if(isset($_POST['smq-bulk_actions_invalid']))
			add_action('init', [$this, 'bulkActionsInvalid']);
		if(isset($_POST['smq-purge_all_sent']))
			add_action('init', [$this, 'purgeAllSent']);
		if(isset($_POST['smq-bulk_actions_sent']))
			add_action('init', [$this, 'bulkActionsSent']);
		add_action( 'admin_menu', [$this, 'add_plugin_page']);
	}

	/**
	 * Prints tab header
	 */
	public function loadPageContent() {
		?>
		<ul>
			<li>
				<strong><?php _e('Processing', 'smtp-mailing-queue')?></strong>:
				<?php _e('Show some informations about processing.', 'smtp-mailing-queue')?>
			</li>
			<li>
				<strong><?php _e('List Queue', 'smtp-mailing-queue')?></strong>:
				<?php _e('Show all mails in mailing queue.', 'smtp-mailing-queue')?>
			</li>
			<li>
				<strong><?php _e('Sending Errors', 'smtp-mailing-queue')?></strong>:
				<?php _e("Emails that could'nt be sent.", 'smtp-mailing-queue')?>
			</li>
			<li>
				<strong><?php _e('Sent', 'smtp-mailing-queue')?></strong>:
				<?php _e("Emails that have been sent.", 'smtp-mailing-queue')?>
			</li>
		</ul>
		<h3 class="nav-tab-wrapper">
			<a href="?page=smtp-mailing-queue&tab=supervisors&supervisor=processing" class="nav-tab <?php echo $this->activeSupervisor == 'processing' ? 'nav-tab-active' : '' ?>">
				<?php _e('Processing', 'smtp-mailing-queue')?>
			</a>
			<a href="?page=smtp-mailing-queue&tab=supervisors&supervisor=listQueue" class="nav-tab <?php echo $this->activeSupervisor == 'listQueue' ? 'nav-tab-active' : '' ?>">
				<?php _e('List Queue', 'smtp-mailing-queue')?>
			</a>
			<a href="?page=smtp-mailing-queue&tab=supervisors&supervisor=listInvalid" class="nav-tab <?php echo $this->activeSupervisor == 'listInvalid' ? 'nav-tab-active' : '' ?>">
				<?php _e('Sending Errors', 'smtp-mailing-queue')?>
			</a>
			<a href="?page=smtp-mailing-queue&tab=supervisors&supervisor=listSent" class="nav-tab <?php echo $this->activeSupervisor == 'listSent' ? 'nav-tab-active' : '' ?>">
				<?php _e('Sent', 'smtp-mailing-queue')?>
			</a>
		</h3>
		<?php
		
		switch($this->activeSupervisor) {
			case 'processing':
				$this->createProcessing();
				break;
			case 'listQueue':
				$this->createListQueue();
				break;
			case 'listInvalid':
				$this->createListInvalid();
				break;
			case 'listSent':
				$this->createListSent();
				break;
		}
	}

	public function createProcessing() {
		$availableDelay = $this->smtpMailingQueue->getQueueProcessingTimeout();
		$delayUsed = get_transient('smtp_mailing_queue_max_processing_delay');
		$processingNotice = get_transient('smtp_mailing_queue_processing_notice');

		if ($processingNotice) {
			printf('<div class="notice %1$s"><p>%2$s</p></div>', esc_attr($processingNotice['class']), esc_html($processingNotice['message']));
		}
		
		?>
		<ul>
			<li>
				<strong><?php _e('Authorized delay to process through wp-cron', 'smtp-mailing-queue')?></strong>:
				<?php printf( __('%d seconds', 'smtp-mailing-queue'), $availableDelay) ?>
			</li>
			<li>
				<strong><?php _e('Maximum delay reached while processing through wp-cron (last 24h)', 'smtp-mailing-queue')?></strong>:
				<?php printf( __('%d seconds', 'smtp-mailing-queue'), $delayUsed)?>
				<p class="description"><?php _e('If this maximum delay is too high, please reduce the queue limit.', 'smtp-mailing-queue') ?></p>
			</li>
		</ul>
		<?php
	}

	/**
	 * Purge all invalid mails
	 */
	public function purgeAllInvalid() {
		$data = $this->smtpMailingQueue->loadDataFromFiles(true, UploadType::Invalid);
		foreach($data as $file => $email) {
			$this->smtpMailingQueue->deleteMail($file);
		}
	}
	
	/**
	 * Purge all sent mails
	 */
	public function purgeAllSent() {
		$data = $this->smtpMailingQueue->loadDataFromFiles(true, UploadType::Sent);
		foreach($data as $file => $email) {
			$this->smtpMailingQueue->deleteMail($file);
		}
	}
	
	/**
	 * Execute action on all selected mails
	 */
	public function bulkActionsInvalid() {
		if (isset($_POST['action']) && isset($_POST['mails']))
		{
			$action = $_POST['action'];
			$mails = $_POST['mails'];

			if ($action === "retry") {
				foreach($mails as $basename) {
					$file = SMTPMailingQueue::getUploadDir(UploadType::Invalid) . $basename;
					$email = $this->smtpMailingQueue->retryMail($file);
				}
			} else if ($action === "delete") {
				foreach($mails as $basename) {
					$file = SMTPMailingQueue::getUploadDir(UploadType::Invalid) . $basename;
					$this->smtpMailingQueue->deleteMail($file);
				}
			}
		}
	}

	/**
	 * Execute action on all selected mails
	 */
	public function bulkActionsSent() {
		if (isset($_POST['action']) && isset($_POST['mails']))
		{
			$action = $_POST['action'];
			$mails = $_POST['mails'];

			if ($action === "delete") {
				foreach($mails as $basename) {
					$file = SMTPMailingQueue::getUploadDir(UploadType::Sent) . $basename;
					$this->smtpMailingQueue->deleteMail($file);
				}
			}
		}
	}


	/**
	 * Prints table with mailing queue
	 *
	 * @param bool $invalid
	 */
	private function createListQueue() {
		$data = $this->smtpMailingQueue->loadDataFromFiles(true, UploadType::Queued);
		if(!$data) {
			echo '<p>' . __('No mails in queue', 'smtp-mailing-queue') . '</p>';
			return;
		}
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php _e('Time', 'smtp-mailing-queue') ?></th>
					<th><?php _e('To', 'smtp-mailing-queue') ?></th>
					<th><?php _e('Subject', 'smtp-mailing-queue') ?></th>
					<th><?php _e('Message', 'smtp-mailing-queue') ?></th>
					<th><?php _e('Headers', 'smtp-mailing-queue') ?></th>
					<th><?php _e('Attachments', 'smtp-mailing-queue') ?></th>
					<th><?php _e('Failures', 'smtp-mailing-queue') ?></th>
				</tr>
			</thead>
			<?php $i = 1; ?>
			<?php foreach($data as $mail): ?>
				<?php
				$dtCreated = new DateTime("now", new DateTimeZone($this->getTimezoneString()));
				$dtCreated->setTimestamp($mail['time']);
				?>
				<tr class="<?php echo ($i % 2) ? 'alternate' : ''; ?>">
					<td><?php echo $dtCreated->format(__('F dS Y, H:i', 'smtp-mailing-queue')) ?></td>
					<td><?php echo $mail['to'] ?></td>
					<td><?php echo $mail['subject'] ?></td>
					<td><?php echo nl2br($mail['message']) ?></td>
					<td><?php echo is_array($mail['headers']) ?  implode('<br />', $mail['headers']) : $mail['headers']; ?></td>
					<td><?php echo implode('<br />', $mail['attachments']); ?></td>
					<td><?php echo $mail['failures'] ?></td>
				</tr>
				<?php $i++; ?>
			<?php endforeach; ?>
		</table>
		<?php
	}

		/**
	 * Prints table with mailing queue
	 *
	 */
	private function createListInvalid() {
		$data = $this->smtpMailingQueue->loadDataFromFiles(true, UploadType::Invalid);
		if(!$data) {
			echo '<p>' . __('No mails in invalid', 'smtp-mailing-queue') . '</p>';
			return;
		}
		?>
		<?php
		$this->createPurgeInvalidForm();
		?>
		<form method="post" action="">
			<select name="action">
				<option value="-1"><?php _e('Bulk actions', 'smtp-mailing-queue') ?></option>
				<option value="retry"><?php _e('Retry', 'smtp-mailing-queue') ?></option>
				<option value="delete"><?php _e('Delete', 'smtp-mailing-queue') ?></option>
			</select>
			<input type="submit" class="button button-primary" value="<?php _e('Apply', 'smtp-mailing-queue') ?>" />
			<table class="widefat">
				<thead>
					<tr>
						<th><input id="smq-select_all" type="checkbox"/></th>
						<th><?php _e('Time', 'smtp-mailing-queue') ?></th>
						<th><?php _e('To', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Subject', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Message', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Headers', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Attachments', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Failures', 'smtp-mailing-queue') ?></th>
					</tr>
				</thead>
				<?php $i = 1; ?>
				<?php foreach($data as $filename => $mail): ?>
					<?php
					$dtCreated = new DateTime("now", new DateTimeZone($this->getTimezoneString()));
					$dtCreated->setTimestamp($mail['time']);
					?>
					<tr class="<?php echo ($i % 2) ? 'alternate' : ''; ?>">
						<td><input class="smq-select_option" type="checkbox" name="mails[]" value="<?php echo basename($filename) ?>"/></td>
						<td><?php echo $dtCreated->format(__('F dS Y, H:i', 'smtp-mailing-queue')) ?></td>
						<td><?php echo $mail['to'] ?></td>
						<td><?php echo $mail['subject'] ?></td>
						<td><?php echo nl2br($mail['message']) ?></td>
						<td><?php echo is_array($mail['headers']) ?  implode('<br />', $mail['headers']) : $mail['headers']; ?></td>
						<td><?php echo implode('<br />', $mail['attachments']); ?></td>
						<td><?php echo $mail['failures'] ?></td>
					</tr>
					<?php $i++; ?>
				<?php endforeach; ?>
			</table>
			<input type="hidden" name="smq-bulk_actions_invalid" value="1"/>
			<?php wp_nonce_field('smq-process_queue', 'smq-process_queue_nonce'); ?>
		</form>
		<?php
	}
	
	/**
	 * Prints form for purging invalid mails
	 */
	private function createPurgeInvalidForm() {
		?>
		<form method="post" action="">
			<p class="submit">
				<input type="hidden" name="smq-purge_all_invalid" value="1"/>
				<input type="submit" class="button button-primary" value="<?php _e('Purge all these mails', 'smtp-mailing-queue') ?>" />
				<?php wp_nonce_field('smq-process_queue', 'smq-process_queue_nonce'); ?>
			</p>
		</form>
		<?php
	}
	
		/**
	 * Prints table with mailing queue
	 *
	 */
	private function createListSent() {
		$data = $this->smtpMailingQueue->loadDataFromFiles(true, UploadType::Sent);
		if(!$data) {
			echo '<p>' . __('No mails in sent', 'smtp-mailing-queue') . '</p>';
			return;
		}
		?>
		<?php
		$this->createPurgeSentForm();
		?>
		<form method="post" action="">
			<select name="action">
				<option value="-1"><?php _e('Bulk actions', 'smtp-mailing-queue') ?></option>
				<option value="delete"><?php _e('Delete', 'smtp-mailing-queue') ?></option>
			</select>
			<input type="submit" class="button button-primary" value="<?php _e('Apply', 'smtp-mailing-queue') ?>" />
			<table class="widefat">
				<thead>
					<tr>
						<th><input id="smq-select_all" type="checkbox"/></th>
						<th><?php _e('Time', 'smtp-mailing-queue') ?></th>
						<th><?php _e('To', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Subject', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Message', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Headers', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Attachments', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Failures', 'smtp-mailing-queue') ?></th>
						<th><?php _e('Sent time', 'smtp-mailing-queue') ?></th>
					</tr>
				</thead>
				<?php $i = 1; ?>
				<?php foreach($data as $filename => $mail): ?>
					<?php
					$dtCreated = new DateTime("now", new DateTimeZone($this->getTimezoneString()));
					$dtCreated->setTimestamp($mail['time']);
					$dtSent = new DateTime("now", new DateTimeZone($this->getTimezoneString()));
					$dtSent->setTimestamp($mail['sent_time']);
					?>
					<tr class="<?php echo ($i % 2) ? 'alternate' : ''; ?>">
						<td><input class="smq-select_option" type="checkbox" name="mails[]" value="<?php echo basename($filename) ?>"/></td>
						<td><?php echo $dtCreated->format(__('F dS Y, H:i', 'smtp-mailing-queue')) ?></td>
						<td><?php echo $mail['to'] ?></td>
						<td><?php echo $mail['subject'] ?></td>
						<td><?php echo nl2br($mail['message']) ?></td>
						<td><?php echo is_array($mail['headers']) ?  implode('<br />', $mail['headers']) : $mail['headers']; ?></td>
						<td><?php echo implode('<br />', $mail['attachments']); ?></td>
						<td><?php echo $mail['failures'] ?></td>
						<td><?php echo $dtSent->format(__('F dS Y, H:i', 'smtp-mailing-queue')) ?></td>
					</tr>
					<?php $i++; ?>
				<?php endforeach; ?>
			</table>
			<input type="hidden" name="smq-bulk_actions_sent" value="1"/>
			<?php wp_nonce_field('smq-process_queue', 'smq-process_queue_nonce'); ?>
		</form>
		<?php
	}

	/**
	 * Prints form for purging invalid mails
	 */
	private function createPurgeSentForm() {
		?>
		<form method="post" action="">
			<p class="submit">
				<input type="hidden" name="smq-purge_all_sent" value="1"/>
				<input type="submit" class="button button-primary" value="<?php _e('Purge all these mails', 'smtp-mailing-queue') ?>" />
				<?php wp_nonce_field('smq-process_queue', 'smq-process_queue_nonce'); ?>
			</p>
		</form>
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