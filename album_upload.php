<?php

require_once 'include/photo_album.php';

class Page extends AlbumPageBase
{
	protected function show_body()
	{
		check_permissions(PERMISSION_USER);
		
		echo '<p><input type="file" id="upload-btn" onchange="uploadFiles()" multiple></p>';
		echo '<div id="upload-progress"></div>';
	}
	
	protected function js()
	{
?>
		function uploadFile(i, files)
		{
			json.upload('api/ops/photo.php', 
			{
				op: "create",
				album_id: <?php echo $this->album->id; ?>,
				photo: files[i]
			}, 
			<?php echo UPLOAD_PHOTO_MAX_SIZE; ?>, 
			function (result)
			{
				$('#progress-' + i).html('<img src="pics/photo/tnails/' + result.photo_id + '.jpg">');
				$('#row-' + i).addClass('dark');
				if (++i < files.length)
				{
					uploadFile(i, files);
				}
			},
			function (param)
			{
				$('#progress-' + i).html(param);
				$('#row-' + i).addClass('dark');
				if (++i < files.length)
				{
					uploadFile(i, files);
				}
				return true; // True means that we proceeded everything. No error dialog required.
			});
		}
		
		function uploadFiles()
		{
			var files = document.getElementById("upload-btn").files;
			if (files.length > 0)
			{
				var progress = '<?php echo '<table class="bordered light" width="100%"><tr class="darker"><td>' . get_label('File name') . '</td><td width="120">' . get_label('File size') . '</td><td width="200">' . get_label('Result') . '</td></tr>'; ?>';
				for (var i = 0; i < files.length; ++i)
				{
					var file = files[i];
					progress += '<tr id="row-' + i + '"><td>' + file.name + '</td><td>' + file.size + '</td><td id="progress-' + i + '"></td></tr>';
				}
				progress += "</table>";
				$('#upload-progress').html(progress);
				
				uploadFile(0, files);
			}
			else
			{
				$('#upload-progress').html('');
			}
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Photos'));

?>
