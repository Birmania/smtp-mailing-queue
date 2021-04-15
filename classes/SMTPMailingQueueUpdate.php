<?php

class SMTPMailingQueueUpdate
{

	/**
	 * @var SMTPMailingQueue
	 */
	private $smtpMailingQueue;

	public function __construct(SMTPMailingQueue $smtpMailingQueue) {
		$this->smtpMailingQueue = $smtpMailingQueue;
	}
	
	/**
	 * Handles plugin updates if necessary.
	 */
	public function update()
	{
		require_once __DIR__ . '/PHPMailer/class-phpmailer.php';

		$installedVersion = get_option("smq_version");

		if ($installedVersion) {
			if (version_compare($installedVersion, $this->smtpMailingQueue->pluginVersion, '='))
				return;

			if (version_compare($installedVersion, '1.0.6', '<'))
				$this->update_1_0_6();

			if (version_compare($installedVersion, '1.2.0', '<'))
				$this->update_1_2_0();
		}

		update_option('smq_version', $this->smtpMailingQueue->pluginVersion);
	}

	/**
	 * Due to a bug in 1.0.5 we need to check the stored mails for validity.
	 */
	protected function update_1_0_6()
	{
		$queue = $this->smtpMailingQueue->loadDataFromFiles(true, false);
		$errors = $this->smtpMailingQueue->loadDataFromFiles(true, true);

		foreach ($queue as $file => $email) {
			if (!PHPMailer::validateAddress($email['to'])) {
				$this->smtpMailingQueue->deleteMail($file);

				SMTPMailingQueue::storeMail(
					$email['to'], $email['subject'], $email['message'], $email['headers'],
					$email['attachments'], $email['time']
				);
			}
		}

		foreach ($errors as $file => $email) {
			$this->smtpMailingQueue->deleteMail($file);
			SMTPMailingQueue::storeMail(
				$email['to'], $email['subject'], $email['message'], $email['headers'],
				$email['attachments'], $email['time']
			);
		}
	}

	/**
	 * Due to a deprecated method in 1.1.0 we need to convert auth password.
	 */
	protected function update_1_2_0()
	{
		$options = get_option('smtp_mailing_queue_options');

		if (!$options) // options may not be set
			return;

		if (empty($options['auth_password']))
			return;

		$authPassword = $options['auth_password'];

		$decryptedPassword = $this->smtpMailingQueue->decrypt_1_1_0($authPassword);

		$options['auth_password'] = $this->smtpMailingQueue->encrypt($decryptedPassword);

		update_option('smtp_mailing_queue_options', $options);
	}
}