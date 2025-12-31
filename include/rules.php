<?php

require_once __DIR__ . '/db.php';

// См правила описанные в https://docs.google.com/document/d/1MTOaNVRmx0eCT-TGYAUbGy2F2WAiOcpdf0NSDsVqjCk
define('DEFAULT_RULES', '0002000010000');

// 6.3. Ротация первой речи.
// rotation
define('RULES_ROTATION', 0);
define('RULES_ROTATION_FIRST', 0); // 6.3.1. Обсуждение каждого последующего игрового дня начинается со следующего после говорившего первым на предыдущем кругу игрока. Если следующий игрок убит, слово передается следующему игроку. Например, если в первый день стол открывал игрок номер 1, а закрывал номер 10, то во второй день стол откроет игрок номер 2 (следующий за номером 1), а закроет номер 1. Если же игрок номер 1 убит ночью, стол по прежнему открывает игрок номер 2 (следующий за номером 1), а закрывает номер 10.
define('RULES_ROTATION_LAST', 1); // 6.3.2. Обсуждение начинается с игрока под таким номером, чтобы последний поговоривший был следующим по сравнению с последним поговорившим прошлым днем. Если следующий по сравнению с последним поговорившим игрок убит, то стол должен закрывать следующий по номеру игрок. Например, если в первый день стол открывал игрок номер 1, а закрывал номер 10, то во второй день стол откроет игрок номер 2, а закроет номер 1 (следующий за номером 10). Если же игрок номер 1 убит ночью, стол открывает игрок номер 3, а закрывает номер 2 (следующий живой игрок после номера 10).

// 6.7. Оставления под протокол.
// onRecord
define('RULES_ON_RECORD', 1);
define('RULES_ON_RECORD_NONE', 0); // 6.7.1. В своих речах игроки могут оставлять цвета под протокол, но это не заносится судьей в запись игры и никак не влияет на дополнительные баллы.
define('RULES_ON_RECORD_NIGHT_KILLED', 1); // 6.7.2. Игроки убитые ночью могут оставлять цвета под протокол. Эти цвета записываются судьей и влияют на дополнительные баллы в соответствии с регламентом турнира.
define('RULES_ON_RECORD_KILLED', 2); // 6.7.3. Игроки убитые ночью, либо заголосованные днем могут оставлять цвета под протокол. Эти цвета записываются судьей и влияют на дополнительные баллы в соответствии с регламентом турнира.
define('RULES_ON_RECORD_EXCEPT_SPLIT', 3); // 6.7.4. Игроки в любой своей речи, кроме речей на попиле, могут оставлять цвета под протокол. Эти цвета записываются судьей и влияют на дополнительные баллы в соответствии с регламентом турнира.
define('RULES_ON_RECORD_ALL', 4); // 6.7.5. Игроки в любой своей речи могут оставлять цвета под протокол. Эти цвета записываются судьей и влияют на дополнительные баллы в соответствии с регламентом турнира.

// 6.8. Количество оставлений под протокол.
// onRecordCount
define('RULES_ON_RECORD_COUNT', 2);
define('RULES_ON_RECORD_0', 0); // 6.8.1. 0
define('RULES_ON_RECORD_1', 1); // 6.8.2. 1
define('RULES_ON_RECORD_2', 2); // 6.8.3. 2
define('RULES_ON_RECORD_3', 3); // 6.8.4. 3
define('RULES_ON_RECORD_4', 4); // 6.8.5. 4
define('RULES_ON_RECORD_5', 5); // 6.8.6. 5
define('RULES_ON_RECORD_6', 6); // 6.8.7. 6
define('RULES_ON_RECORD_7', 7); // 6.8.8. 7
define('RULES_ON_RECORD_8', 8); // 6.8.9. 8
define('RULES_ON_RECORD_9', 9); // 6.8.10. 9

// 7.5. Отзыв кандидатуры.
// withdraw
define('RULES_WITHDRAW', 3);
define('RULES_WITHDRAW_ALLOWED', 0); // 7.5.1. Во время своей минуты игрок имеет право отозвать выставленную им кандидатуру с голосования. Отзывание игрока с голосования производится фразой в настоящем времени: "Я отзываю игрока № ...". Отзывание игрока обязательно для выставления другого игрока. Пример:
										// Игрок: выставляю игрока 1.
										// Ведущий: принято.
										// Игрок: выставляю игрока 2, выставляю игрока 3, выставляю игрока 4.
										// [ведущий молчит]
										// Игрок: отзываю игрока номер 1.
										// Ведущий: отозвано.
										// Игрок: выставляю игрока 2, выставляю игрока 3, выставляю игрока 4.
										// Ведущий: номер 3 принят (это означает, что 2 уже выставлен другими игроками).
define('RULES_WITHDRAW_AUTO', 1); // 7.5.2. Во время своей минуты игрок имеет право отозвать выставленную им кандидатуру с голосования. Отзывание игрока с голосования производится фразой в настоящем времени: "Я отзываю игрока № ...". Отзывание игрока не обязательно для выставления другого игрока. Оно происходит автоматически при следующем выставлении. Пример:
										// Игрок: выставляю игрока 1.
										// Ведущий: принято.
										// Игрок: выставляю игрока 2, выставляю игрока 3, выставляю игрока 4.
										// Ведущий: номер 4 принят
										// Игрок: отзываю игрока номер 4.
										// Ведущий: отозвано.
										// Игрок: выставляю игрока 5, выставляю игрока 6, выставляю игрока 7.
										// Ведущий: номер 6 принят (это означает, что 7 уже выставлен другими игроками).
define('RULES_WITHDRAW_NO', 2); // 7.5.3. Отозвать кандидатуру невозможно. Пример:
										// Игрок: выставляю игрока 5, 6, 7.
										// Ведущий: номер 6 принят (это означает, что 5 уже выставлен другими игроками)
										// Игрок: выставляю игрока 2, 3, 4.
										// [ведущий молчит]
										// Игрок: отзываю игрока номер 6.
										// [ведущий молчит]
										// Игрок: выставляю игрока 3, 4, 5.
										// [ведущий молчит]
										// Выставлен игрок номер 6.
												
// 7.16. Голосование в первый день.
// firstVoting
define('RULES_FIRST_DAY_VOTING', 4);
define('RULES_FIRST_DAY_VOTING_STANDARD', 0); // 7.16.1. Если в первый игровой «день» выставлена только одна кандидатура, то голосование не проводится. В течение следующих «дней» голосуется любое количество выставленных на голосование игроков.
define('RULES_FIRST_DAY_VOTING_TO_TALK', 1); // 7.16.2. Игрок победивший в голосовании в первый игровой «день» не покидает игру, а просто получает доподнительные 30 секунд, чтобы поговорить.
define('RULES_FIRST_DAY_VOTING_NO_BREAKING', 2); // 7.16.2. Если в первый игровой «день» выставлена только одна кандидатура, то голосование не проводится. Если в голосовании побеждает только один игрок, то никто не умирает, слово ему не дается, наступает ночь. В случае попила, игроки разговаривают по 30 секунд. Поднять двоих можно.
define('RULES_FIRST_DAY_VOTING_BREAKING_TO_THEMSELF', 3); // 7.16.2. Если в первый игровой «день» выставлена только одна кандидатура, то голосование не проводится. Если в голосовании победил только один игрок, то он умирает только в том случае, если проголосовал за себя сам. В остальных случаях он просто получает дополнительные 30 секунд для речи.

// 7.18. Порядок повторного голосования после попила.
// splitOrder
define('RULES_SPLIT_ORDER', 5);
define('RULES_SPLIT_ORDER_SAME', 0); // 7.18.1. Повторное голосование после попила происходит в том же порядке, что и предыдущее.
define('RULES_SPLIT_ORDER_REVERSE', 1); // 7.18.2. Повторное голосование после попила происходит в обратном порядке по сравнению с предыдущим

// 7.21. Если в результате голосования, а затем и переголосования стол поделился между всеми игроками сидящими за столом.
// killAll
define('RULES_KILL_ALL', 6);
define('RULES_KILL_ALL_PROHIBITED', 0); // 7.21.1. Объявляется ночь. Никто не покидает стол.
define('RULES_KILL_ALL_DRAW', 1); // 7.21.2. Проводятся речи по 30 секунд и переголосование. Если результат повторяется, то проводится голосование на то, чтобы все игроки покинули стол. Если игроки голосуют за убийство всех, объявляется ничья.

// 7.22. Если за игровым столом находится четыре игрока и двое из них дважды набирают одинаковое число голосов.
// splitOnFour
define('RULES_SPLIT_ON_FOUR', 7);
define('RULES_SPLIT_ON_FOUR_ALLOWED', 0); // 7.22.1. Дальнейшее происходит как в любом другом раунде. Ведущий спрашивает кто за то, чтобы оба игрока покинули стол и если больше 50% игроков голосуют за это, оба игрока покидают стол.
define('RULES_SPLIT_ON_FOUR_PROHIBITED', 1); // 7.22.2. Дальнейшее голосование не проводится. Судья объявляет “Наступает ночь”. Никто не покидает игровой стол.

// 7.23. Если за столом девять игроков и трое из них дважды набирают одинаковое число голосов.
// splitOnNine
define('RULES_SPLIT_ON_NINE', 8);
define('RULES_SPLIT_ON_NINE_YES', 0); // 7.23.1. Если за столом девять игроков и трое из них дважды набирают одинаковое число голосов, дальше всё происходит как в любом другом раунде. Судья спрашивает кто за то, чтобы все три игрока покинули стол и если больше 4 игроков голосуют за это, оба игрока покидают стол.
define('RULES_SPLIT_ON_NINE_NO', 1); // 7.23.2. Если за столом девять игроков и трое из них дважды набирают одинаковое число голосов, дальнейшее голосование не проводится. Судья объявляет «Наступает ночь». Никто не покидает игровой стол.
define('RULES_SPLIT_ON_NINE_AFTER1', 2); // 7.23.2. Если за столом девять игроков и трое из них дважды набирают одинаковое число голосов, дальнейшее голосование не проводится. Судья объявляет «Наступает ночь». Никто не покидает игровой стол.

// 8.17. Игрок, "убитый" в первую ночь.
// legacy
define('RULES_LEGACY', 9);
define('RULES_LEGACY_FIRST', 0); // 8.17.1. Имеет право на завещание, или "лучший ход". В своей заключительной речи он может назвать трех игроков которых считает мафией. Если он угадает двух, или трех из них, то может получить дополнительные баллы в соответствии с регламентом турнира. Игрок не имеет права на “завещание” если по результату голосования в первый день игру покинули 2 или большее количество игроков. 
define('RULES_LEGACY_NO', 1); // 8.17.2. Может назвать тройку черных, однако она никак не будет зафиксирована Судьей и не повлияет на результаты турнира..

// 8.19. Когда игра заканчивается.
// gameEndSpeech
define('RULES_GAME_END_SPEECH', 10);
define('RULES_GAME_END_SPEECH_YES', 0); // 8.19.1. Судья предоставляет заключительную минуту последнему убитому игроку, кроме случаев когда последний убитый игрок был убит замечаниями или удален с игрового стола. И только по окончании заключительной минуты останавливает игру и объявляет победу той или иной команды. При этом замечания и удаления полученные во время последней речи не могут изменить результат игры. Если, например, последний оставшийся мафиози в момент заключительной речи убитого игрока получает четвертое замечание и удаляется из игры, Судья все равно объявляет победу мафии.
define('RULES_GAME_END_SPEECH_NO', 1); // 8.19.2. Судья не предоставляет последнего слова, а просто объявляет победу той или иной команды.

// 9.5. Наказание за три замечания.
// threeFouls
define('RULES_3_WARNINGS', 11);
define('RULES_3_WARNINGS_LOSE_SPEECH', 0); // 9.5.1. При получении трёх фолов игрок лишается права слова в свою ближайшую минуту. За исключением случаев, когда это его последнее слово или оправдательное слово при переголосовании. Игрок лишается слова один раз. В последующие дни игрок получит слово. У игрока получившего три фола остается право выставить кандидатуру на голосование. Когда за столом остается три или четыре игрока, игроку получившему три фола предоставляется «укороченная» речь 30 секунд. Если игрок с тремя фолами попал в ситуацию переголосования, то он имеет право слова. Он пропустит свое слово в последующие дни.
define('RULES_3_WARNINGS_SHORT_SPEECH', 1); // 9.5.2. При получении трёх фолов речь игрока в ближайшую минуту сокращается до 30 секунд. Речь сокращается один раз. В последующие дни игрок получит полные 30 секунд.
define('RULES_3_WARNINGS_NO', 2); // 9.5.3. При получении трёх фолов игрока не получает никакого наказания.

// 9.6. Наказание за три замечания.
// threeFouls
define('RULES_TECH_FAUL', 12);
define('RULES_TECH_FAUL_NO', 0); // 9.6.1. В этой версии правил технических фолов нет.
define('RULES_TECH_FAUL_YES', 1); // 9.6.2. За повышенные эмоции, споры с судьей, стуки по столу отвлекающие от игры, а также грубые высказывания в адрес других игроков, судья может дать технический фол. Технические фолы считаются отдельно от основных. За два технических фола игрок получает удаление.

define('RULE_OPTIONS_COUNT', 13);

define('RULE_OPTION_NAME', 0);
define('RULE_OPTION_VALUES', 1);
define('RULE_OPTION_PARAGRAPH', 2);
define('RULE_OPTION_ITEM', 3);
define('RULE_OPTION_IMPORTANCE', 4);

define('RULE_PARAGRAPH_NAME', 0);
define('RULE_PARAGRAPH_ITEMS', 1);

define('RULE_ITEM_INDEX', 0);
define('RULE_ITEM_NAME', 1);
define('RULE_ITEM_OPTIONS', 2);
define('RULE_ITEM_OPTIONS_SHORT', 3);

$_rules_options = array(
	array('rotation', array('first', 'last'), 5, 2, false),
	array('onRecord', array('no', 'night-killed', 'killed', 'except-split', 'all'), 5, 6, false),
	array('onRecordCount', array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9), 5, 7, false),
	array('withdraw', array('allowed', 'auto', 'no'), 6, 4, false),
	array('firstVoting', array('standard', 'to-talk', 'no-break', 'self-break'), 6, 15, false),
	array('splitOrder', array('same', 'reverse'), 6, 17, false),
	array('killAll', array('no', 'draw'), 6, 20, false),
	array('splitOnFour', array(true, false), 6, 21, true),
	array('splitOnNine', array('yes', 'no', 'after1'), 6, 22, true),
	array('legacy', array(true, false), 7, 16, false),
	array('gameEndSpeech', array(true, false), 7, 18, false),
	array('threeFouls', array('lose-speech', 'short-speech', 'no'), 8, 4, false),
	array('techFouls', array('no', 'yes'), 8, 5, false),
);

function is_valid_rules_code($rules_code)
{
	global $_rules_options;
	
	if (strlen($rules_code) != RULE_OPTIONS_COUNT)
	{
		return false;
	}
	
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		if (!is_numeric(substr($rules_code, $i, 1)))
		{
			return false;
		}
	}
	return true;
}

function upgrade_rules_code($rules_code)
{
	if (is_string($rules_code))
	{
		if (strlen($rules_code) == 29)
		{
			$rules_code = substr($rules_code, 0, 20);
			$rules_code = substr($rules_code, 0, 17) . substr($rules_code, 19);
			$rules_code = substr($rules_code, 0, 12) . substr($rules_code, 15);
			$rules_code = substr($rules_code, 0, 9) . substr($rules_code, 10);
			$rules_code = substr($rules_code, 0, 6) . substr($rules_code, 8);
			$rules_code = substr($rules_code, 0, 4) . substr($rules_code, 5);
			$rules_code = substr($rules_code, 0, 1) . substr($rules_code, 3);
			
			$rules_code = substr($rules_code, 0, 2) . '00' . substr($rules_code, 2);
			$rules_code = substr($rules_code, 0, 6) . '0' . substr($rules_code, 6);
			$rules_code = substr($rules_code, 0, 9) . '0' . substr($rules_code, 9);
		}
		
		if (strlen($rules_code) == 12)
		{
			$rules_code = substr($rules_code, 0, 11) . '00' . substr($rules_code, 11);
		}
		
		if (strlen($rules_code) == 14)
		{
			$rules_code = substr($rules_code, 0, -1);
		}
	}
	return $rules_code;
}

function check_rules_code($rules_code)
{
	$rules_code = upgrade_rules_code($rules_code);
	
	if (!is_valid_rules_code($rules_code))
	{
		throw new Exc(get_label('Invalid rules code "[0]".', $rules_code));
	}
	return $rules_code;
}


function rules_code_from_object($obj)
{
	global $_rules_options;
	
	if (isset($obj->code))
	{
		return $obj->id;
	}
	
	$rules = '';
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$rule = $_rules_options[$i];
		$name = $rule[RULE_OPTION_NAME];
		if (isset($obj->$name))
		{
			$options = $rule[RULE_OPTION_VALUES];
			for ($j = sizeof($options) - 1; $j > 0; --$j)
			{
				if ($options[$j] == $obj->$name)
				{
					break;
				}
			}
			$rules .= $j;
		}
	}
	return $rules;
}

function rules_code_to_object($rules_code, $detailed = false)
{
	global $_rules_options;
	
	$rules_code = check_rules_code($rules_code);
	$min_index = $detailed ? 0 : 1;
	$obj = new stdClass();
	$obj->code = $rules_code;
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$rule = $_rules_options[$i];
		$name = $rule[RULE_OPTION_NAME];
		$values = $rule[RULE_OPTION_VALUES];
		$c = (int)substr($rules_code, $i, 1);
		if ($c >= $min_index && $c < sizeof($values))
		{
			$obj->$name = $values[$c];
		}
	}
	return $obj;
}
	
function rules_code_from_json($json)
{
	return rules_code_from_object(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
}

function rules_code_to_json($rules_code)
{
	return json_encode(rules_code_to_object($rules_code));
}

function get_rule($rules_code, $rule_index)
{
	return (int)substr($rules_code, $rule_index, 1);
}
	
function set_rule($rules_code, $rule_index, $rule)
{
	if ($rule < 0 || $rule > 9)
	{
		$rule = 0;
	}
	return substr($rules_code, 0, $rule_index) . $rule . substr($rules_code, $rule_index + 1);
}

function is_rule_allowed($rules_filter, $rule_num, $rule_value)
{
	global $_rules_options;
	
	if ($rules_filter == null)
	{
		return true;
	}
	
	if (is_string($rules_filter))
	{
		$rules_filter = json_decode($rules_filter);
	}
	
	$rule = $_rules_options[$rule_num];
	$rule_name = $rule[RULE_OPTION_NAME];
	if (!isset($rules_filter->$rule_name))
	{
		return true;
	}
	
	$allowed_rules = $rules_filter->$rule_name;
	if (count($allowed_rules) <= 0)
	{
		return true;
	}
	
	$rule_value_name = $rule[RULE_OPTION_VALUES][$rule_value];
	if (is_array($allowed_rules))
	{
		foreach ($allowed_rules as $option)
		{
			if ($option == $rule_value_name)
			{
				return true;
			}
		}
	}
	else
	{
		return $allowed_rules == $rule_value_name;
	}
	return false;
}

// return the closest rules_code that matches the filter
function correct_rules($rules_code, $rules_filter)
{
	global $_rules_options;
	
	if (is_string($rules_filter))
	{
		$rules_filter = json_decode($rules_filter);
	}
	$rules_code = upgrade_rules_code($rules_code);
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$rule = $_rules_options[$i];
		if (!is_rule_allowed($rules_filter, $i, get_rule($rules_code, $i)))
		{
			$options = $rule[RULE_OPTION_VALUES];
			for ($j = 0; $j < count($options); ++$j)
			{
				if (is_rule_allowed($rules_filter, $i, $j))
				{
					$rules_code = set_rule($rules_code, $i, $j);
					break;
				}
			}
		}
	}
	return $rules_code;
}

function are_rules_allowed($rules_code, $rules_filter)
{
	global $_rules_options;
	
	if (is_string($rules_filter))
	{
		$rules_filter = json_decode($rules_filter);
	}
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$rule = $_rules_options[$i];
		if (!is_rule_allowed($rules_filter, $i, get_rule($rules_code, $i)))
		{
			return false;
		}
	}
	return true;
}

function are_rules_configurable($rules_filter)
{
	global $_rules_options;
	
	if (is_string($rules_filter))
	{
		$rules_filter = json_decode($rules_filter);
	}
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$rule = $_rules_options[$i];
		$rule_name = $rule[RULE_OPTION_NAME];
		if (!isset($rules_filter->$rule_name))
		{
			return true;
		}
		
		$allowed_rules = $rules_filter->$rule_name;
		if (is_array($allowed_rules) && count($allowed_rules) != 1)
		{
			return true;
		}
	}
	return false;
}

function api_rules_help($rules_param, $show_code_param = false)
{
	global $_rules_options;
	
	$rules = include '../../include/languages/en/rules.php';
	if ($show_code_param)
	{
		$rules_param->sub_param('code', 'Rules code in the form "' . DEFAULT_RULES . '". It uniquiely identifies the rules. It is guaranteed that games/events/tournaments/clubs with the same code are using the same rules.');
	}
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$rule_option = $_rules_options[$i];
		$rule_option_name = $rule_option[RULE_OPTION_NAME];
		$rule_options = $rule_option[RULE_OPTION_VALUES];
		$rule_option_paragraph = $rule_option[RULE_OPTION_PARAGRAPH];
		$rule_option_item = $rule_option[RULE_OPTION_ITEM];
		
		$rule = $rules[$rule_option_paragraph][RULE_PARAGRAPH_ITEMS][$rule_option_item];
		$rule_name = $rule[RULE_ITEM_NAME];
		$rule_descriptions = $rule[RULE_ITEM_OPTIONS_SHORT];
		
		$description = $rule_name . '. Values:<small><dl>';
		for ($j = 0; $j < count($rule_options); ++$j)
		{
			$option = $rule_options[$j];
			if (is_string($option))
			{
				$option = '"' . $option . '"';
			}
			else if (is_bool($option))
			{
				if ($option)
				{
					$option = 'true';
				}
				else
				{
					$option = 'false';
				}
			}
			
			if (!isset($rule_descriptions[$j]))
			{
				$description .= '<b><dt>' . $option . '</dt><dd>Not set in rules_options.php</dd></b>';
			}
			else
			{
				$description .= '<dt>' . $option . '</dt><dd>' . $rule_descriptions[$j] . '</dd>';
			}
		}
		$description .= '</dl></small>';
		$rules_param->sub_param($rule_option_name, $description, $rule_options[0]);
	}
}

function api_rules_filter_help($rules_param)
{
	global $_rules_options;
	
	$rules = include '../../include/languages/en/rules.php';
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$rule_option = $_rules_options[$i];
		$rule_option_name = $rule_option[RULE_OPTION_NAME];
		$rule_options = $rule_option[RULE_OPTION_VALUES];
		$rule_option_paragraph = $rule_option[RULE_OPTION_PARAGRAPH];
		$rule_option_item = $rule_option[RULE_OPTION_ITEM];
		
		$rule = $rules[$rule_option_paragraph][RULE_PARAGRAPH_ITEMS][$rule_option_item];
		$rule_name = $rule[RULE_ITEM_NAME];
		$rule_descriptions = $rule[RULE_ITEM_OPTIONS_SHORT];
		
		$description = $rule_name . '. An array of strings or a single string. When string is specified this rule is allowed. Possible values:<small><dl>';
		for ($j = 0; $j < count($rule_options); ++$j)
		{
			$option = $rule_options[$j];
			if (is_string($option))
			{
				$option = '"' . $option . '"';
			}
			else if (is_bool($option))
			{
				if ($option)
				{
					$option = 'true';
				}
				else
				{
					$option = 'false';
				}
			}
			
			if (!isset($rule_descriptions[$j]))
			{
				$description .= '<b><dt>' . $option . '</dt><dd>Not set in rules_options.php</dd></b>';
			}
			else
			{
				$description .= '<dt>' . $option . '</dt><dd>' . $rule_descriptions[$j] . '</dd>';
			}
		}
		$description .= '</dl></small>';
		$rules_param->sub_param($rule_option_name, $description, 'all options are allowed.');
	}
}

define('RULES_VIEW_FULL', 0);
define('RULES_VIEW_SHORT', 1);
define('RULES_VIEW_SHORTEST', 2);

function show_rules($rules_code, $view)
{
	global $_lang, $_rules_options;
	
	$rules = include 'include/languages/' . get_lang_code($_lang) . '/rules.php';
	echo '<big><table class="bordered light" width="100%">';
	if ($view <= RULES_VIEW_FULL)
	{
		for ($i = 0; $i < count($rules); ++$i)
		{
			$paragraph = $rules[$i];
			$items = $paragraph[RULE_PARAGRAPH_ITEMS];
			echo '<tr><td class="darker"><b>' . ($i + 1) . '. ' . $paragraph[RULE_PARAGRAPH_NAME] . '</b></td></tr><tr><td>';
			for ($j = 0; $j < count($items); ++$j)
			{
				$item = $items[$j];
				if (!is_string($item))
				{
					$index = $item[RULE_ITEM_INDEX];
					if (is_string($rules_code))
					{
						$rule_value = get_rule($rules_code, $index);
						$item = $item[RULE_ITEM_OPTIONS][$rule_value];
					}
					else
					{
						$options = $item[RULE_ITEM_OPTIONS];
						$count = count($options);
						$rule_value = 0;
						if ($rules_code != NULL)
						{
							$count = 0;
							for ($k = 0; $k < count($options); ++$k)
							{
								if (is_rule_allowed($rules_code, $index, $k))
								{
									++$count;
									$rule_value = $k;
								}
							}
						}
						
						$remainin_width = 100;
						$width = floor(100 / $count);
						if ($count == 1)
						{
							$item = $item[RULE_ITEM_OPTIONS][$rule_value];
						}
						else
						{
							$item = '<table class="bordered lighter" width="100%"><tr class="dark"><td colspan="' . count($options) . '">' . get_label('One of') . ':</td></tr><tr valign="top">';
							for ($k = 1; $k < count($options); ++$k)
							{
								if (is_rule_allowed($rules_code, $index, $k - 1))
								{
									$item .= '<td width="' . $width . '%">' . $options[$k-1] . '</td>';
									$remainin_width -= $width;
								}
							}
							if (is_rule_allowed($rules_code, $index, $k - 1))
							{
								$item .= '<td width="' . $remainin_width . '%">' . $options[$k-1] . '</td>';
							}
							$item .= '</tr></table>';
						}
					}
				}
				echo '<p>' . ($i + 1) . '.' . ($j + 1) . '. ' . $item . '</p>';
			}
			echo '</tr></td>';
		}
	}
	else
	{
		for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
		{
			$option = $_rules_options[$i];
			$paragraph_index = $option[RULE_OPTION_PARAGRAPH];
			$item_index = $option[RULE_OPTION_ITEM];
			$item = $rules[$paragraph_index][RULE_PARAGRAPH_ITEMS][$item_index];
			if (is_string($rules_code))
			{
				$rule_value = get_rule($rules_code, $i);
				if ($view >= RULES_VIEW_SHORTEST && $rule_value <= 0 && !$option[RULE_OPTION_IMPORTANCE])
				{
					continue;
				}
				
				echo '<tr><td class="dark"><b>' . ($paragraph_index + 1) . '.' . ($item_index + 1) . '. ' . $item[RULE_ITEM_NAME] . '</b></td></tr><tr><td><p>' . $item[RULE_ITEM_OPTIONS_SHORT][$rule_value] . '</p></td></tr>';
			}
			else
			{
				$options = $item[RULE_ITEM_OPTIONS_SHORT];
				$count = count($options);
				$rule_value = 0;
				if ($rules_code != NULL)
				{
					$count = 0;
					for ($k = 0; $k < count($options); ++$k)
					{
						if (is_rule_allowed($rules_code, $i, $k))
						{
							++$count;
							$rule_value = $k;
						}
					}
				}
				
				
				$remainin_width = 100;
				$width = floor(100 / $count);
				echo '<tr class="dark"><td><b>' . ($paragraph_index + 1) . '.' . ($item_index + 1) . '. ' . $item[RULE_ITEM_NAME] . '</b><p><table class="bordered light" width="100%"><tr valign="top" align="center">';
				for ($k = 1; $k < count($options); ++$k)
				{
					if (is_rule_allowed($rules_code, $i, $k - 1))
					{
						echo '<td width="' . $width . '%"><p>' . $options[$k-1] . '</p></td>';
						$remainin_width -= $width;
					}
				}
				if (is_rule_allowed($rules_code, $i, $k - 1))
				{
					echo '<td width="' . $remainin_width . '%"><p>' . $options[$k-1] . '</p></td>';
				}
				echo '</tr></table></p></td></tr>';
			}
		}
	}
	echo '</table></big>';
}

function get_available_rules($club_id = null, $club_name = null, $club_rules = null)
{
	$rules = array();
	if ($club_id != null)
	{
		if ($club_name != null && $club_rules != null)
		{
			$r = new stdClass();
			$r->id = 0;
			$r->name = $club_name;
			$r->rules = $club_rules;
			$rules[] = $r;
		}
	
		$query = new DbQuery('SELECT l.id, l.name, c.rules FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ? ORDER BY name', $club_id);
		while ($row = $query->next())
		{
			$r = new stdClass();
			list($r->id, $r->name, $r->rules) = $row;
			$r->id = -(int)$r->id;
			$rules[] = $r;
		}
		$query = new DbQuery('SELECT id, name, default_rules FROM leagues WHERE (flags & '.LEAGUE_FLAG_ELITE.') <> 0 AND id NOT IN (SELECT league_id FROM league_clubs WHERE club_id = ?) ORDER BY name', $club_id);
		while ($row = $query->next())
		{
			$r = new stdClass();
			list($r->id, $r->name, $r->rules) = $row;
			$r->id = -(int)$r->id;
			$rules[] = $r;
		}
		$query = new DbQuery('SELECT id, name, rules FROM club_rules WHERE club_id = ? ORDER BY name', $club_id);
		while ($row = $query->next())
		{
			$r = new stdClass();
			list($r->id, $r->name, $r->rules) = $row;
			$r->id = (int)$r->id;
			$rules[] = $r;
		}
	}
	else
	{
		$query = new DbQuery('SELECT id, name, default_rules FROM leagues WHERE (flags & '.LEAGUE_FLAG_ELITE.') <> 0 ORDER BY name');
		while ($row = $query->next())
		{
			$r = new stdClass();
			list($r->id, $r->name, $r->rules) = $row;
			$r->id = -(int)$r->id;
			$rules[] = $r;
		}
	}
	return $rules;
}

?>