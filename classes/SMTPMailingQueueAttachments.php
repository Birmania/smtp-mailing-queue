<?php

class SMTPMailingQueueAttachments {


	public function __construct() {
		$this->init();
	}

	/**
	 * Adds hooks, actions and filters for plugin.
	 */
	protected function init() {
		// DO NOTHING
	}
	
	public static function storeAttachments($attachments) {
		$attachments_stored = [];
	
		foreach ($attachments as $attachment)
		{
			$uploads_dir = SMTPMailingQueue::getUploadDir('attachments');
			$uploads_dir = SMTPMailingQueueAttachments::initRandomDir( $uploads_dir );
		
			$filename = basename($attachment);

			$filename = wp_unique_filename( $uploads_dir, $filename );
			$new_file = path_join( $uploads_dir, $filename );

			if ( false === copy( $attachment, $new_file ) ) {
				error_log("SMTP Mailing Queue : Unable to copy attachment from %{attachment} to %{$new_file}");
			} else
			{
				// Make sure the uploaded file is only readable for the owner process
				chmod( $new_file, 0400 );
				$attachments_stored[] = $new_file;
			}
		}
		
		return $attachments_stored;
	}

	public static function initRandomDir($dir){
		do {
			$rand_max = mt_getrandmax();
			$rand = zeroise( mt_rand( 0, $rand_max ), strlen( $rand_max ) );
			$dir_new = path_join( $dir, $rand );
		} while ( file_exists( $dir_new ) );

		if ( wp_mkdir_p( $dir_new ) ) {
			return $dir_new;
		}

		return $dir;
	}
	
	public static function removeAttachments($attachments) {
		foreach ( $attachments as $index => $attachment ) {
			unlink( $attachment );

			if ( ( $dir = dirname( $attachment ) )
			&& false !== ( $files = scandir( $dir ) )
			&& ! array_diff( $files, array( '.', '..' ) ) ) {
				// remove parent dir if it's empty.
				rmdir( $dir );
			}
		}
	}
}