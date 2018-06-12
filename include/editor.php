<?php

require_once __DIR__ . '/session.php';

function show_single_editor($name, $text, $tags = NULL, $rows = 8, $cols = 80)
{
	global $_profile, $_lang_code;
	echo '<textarea id="' . $name . '" name="' . $name . '" cols="' . $cols . '" rows="' . $rows . '">' . htmlspecialchars($text, ENT_QUOTES) . "</textarea>\n";
	echo '<script type="text/javascript" src="ckeditor/ckeditor.js"></script>' . "\n";
	echo '<script type="text/javascript">' . "\n";
	echo '//<![CDATA[' . "\n";
	
	if ($tags != NULL)
	{
?>
		CKEDITOR.plugins.add( 'tokens',
		{  
			requires : ['richcombo'], //, 'styles' ],
			init : function( editor )
			{
				var config = editor.config,
				lang = editor.lang.format;

				// Gets the list of tags from the settings.
				var tags = []; //new Array();
				//this.add('value', 'drop_text', 'drop_label');
<?php
				$tags_count = count($tags);
				for ($i = 0; $i < $tags_count; ++$i)
				{
					list ($tag, $tag_name) = $tags[$i];
					echo "\t\t\t\ttags[" . $i . ']=["' . $tag . '", "' . $tag_name . '", "' . $tag_name . "\"];\n";
					// tags[0]=["[contact_name]", "Name", "Name"];
				}
				$ins_tag_label = get_label('Insert tag');
				$tags_label = get_label('Tags');
?>

				// Create style objects for all defined styles.
				editor.ui.addRichCombo( 'tokens',
				{
					label : "<?php echo $ins_tag_label; ?>",
					title :"<?php echo $ins_tag_label; ?>",
					voiceLabel : "<?php echo $ins_tag_label; ?>",
					className : 'cke_format',
					multiSelect : false,

					panel :
					{
						css : [ config.contentsCss, CKEDITOR.getUrl( editor.skinPath + 'editor.css' ) ],
						voiceLabel : lang.panelVoiceLabel
					},

					init : function()
					{
						this.startGroup( "<?php echo $tags_label; ?>" );
						//this.add('value', 'drop_text', 'drop_label');
						for (var this_tag in tags)
						{
							this.add(tags[this_tag][0], tags[this_tag][1], tags[this_tag][2]);
						}
					},

					onClick : function( value )
					{        
						editor.focus();
						editor.fire( 'saveSnapshot' );
						editor.insertHtml(value);
						editor.fire( 'saveSnapshot' );
					}
				});
			}
		});			
		
		var editor = CKEDITOR.replace('<?php echo $name; ?>',
		{
			skin : 'v2',
			language: '<?php echo $_lang_code; ?>',
			toolbar : [
				['Bold','Italic','Underline','-','TextColor','BGColor','-','Format','Font','FontSize','-','RemoveFormat'],
				['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock','-','NumberedList','BulletedList','-','Outdent','Indent','-','SpellChecker'],
				['Link','Unlink','-','Image','Table','HorizontalRule','SpecialChar'],
				['Source'],['tokens']],
			extraPlugins: 'tokens'
		});
<?php
	}
	else
	{
?>
		var editor = CKEDITOR.replace('<?php echo $name; ?>',
		{
			skin : 'v2',
			language: '<?php echo $_lang_code; ?>',
			toolbar : [
				['Bold','Italic','Underline','-','TextColor','BGColor','-','Format','Font','FontSize','-','RemoveFormat'],
				['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock','-','NumberedList','BulletedList','-','Outdent','Indent','-','SpellChecker'],
				['Link','Unlink','-','Image','Table','HorizontalRule','SpecialChar'],
				['Source'],['tokens']]
		});
<?php
	}

	echo "//]]>\n";
	echo "</script>\n";
}

?>