<?php
/*
Plugin Name: SMTP Mailing Queue
Plugin URI: http://dennishildenbrand.com
Description: SMTP Mailing Queue
Author: Dennis Hildenbrand
Version: 1.0.5
Author URI: http://dennishildenbrand.com
*/

// checking for php version
if(is_admin() && version_compare(PHP_VERSION, '5.4', '<')) {
	function smq_min_reqs() {
		echo '<div class="error">
			<h3>SMTP Mailing Queue requires at least PHP 5.4 The version you are using is <b>' . PHP_VERSION . '</b></h3>
			<h3><a href="http://php.net/eol.php" target="_blank">PHP 5.3 was discontinued by the PHP development team on August 14, 2014!</a></h3>
			<p>For security reasons we <b>warmly suggest</b> that you contact your hosting provider and ask to update your account to the latest stable PHP version, but at least PHP 5.4.</p>
			<p>If they refuse for whatever reason we suggest to <b>change provider as soon as possible.</b></p>
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
}

require_once('classes/SMTPMailingQueue.php');
require_once('classes/SMTPMailingQueueOptions.php');
require_once('classes/SMTPMailingQueueAdvancedOptions.php');
require_once('classes/SMTPMailingQueueTools.php');

$smtpMailingQueue = new SMTPMailingQueue(__FILE__);
new SMTPMailingQueueOptions();
new SMTPMailingQueueAdvancedOptions();
new SMTPMailingQueueTools();

// overwriting wp_mail() to store mailing data instead of sending immediately
if (!function_exists('wp_mail') && !isset($_GET['smqProcessQueue'])) {
	function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
		global $smtpMailingQueue;
		return $smtpMailingQueue->wp_mail($to, $subject, $message, $headers, $attachments);
	}
}