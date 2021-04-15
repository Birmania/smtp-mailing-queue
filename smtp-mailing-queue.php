<?php
/*
Plugin Name: SMTP Mailing Queue
Plugin URI: http://dennishildenbrand.com
Description: SMTP Mailing Queue
Author: Dennis Hildenbrand
Version: 1.4.0
Author URI: http://dennishildenbrand.com
Text Domain: smtp-mailing-queue
*/

// checking for php version
if(is_admin() && version_compare(PHP_VERSION, '5.4', '<')) {
	function smq_min_reqs() {
		echo '<div class="error">
			<h3>' . sprintf(__('SMTP Mailing Queue requires at least PHP 5.4 The version you are using is %s.', 'smtp-mailing-queue'), '<i>' . PHP_VERSION . '</i>') . '</h3>
			<h3><a href="http://php.net/eol.php" target="_blank">' . __('PHP 5.3 was discontinued by the PHP development team on August 14, 2014!', 'smtp-mailing-queue') . '</a></h3>
			<p>' . sprintf(__('For security reasons we %s warmly suggest %s that you contact your hosting provider and ask to update your account to the latest stable PHP version, but at least PHP 5.4.', 'smtp-mailing-queue'), '<b>', '</b>') . '</p>
			<p>' . sprintf(__('If they refuse for whatever reason we suggest to %s change provider as soon as possible. %s', 'smtp-mailing-queue'), '<b>', '</b>') . '</p>
			</div>';
		$plugins = get_option('active_plugins');
		$out = array();
		foreach($plugins as $key => $val) {
			if($val != 'smtp-mailing-queue/smtp-mailing-queue.php')
				$out[$key] = $val;
		}
		update_option('active_plugins', $out);
	}
	add_action('admin_head', 'smq_min_reqs');
	return;
} else {

	require_once('classes/SMTPMailingQueue.php');
	require_once('classes/SMTPMailingQueueUpdate.php');
	require_once('classes/SMTPMailingQueueOptions.php');
	require_once('classes/SMTPMailingQueueAdvancedOptions.php');
	require_once('classes/SMTPMailingQueueTools.php');
	require_once('classes/OriginalPluggeable/OriginalPluggeable.php');

	$originalPluggeable = new OriginalPluggeable();
	$smtpMailingQueue = new SMTPMailingQueue(__FILE__, $originalPluggeable);
	$smtpMailingQueueUpdate = new SMTPMailingQueueUpdate($smtpMailingQueue);
	new SMTPMailingQueueOptions($smtpMailingQueue);
	new SMTPMailingQueueAdvancedOptions($smtpMailingQueue);
	new SMTPMailingQueueTools($smtpMailingQueue, $originalPluggeable);

	// overwriting wp_mail() to store mailing data instead of sending immediately
	if (!function_exists('wp_mail') && !isset($_GET['smqProcessQueue'])) {
		function wp_mail($to, $subject, $message, $headers = '', $attachments = array())
		{
			global $smtpMailingQueue;
			return $smtpMailingQueue->wp_mail($to, $subject, $message, $headers, $attachments);
		}
	}

	add_action('plugins_loaded', array($smtpMailingQueueUpdate, 'update'));
}