<?php

require_once 'include/session.php';
require_once 'include/editor.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('advert')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club'];

	echo '<textarea id="form-advert" cols="80" rows="4"></textarea>';

?>
	<script>
	var editor = CKEDITOR.instances['form-advert'];
	if (editor)
	{
		CKEDITOR.remove(editor);
	}
	editor = CKEDITOR.replace('form-advert',
	{
		skin : 'v2',
		language: '<?php echo $_lang_code; ?>',
		toolbar : [
			['Bold','Italic','Underline','-','TextColor','BGColor','-','Format','Font','FontSize','-','RemoveFormat'],
			['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock','-','NumberedList','BulletedList','-','Outdent','Indent','-','SpellChecker'],
			['Link','Unlink','-','Image','Table','HorizontalRule','SpecialChar'],
			['Source'],['tokens']]
	});
	
	function commit(onSuccess)
	{
		json.post("advert_ops.php",
			{
				id: <?php echo $club_id; ?>,
				message: editor.getData(),
				expires: <?php echo (time() + 604800); ?>,
				create: ""
			},
			onSuccess);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>