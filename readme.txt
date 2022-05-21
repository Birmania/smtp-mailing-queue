=== SMTP Mailing Queue ===
Contributors: hildende, birmania
Tags: mail, smtp, phpmailer, mailing queue, wp_mail, email
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=KRBU2JDQUMWP4
Requires at least: 3.9
Tested up to: 5.9.3
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add emails to a mailing queue instead of sending immediately to speed up sending forms for the website visitor and lower server load.

== Description ==
This plugin adds emails to a mailing queue instead of sending immediately. This speeds up sending forms for the website visitor and lowers the server load.
Emails are stored as files which are deleted after emails are sent.

You can send all outgoing emails via an SMTP server or (the WordPress standard) PHP function [mail](http://php.net/manual/en/function.mail.php), and either use [wp_cron](https://codex.wordpress.org/Function_Reference/wp_cron) or a cronjob (if your server/hoster supports this) to process the queue.

Plugin requires PHP 5.4 or above.

Tools:

* You can send test mails to test your setup.
* You can process the mailing queue manually instead of waiting for cronjob.


Supervisors:
* You can display basic informations about mail processing performances
* You can display the mailing queue in the backend to see emails that will be sent with next processing.
* You can display the invalid mails and delete or retry them.
* You can display the sent mails and delete them.

Coming soon:

* Storing mailing data in database instead of files.
* Using plugin for SMTP mails without using mailing queue.

Feel free to suggest features or send feedback in the [support section](https://wordpress.org/support/plugin/smtp-mailing-queue), via [email](mailto:antoine.brultet@gmail.com) or by creating a pull request on [github](https://github.com/Birmania/smtp-mailing-queue).

== Installation ==
1. Upload the files to the `/wp-content/plugins/smtp-mailing-queue/` directory
2. Activate the \"SMTP Mailing Queue\" plugin through the \"Plugins\" admin page in WordPress
3. Go to \"SMTP Mailing Queue\" settings page in WordPress admin settings section (you can simply click the \"Settings\" link for this plugin in the \"Plugin\" page

= SMTP =
Enter the SMTP credentials you got from your mail provider.

**Common mail providers:**

**gmail**

* Host: smtp.gmail.com
* Port: 465
* Encryption: ssl
* Use authentication: yes
* Username: your full email address

**yahoo**

* Host: smtp.mail.yahoo.com
* Port: 465
* Encryption: ssl
* Use authentication: yes
* Username: your full email address

**office365**

* Host: smtp.office365.com
* Port: 587
* Encryption: tls
* Use authentication: yes
* Username: your full email address

If you have another mail provider you will most likely get the SMTP settings on their website or by asking them.

= Advanced =

* queue limit: Set the amount of mails sent per cronjob processing
* secret key: Set a key needed to start queue manually or via cronjob
* don't use wp_cron: Use a real cronjob instead of wp_cron.
	Call http://www.example.org**?smqProcessQueue&key=MySecretKey**  in cronjob to start processing queue.
* wp_cron interval: Choose how often wp_cron is started (in seconds)
* minimum recipients: Mail sending will be delayed (through queue) only if recipients number is higher than this value
* maximum retry: Mail sending will be retried until it reach this amount of failure


= Additional =
For apache a .htaccess file with `deny from all` is generated in mail storage dir.
For all systems that cannot read .htaccess you should deny access to `wp-content/uploads/smtp-mailing-queue/`.

= Usage =
After activation mails automatically queue to be processed by wp_cron or cronjob. SMTP will be used if settings set up.

Tools:

* Test Mail: Test your email settings by sendig directly or adding test mail into queue.
* Process Queue: Start queue processing manually. Your set queue limit will still be obeyed, if set.

Supervisors:

* Processing: Get basic informations about your current processing capacity how much it is used.
* List Queue: Show all mails in mailing queue.
* List Invalid: Show all mails in failed state. You can purge this list or retry some mails (retry : bring back failure count to 0 and mail moved to "List Queue").
* List Sent: Show all mails sent. You can purge this list. As  mails are not encrypted, it is recommended to use this option mostly for debug/analysis purposes.

== Frequently Asked Questions ==
= Can this plugin be used to send emails via SMTP? =

Yes.

= Do I have to use SMTP? =

No (just leave SMTP settings empty)

= Can anyone read the mails in a browser =

Not if you followed the advanced installation.

= Can I just use the SMTP function and sent immediatly without queuing? =

Not at the moment, but this will be added in a future release.

= I like this plugin. Can I buy you a beer? =

Sure, here are the donation links of top contributors :
[Hildende : Founder](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=KRBU2JDQUMWP4)
[Birmania : Maintainer](https://www.paypal.com/donate/?hosted_button_id=9LUJKR4XMJP8W)

== Screenshots ==

1. SMTP Setting
2. Advanced Settings
3. Tools
4. Supervisors

== Changelog ==

= 2.0.0 =
* Bugfix : 'wp_mail' filters are now immediately executed on call instead of being delayed to real email sending
* Bugfix : Removed an error that could occurs on 'localhost' development environent using custom value for From/Email in options
* Bugfix : Fixed a deprecated on optional 'pluginFile' field in SMTPMailingQueue constructor
* Bugfix : Strip Wordpress magic quotes on test email form
* Bugfix : Better email headers formatting in supervisor tables 

= 1.4.7 =
* Bugfix : In SMTP Settings, "From Email" and "From Name" are now used as soon as they are not empty, even if "Host value" is empty
* Bugfix : Schedule "smq_sanity_checks" on every plugin construct to avoid missing cronjob

= 1.4.6 =
* Bugfix : Fix a member call on 'null' when using the plugin from WP-CLI (Thanks @cvl01)

= 1.4.5 =
* Feature : Add PHPMailer error detail message in notice while using tool to send immediate test mail

= 1.4.4 =
* Bugfix : Enable sanity check on plugin update

= 1.4.3 =
* Feature : Added sanity check to ensure that smq_start_queue is properly requeued
* Feature : Move processing queue error from global admin to associated supervisor page

= 1.4.2 =
* Feature: Added capacity to store, list and purge sent mails
* Feature: Display basic informations about processing (max. time it take per run) to help adjust queue limit
* Feature: Mail lists are moved from "Tools" to a new tab called "Supervisors"
* Bugfix: Mails processing through wp-cron now use a timetout linked to wp-cron configuration (WP_CRON_LOCK_TIMEOUT / 2)

= 1.4.1 =
* Feature: Display admin notice if error occured while calling queue processing

= 1.4.0 =
* Feature: Compatibility with WP version >=5.5 (new PHPMailer)

= 1.3.1 =
* Bugfix: Fixed bug on randomly lost emails when requests are too close to each others (Thanks @manandre)
* Bugfix: Remove some notices and warning on first plugin activation

= 1.3.0 =
* Feature: Added capability to purge all invalid mails (Button available at Invalid List tab)
* Feature: Added bulk actions on invalid mails (retry, delete)
* Bugfix: Fixed bug on attachment deletion

= 1.2.2 =
* Bugfix: Fixed bug when sending delayed mail

= 1.2.1 =
* Bugfix: Fixed intermittent failure with plugin version upgrade

= 1.2.0 =
* Bugfix: Fixed deprecated methods 'mcrypt_get_iv_size', 'mcrypt_create_iv', 'mcrypt_encrypt'

* Bugfix: Fixed call to non-static method 'SMTPMailingQueueAttachments::storeAttachments'

* Bugfix: Fixed undefined index 'dont_wait' when sending test mail

= 1.1.1 =
* Feature: Added advanced option to retry mail sending X time before declare invalid.

* Feature: Added French translation.

* Bugfix: Attachments are no longer lost on mail sending.

= 1.1.0 =
* Feature: Made plugin translatable.

* Feature: Added German translation.

* Bugfix: Fixed cron requests on IDN hosts (Thanks to [epoxa](https://github.com/epoxa) for this fix)

* Bugfix: Fixed bug that caused plugin to ignore smtp settings in some cases.

* Bugfix: Fixed php5.3 incompatibility message.

= 1.0.6 =
* Bugfix: Emails that couldn't be sent now really don't stop the queue anymore.

= 1.0.5 =
* Feature/Bugfix: Added tools section for emails that couldn't be sent. Those emails will no longer stop the entire queue.

= 1.0.4 =

* Feature: Added advanced option to only queue mails if more than one recipient is set.

= 1.0.3 =

* Feature: Added warning on install if PHP version <5.4

* Bugfix: Use of WordPress URL instead of host name (Thanks to [mgoncharenko](https://github.com/mgoncharenko) for this fix)

= 1.0.2 =

* Bugfix: PHP warning for empty headers in list tool
* Bugfix: Wrong SMTP password stored at first save

= 1.0.1 =

* Bugfix: timeout at slow SMTP servers

= 1.0.0 =

* First commit of the plugin