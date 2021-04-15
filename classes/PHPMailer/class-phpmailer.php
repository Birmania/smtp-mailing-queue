<?php
global $wp_version;
if( $wp_version < '5.5') {
	require_once(ABSPATH . WPINC . '/class-phpmailer.php');
}
else {
	require_once(ABSPATH . WPINC . '/PHPMailer/PHPMailer.php');
	require_once(ABSPATH . WPINC . '/PHPMailer/SMTP.php');
	require_once(ABSPATH . WPINC . '/PHPMailer/Exception.php');
	
	class_alias( PHPMailer\PHPMailer\PHPMailer::class, 'PHPMailer' );
	class_alias( PHPMailer\PHPMailer\Exception::class, 'phpmailerException' );
}