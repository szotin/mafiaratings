<?php

require_once 'include/session.php';
require_once 'include/languages.php';
require_once 'include/url.php';
require_once 'include/email.php';
require_once 'include/editor.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('note')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club'];

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">'.get_label('Note name').':</td><td><input id="form-name"> </td></tr>';
	echo '<tr><td>'.get_label('Note').':</td><td>';
	echo '<textarea id="form-note" cols="80" rows="4"></textarea>';
	echo '</td></tr>';
	echo '</table>';

?>	
	<script>
	var editor = CKEDITOR.instances['form-note'];
	if (editor)
	{
		CKEDITOR.remove(editor);
	}
	editor = CKEDITOR.replace('form-note',
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
		json.post("api/ops/note.php",
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, name: $("#form-name").val()
			, note: editor.getData()
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