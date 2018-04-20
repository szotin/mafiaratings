<?php

require_once 'include/session.php';
require_once 'include/languages.php';
require_once 'include/url.php';
require_once 'include/email.php';
require_once 'include/editor.php';

initiate_session();

try
{
	dialog_title(get_label('Edit advert'));

	if (!isset($_REQUEST['advert']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('advert')));
	}
	$advert_id = $_REQUEST['advert'];
	
	list ($message, $starts, $expires) = Db::record(get_label('advert'), 'SELECT message, timestamp, expires FROM news WHERE id = ?', $advert_id);

	echo '<textarea id="form-advert" cols="80" rows="4">' . htmlspecialchars($message, ENT_QUOTES) . '</textarea>';
	
?>	
	<script>
	var editor = CKEDITOR.instances['form-advert'];
	if(editor)
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
				id: <?php echo $advert_id; ?>,
				message: editor.getData(),
				starts: <?php echo $starts; ?>,
				expires: <?php echo $expires; ?>,
				update: ""
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
	echo '<error=' . $e->getMessage() . '>';
}

?>