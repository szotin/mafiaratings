<?php

require_once 'include/photo_album.php';

class Page extends AlbumPageBase
{
	protected function show_body()
	{
		echo '<table class="transp" width="100%"><tr>';
		echo '<td width="120" valign="top" align="center">' . get_label('Upload photos') . '<span id="spanButtonPlaceHolder"></span></td>';
		echo '<td><form id="form1" action="index.php" method="post" enctype="multipart/form-data">';
		echo '<div class="fieldset flash" id="fsUploadProgress">';
		echo '<span class="darker">' . get_label('Upload Queue') . '</span>';
		echo '</div>';
		echo '</form></td></tr></table>';
?>

		<script type="text/javascript" src="js/swfupload.js"></script>
		<script type="text/javascript" src="js/swfupload.queue.js"></script>
		<script type="text/javascript" src="js/fileprogress.js"></script>
		<script type="text/javascript">
			var swfu;

			window.onload = function()
			{
				var settings =
				{
					flash_url: "js/swfupload.swf",
					upload_url: "upload.php",
					post_params:
					{
						"PHPSESSID": "<?php echo session_id(); ?>",
						"id": "<?php echo $this->album->id; ?>",
						"code": "<?php echo PHOTO_CODE; ?>"
					},
					file_size_limit: "2 MB",
					file_types: "*.jpg; *.jpeg; *.png",
					file_types_description: "<?php echo get_label('Picture Files'); ?>",
					file_upload_limit: 100,
					file_queue_limit: 0,
					custom_settings:
					{
						progressTarget: "fsUploadProgress"
					},
					debug: false,

					// Button settings
					button_width: "60",
					button_height: "48",
					button_image_url: "images/upload.png",
					button_placeholder_id: "spanButtonPlaceHolder",

					// The event handler functions are defined in handlers.js
					file_queued_handler: fileQueued,
					file_queue_error_handler: fileQueueError,
					file_dialog_complete_handler: fileDialogComplete,
					upload_start_handler: uploadStart,
					upload_progress_handler: uploadProgress,
					upload_error_handler: uploadError,
					upload_success_handler: uploadSuccess
				};

				swfu = new SWFUpload(settings);
			};

			function fileQueued(file)
			{
				var progress = new FileProgress(file, this.customSettings.progressTarget);
				progress.setStatus("<?php echo get_label('Pending...'); ?>");
			}

			function fileQueueError(file, errorCode, message)
			{
				if (errorCode === SWFUpload.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED)
				{
					alert("<?php echo get_label('You have selected too many photos. We do not allow to upload more than 100 in once.'); ?>");
					return;
				}

				var progress = new FileProgress(file, this.customSettings.progressTarget);
				progress.setError();

				switch (errorCode)
				{
				case SWFUpload.QUEUE_ERROR.FILE_EXCEEDS_SIZE_LIMIT:
					progress.setStatus("<?php echo get_label('File size exeeds 2 Mb. We do not accept big files.'); ?>");
					break;
				case SWFUpload.QUEUE_ERROR.ZERO_BYTE_FILE:
					progress.setStatus("<?php echo get_label('Cannot upload Zero Byte files.'); ?>");
					break;
				case SWFUpload.QUEUE_ERROR.INVALID_FILETYPE:
					progress.setStatus("<?php echo get_label('This is not a picture.'); ?>");
					break;
				default:
					progress.setStatus("<?php echo get_label('Unhandled Error'); ?>");
					break;
				}
			}

			function fileDialogComplete(numFilesSelected, numFilesQueued)
			{
				this.startUpload();
			}

			function uploadStart(file)
			{
				var progress = new FileProgress(file, this.customSettings.progressTarget);
				progress.setStatus("<?php echo get_label('Uploading...'); ?>");
				return true;
			}

			function uploadProgress(file, bytesLoaded, bytesTotal)
			{
				var percent = Math.ceil((bytesLoaded / bytesTotal) * 100);
				var progress = new FileProgress(file, this.customSettings.progressTarget);
				progress.setProgress(percent);
				progress.setStatus("<?php echo get_label('Uploading...'); ?>");
			}

			function uploadSuccess(file, serverData)
			{
				var progress = new FileProgress(file, this.customSettings.progressTarget);
				progress.setComplete();
				progress.setStatus(serverData);
			}

			function uploadError(file, errorCode, message)
			{
				var progress = new FileProgress(file, this.customSettings.progressTarget);
				progress.setError();
				progress.setStatus("<?php echo get_label('Upload Error'); ?> " + ': ' + message);
			}
		</script>
<?php
	}
}

$page = new Page();
$page->run(get_label('Photos'));

?>