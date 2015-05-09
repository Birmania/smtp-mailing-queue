<?php
/*
Plugin Name: SMTP Mailing Queue
Plugin URI: http://dennishildenbrand.com
Description: SMTP Mailing Queue
Author: Dennis Hildenbrand
Version: 1.0.2
Author URI: http://dennishildenbrand.com
*/
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
	function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
		global $smtpMailingQueue;
		return $smtpMailingQueue->storeMail($to, $subject, $message, $headers, $attachments);
	}
}