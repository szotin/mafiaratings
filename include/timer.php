<?php

$timer_control_exists = false;

function timer_form_start()
{
    echo '<form method="post" name="timerForm">';
}

function timer_form_end()
{
    echo '</form>';
}

function timer_control_width()
{
	return 64;
}

function timer_control($timer_value, $prompt_time, $reset = false)
{
	global $_REQUEST, $timer_control_exists;
	
	if ($timer_control_exists)
	{
		return;
	}
	$timer_control_exists = true;
	
	$running = 0;
	if (!$reset)
	{
		if (isset($_REQUEST['clock']))
		{
			$timer_value = $_REQUEST['clock'];
		}
		if (isset($_REQUEST['timerRunning']))
		{
			$running = $_REQUEST['timerRunning'];
		}
	}

	echo '<button type="button" name="timerButton" class="timer" onclick="change_timer()"><img name="timerImage" src="';
	if ($running)
	{
		echo 'images/pause.png';
	}
	else
	{
		echo 'images/resume.png';
	}
	echo '"></button>';
	echo '<input type="text" name="clock" size="2" class="timer">';
	echo '<input type="hidden" name="timerRunning" value="' . $running . '">';
	
?>
	<script type="text/javascript" src="sound/soundmanager2-nodebug-jsmin.js"></script>

	<script language="JavaScript" type="text/javascript">
	<!--
		var sound10;
		var soundEnd;
		soundManager.url = 'sound/';
		soundManager.useFlashBlock = false;
		soundManager.onready(
			function()
			{
				if (soundManager.supported())
				{
					sound10 = soundManager.createSound({id: 'sound10', url: 'sound/10sec.mp3'});
					soundEnd = soundManager.createSound({id: 'soundEnd', url: 'sound/end.mp3'});
				}
			});
			
		var start = (new Date()).getTime();
		var timer_value = <?php echo $timer_value; ?>;
		var prompt_time = <?php echo $prompt_time; ?>;
		var running = <?php echo $running; ?>;
		var old_time = timer_value;

		function change_timer()
		{
			if (running > 0)
			{
				document["timerImage"].src = "images/resume.png";
				running = 0;
				document.timerForm.timerRunning.value = "0";
			}
			else
			{
				document["timerImage"].src = "images/pause.png";
				timer_value = parseInt(document.timerForm.clock.value);
				start = (new Date()).getTime();
				running = 1;
				document.timerForm.timerRunning.value = "1";
			}
			show_timer();
		}
	
		function show_timer() {
			
			var now = (new Date()).getTime();
			var t = timer_value - Math.round((now - start) / 1000);
			if (t <= 0)
			{
				document.timerForm.clock.value = "0";
				document["timerImage"].src = "images/resume.png";
				running = 0;
				document.timerForm.timerRunning.value = "0";
				soundEnd.play();
			}
			else
			{
				document.timerForm.clock.value = "" + t;
				if (running > 0)
				{
					setTimeout("show_timer()", 1000);
				}
				if (t <= prompt_time && old_time > prompt_time)
				{
					sound10.play();
				}
			}
			old_time = t;
		}
		
		show_timer();
		
	//-->
	</script>
<?php
}

?>