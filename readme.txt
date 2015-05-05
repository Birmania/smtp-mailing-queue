=== SMTP Mailing Queue ===
Contributors: hildende
Tags: mail, smtp, phpmailer, mailing queue, wp_mail, email
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=KRBU2JDQUMWP4
Requires at least: 3.9
Tested up to: 4.2
Stable tag: 1.0.0
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
* You can display the mailing queue in the backend to see emails that will be sent with next processing.

Coming soon:

* Logging of mails and errors.
* Archive of sent mail.
* Storing mailing data in database instead of files.
* Using plugin for SMTP mails without using mailing queue.

Feel free to suggest features or send feedback in the [support section](https://wordpress.org/support/plugin/smtp-mailing-queue), via [email](mailto:dennis@dennishildenbrand.com) or by creating a pull request on [github](https://github.com/hildende/smtp-mailing-queue).

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


= Additional =
For apache a .htaccess file with `deny from all` is generated in mail storage dir.
For all systems that cannot read .htaccess you should deny access to `wp-content/uploads/smtp-mailing-queue/`.

= Usage =
After activation mails automatically queue to be processed by wp_cron or cronjob. SMTP will be used if settings set up.

Tools:

* Test Mail: Test your email settings by sendig directly or adding test mail into queue.
* Process Queue: Start queue processing manually. Your set queue limit will still be obeyed, if set.
* List Queue: Show all mails in mailing queue.

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

Sure, just [head over here](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=KRBU2JDQUMWP4)

== Screenshots ==

1. SMTP Setting
2. Advanced Settings
3. Tools

== Changelog ==
= 1.0.0 =

* First commit of the plugin