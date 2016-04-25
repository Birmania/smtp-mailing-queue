<?php

class SMTPMailingQueueUpdate {

	/**
	 * @var SMTPMailingQueue
	 */
	protected $smtpMailingQueue;

	/**
	 * Handles plugin updates if necessary.
	 */
	public function update() {
		require_once ABSPATH . WPINC . '/class-phpmailer.php';

		global $smtpMailingQueue;
		$this->smtpMailingQueue = $smtpMailingQueue;

		$installedVersion = get_option( "smq_version" );

		if(version_compare($installedVersion, $this->smtpMailingQueue->pluginVersion, '='))
			return;

		if(version_compare($installedVersion, '1.0.6', '<'))
			$this->update_1_0_6();

		update_option( 'smq_version', $this->smtpMailingQueue->pluginVersion);
	}

	/**
	 * Due to a bug in 1.0.5 we need to check the stored mails for validity.
	 */
	protected function update_1_0_6() {
		$queue = $this->smtpMailingQueue->loadDataFromFiles(true, false);
		$errors = $this->smtpMailingQueue->loadDataFromFiles(true, true);

		foreach($queue as $file => $email) {
			if(!PHPMailer::validateAddress($email['to'])) {
				$this->smtpMailingQueue->deleteFile($file);

				SMTPMailingQueue::storeMail(
					$email['to'], $email['subject'], $email['message'], $email['headers'],
					$email['attachments'], $email['time']
				);
			}
		}

		foreach($errors as $file => $email) {
			$this->smtpMailingQueue->deleteFile($file);
			SMTPMailingQueue::storeMail(
				$email['to'], $email['subject'], $email['message'], $email['headers'],
				$email['attachments'], $email['time']
			);
		}
	}

}