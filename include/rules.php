<?php

require_once __DIR__ . '/db.php';

// См правила описанные в https://docs.google.com/document/d/1MTOaNVRmx0eCT-TGYAUbGy2F2WAiOcpdf0NSDsVqjCk

// 1.6. If the situation described in paragraph 1.5 happens with four players in the game
// no_kill_on_four
define('RULES_NO_KILL_ON_FOUR', 0);
define('RULES_NO_KILL_ON_FOUR_RED', 0); // 1.6.1.  «red» team wins. 
define('RULES_NO_KILL_ON_FOUR_DRAW', 1); // 1.6.2.  a draw is announced.

// 4.5. Ночная поза игрока. Запрещено петь, танцевать, говорить. Запрещено касаться руками маски. Запрещено касаться других игроков любыми частями тела.
// night_pose
define('RULES_NIGHT_POSE', 1);
define('RULES_NIGHT_POSE_FIGM', 0); //	4.5.1. Игроки сидят скрестив руки на груди, наклонив голову под углом примерно 45 градусов.
define('RULES_NIGHT_POSE_MAF_CLUB', 1); //	4.5.2. Игроки сидят положив обе руки на стол, наклонив голову под углом примерно 45 градусов.
define('RULES_NIGHT_POSE_FREE', 2); // 4.5.3. Игрокам разрешается сидеть как им удобно если они не нарушают ничего из сказанного в 4.5

// 5.4. Последовательность событий ночью.
// night_seq
define('RULES_NIGHT_SEQ', 2);
define('RULES_NIGHT_SEQ_FIGM', 0); // 5.4.1. Знакомство с Доном (5.2), затем Договоренка мафии (5.1), затем Знакомство с Шерифом (5.3).
define('RULES_NIGHT_SEQ_MAF_CLUB', 1); // 5.4.2. Договоренка мафии (5.1), затем Знакомство с Доном (5.2с), затем Знакомство с Шерифом (5.3).
define('RULES_NIGHT_SEQ_QUICK', 2); // 5.4.3. Договоренка мафии (5.1). Знакомство с Доном и Шерифом не производится.

// 6.3. Ротация первой речи.
// rotation
define('RULES_ROTATION', 3);
define('RULES_ROTATION_FIRST', 0); // 6.3.1. Обсуждение каждого последующего игрового дня начинается со следующего после говорившего первым на предыдущем кругу игрока. Если следующий игрок убит, слово передается следующему игроку. Например, если в первый день стол открывал игрок номер 1, а закрывал номер 10, то во второй день стол откроет игрок номер 2 (следующий за номером 1), а закроет номер 1. Если же игрок номер 1 убит ночью, стол по прежнему открывает игрок номер 2 (следующий за номером 1), а закрывает номер 10.
define('RULES_ROTATION_LAST', 1); // 6.3.2. Обсуждение начинается с игрока под таким номером, чтобы последний поговоривший был следующим по сравнению с последним поговорившим прошлым днем. Если следующий по сравнению с последним поговорившим игрок убит, то стол должен закрывать следующий по номеру игрок. Например, если в первый день стол открывал игрок номер 1, а закрывал номер 10, то во второй день стол откроет игрок номер 2, а закроет номер 1 (следующий за номером 10). Если же игрок номер 1 убит ночью, стол открывает игрок номер 3, а закрывает номер 2 (следующий живой игрок после номера 10).

// 7.3. Жесты при выставлении.
// nomination_thumb
define('RULES_NOMINATION_THUMB', 4);
define('RULES_NOMINATION_THUMB_OPTIONAL', 0); // 7.3.1. Выставление руки на стол допустимо, но не обязательно.
define('RULES_NOMINATION_THUMB_REQUIRED', 1); // 7.3.2. При выставлении игрок должен выставить руку с поднятым большим пальцем на стол.

// 7.5. Отзыв кандидатуры.
// withdraw
define('RULES_WITHDRAW', 5);
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
												
// 7.7. Получение информации о выставленных на голосование игроках.
// nomination_question
define('RULES_NOMINATION_QUESTION', 6);
define('RULES_NOMINATION_QUESTION_ALOWED', 0); // 7.7.2. Игрок имеет право спросить у Судьи о выставленных на голосование игроках один раз во время своей речи.
define('RULES_NOMINATION_QUESTION_PROHIBITED', 1); // 7.7.1. Игрок не имеет права получать у Судьи информацию о выставленных на голосование игроках.

// 7.10. Постановка рук до и во время голосования.
// voting_pose
define('RULES_VOTING', 7);
define('RULES_VOTING_FIGM', 0); // 7.10.1. Перед голосованием игроки должны убрать руки с игрового стола. Для того, чтобы проголосовать против того или иного игрока, игрок обязан после озвучивания кандидатуры незамедлительно поставить кулак с выставленным вверх большим пальцем на игровой стол. 
define('RULES_VOTING_MAF_CLUB', 1); // 7.10.2. Игрок перед голосованием должен поставить локоть одной руки на стол. После озвучивания кандидатуры игрок должен незамедлительно поставить кулак с выставленным вверх большим пальцем на игровой стол.

// 7.16. Голосование в первый день.
// first_voting
define('RULES_FIRST_DAY_VOTING', 8);
define('RULES_FIRST_DAY_VOTING_STANDARD', 0); // 7.16.1. Если в первый игровой «день» выставлена только одна кандидатура, то голосование не проводится. В течение следующих «дней» голосуется любое количество выставленных на голосование игроков.
define('RULES_FIRST_DAY_VOTING_TO_TALK', 1); // 7.16.2. Игрок победивший в голосовании в первый игровой «день» не покидает игру, а просто получает доподнительные 30 секунд, чтобы поговорить.

// 7.19. Если при повторном голосовании победило то же количество игроков, что и в предыдущем, но они победили другими составами голосующих, то:
// alt_split
define('RULES_ALT_SPLIT', 9);
define('RULES_ALT_SPLIT_CONTINUE', 0); // 7.19.1. Голосование считается повторившимся и ставится вопрос о том, чтобы все голосуемые игроки покинули стол.
define('RULES_ALT_SPLIT_REVOTE', 1); // 7.19.2. Считается, что результат голосования изменился и проводится еще одно переголосование. Однако если и в нем побеждает то же количество игроков но другими голосами, то ставится вопрос о том, чтобы все голосуемые игроки покинули стол.

// 7.20. Если в результате голосования, а затем и переголосования стол поделился между всеми игроками сидящими за столом.
// kill_all
define('RULES_KILL_ALL', 10);
define('RULES_KILL_ALL_PROHIBITED', 0); // 7.20.1. Объявляется ночь. Никто не покидает стол.
define('RULES_KILL_ALL_DRAW', 1); // 7.20.2. Проводятся речи по 30 секунд и переголосование. Если результат повторяется, то проводится голосование на то, чтобы все игроки покинули стол. Если игроки голосуют за убийство всех, объявляется ничья.

// 7.21. Если за игровым столом находится четыре игрока и они дважды набирают одинаковое число голосов.
// split_on_four
define('RULES_SPLIT_ON_FOUR', 11);
define('RULES_SPLIT_ON_FOUR_ALLOWED', 0); // 7.21.1. Дальнейшее происходит как в любом другом раунде. Ведущий спрашивает кто за то, чтобы оба игрока покинули стол и если больше 50% игроков голосуют за это, оба игрока покидают стол.
define('RULES_SPLIT_ON_FOUR_PROHIBITED', 1); // 7.21.2. Дальнейшее голосование не проводится. Судья объявляет “Наступает ночь”. Никто не покидает игровой стол.

// 8.3. Судья объявляет: "Мафия выходит на охоту".
// shooting
define('RULES_SHOOTING', 12);
define('RULES_SHOOTING_ALL_TEN', 0); // 8.3.1. Далее Судья каждую ночь объявляет номера игроков от 1 до 10.
define('RULES_SHOOTING_ALIVE_ONLY', 1); // 8.3.2. Далее Судья каждую ночь объявляет по очереди в порядке возрастания только номера игроков сидящих за столом.

// 8.6. Выставление убитым игроком.
// killed_nom
define('RULES_KILLED_NOMINATE', 13);
define('RULES_KILLED_NOMINATE_PROHIBITED', 0); // 8.6.1. Покинувший игру игрок имеет право на последнее слово продолжительностью в 1 минуту. Эта минута предоставляется ему в самом начале следующего дня. В эту минуту игрок не имеет права выставлять других игроков на голосование.
define('RULES_KILLED_NOMINATE_ALLOWED', 1); // 8.6.2. Покинувший игру игрок имеет право на последнее слово продолжительностью в 1 минуту. Эта минута предоставляется ему в самом начале следующего дня. В эту минуту игрок имеет право выставлять других игроков на голосование.

// 8.13. Ответ Судьи на номер показанный Шерифом.
// sheriff_response
define('RULES_SHERIFF_RESPONSE', 14);
define('RULES_SHERIFF_RESPONSE_NOD', 0); // 8.13.1. Судья кивает в том случае если игрок «черный». Судья мотает головой, если он «красный».
define('RULES_SHERIFF_RESPONSE_THUMB', 1); // 8.13.2. Судья показывает большой палец вниз, если игрок «черный». Судья показывает большой палец вверх, если игрок «красный».
define('RULES_SHERIFF_RESPONSE_BOTH', 2); // 8.13.3. Судья делает оба жеста упомянутых в предыдущих пунктах.
define('RULES_SHERIFF_RESPONSE_EITHER', 3); // 8.13.4. Судья делает любой из жестов упомянутых в предыдущих постах или оба жеста на свой выбор.

// 8.17. Игрок, "убитый" в первую ночь.
// best_guess
define('RULES_BEST_GUESS', 15);
define('RULES_BEST_GUESS_YES', 0); // 8.17.1. Имеет право на "лучший ход". В своей заключительной речи он может назвать трех игроков которых считает мафией. Если он угадает двух, или трех из них, то может получить дополнительные баллы в соответствии с регламентом турнира. Игрок не имеет права на “лучший ход” если по результату голосования в первый день игру покинули 2 или большее количество игроков. 
define('RULES_BEST_GUESS_NO', 1); // 8.17.2. Может назвать тройку черных, однако она никак не будет зафиксирована Судьей и не повлияет на результаты турнира..

// 8.19. Когда игра заканчивается.
// game_end_speech
define('RULES_GAME_END_SPEECH', 16);
define('RULES_GAME_END_SPEECH_YES', 0); // 8.19.1. Судья предоставляет заключительную минуту последнему убитому игроку, кроме случаев когда последний убитый игрок был убит замечаниями или удален с игрового стола. И только по окончании заключительной минуты останавливает игру и объявляет победу той или иной команды. При этом замечания и удаления полученные во время последней речи не могут изменить результат игры. Если, например, последний оставшийся мафиози в момент заключительной речи убитого игрока получает четвертое замечание и удаляется из игры, Судья все равно объявляет победу мафии.
define('RULES_GAME_END_SPEECH_NO', 1); // 8.19.2. Судья не предоставляет последнего слова, а просто объявляет победу той или иной команды.

// 8.20. По окончании игры..
// extra_points
define('RULES_EXTRA_POINTS', 17);
define('RULES_EXTRA_POINTS_FIGM', 0); // 8.20.1. Судья и его помощники назначают дополнительные баллы игрокам повлиявшим на исход игры. Баллы могут быть как положительными, так и отрицательными. Они назначаются в соответствии с регламентом турнира.
define('RULES_EXTRA_POINTS_MAF_CLUB', 1); // 8.20.2. Судья и его помощники могут назначить определенным игрокам звания “лучший игрок” и “лучший ход”. При этом “лучший игрок” может присуждаться только одному игроку. И это должен быть игрок победившей команды. “Лучший ход” может быть назначен любому количеству игроков из любых команд. Один и тот же игрок может не больше одного из этих званий. Звания приносят игрокам дополнительные баллы в соответствии с регламентом турнира.
define('RULES_EXTRA_POINTS_BEST_PLAYER', 2); // 8.20.3. Судья и его помощники могут назначить определенным игрокам звание “лучший игрок”. При этом “лучший игрок” может присуждаться только одному игроку. Он не должен быть игроком победившей команды. Звание приносит игрокам дополнительные баллы в соответствии с регламентом турнира.
define('RULES_EXTRA_POINTS_BEST_PLAYER_AND_MOVE', 3); // 8.20.4. Судья и его помощники могут назначить определенным игрокам звания “лучший игрок” и “лучший ход”. При этом и “лучший игрок”, как и “лучший ход” могут присуждаться только одному игроку. Эти игроки не обязаны быть игроками победившей команды. Их нельзя присуждать одному и тому же игроку. Эти звания приносят игрокам дополнительные баллы в соответствии с регламентом турнира.

// 9.6. Выставление при трех фолах.
// three_warnings_nomination
define('RULES_THREE_WARNINGS_NOMINATION', 18);
define('RULES_THREE_WARNINGS_NOMINATION_ALLOWED', 0); // 9.6.1. У игрока получившего три фола остается право выставить кандидатуру на голосование.
define('RULES_THREE_WARNINGS_NOMINATION_PROHIBITED', 1); // 9.6.2. Игрок получивший три фола не имеет права выставлять кандидатуру на голосование.

// 9.10. Если игрок покидает игровой стол, получая 4-й или дисквалифицирующий фол. Или за нарушение которое карается удалением.
// antimonster
define('RULES_ANTIMONSTER', 19);
define('RULES_ANTIMONSTER_NO_VOTING', 0); // 9.10.1. Ближайшее или текущее голосование не проводится, кроме случаев, когда этот игрок был убит ночью или является покинувшим игру по результату голосования. Если игрок покидает игровой стол, получая 4-й или дисквалифицирующий фол, во время голосования, после того, как был определён результат голосования, и он не является покинувшим игру по результату этого голосования, то следующее голосование не проводится.
define('RULES_ANTIMONSTER_NO', 1); // 9.10.2. Это никак не влияет на проведение последующих голосований.
define('RULES_ANTIMONSTER_NOMINATED', 2); // 9.10.3. Ближайшее или текущее голосование не проводится, кроме случаев, когда этот игрок был выставлен на голосование. Если игрок покидает игровой стол, получая 4-й или дисквалифицирующий фол, во время голосования, после того, как был определён результат голосования, и он не является покинувшим игру по результату этого голосования, то следующее голосование не проводится.
define('RULES_ANTIMONSTER_PARTICIPATION', 3); // 9.10.3. Ближайшее проводится, но если ушедший игрок был выставлен, то он участвует в голосовании наравне со всеми несмотря на то, что его нет за столом. Если он побеждает в голосовании, больше никто не покидает стол.

// 10.9. Постановка руки на стол до голосования.
// early_vote
define('RULES_EARLY_VOTE', 20);
define('RULES_EARLY_VOTE_WARNING', 0); // 10.9.1. Фол.
define('RULES_EARLY_VOTE_OK', 1); // 10.9.2. Не наказывается.

// 10.10. Жестикуляция ниже уровня игрового стола и за спинами игроков.
// undertable_sign
define('RULES_UNDERTABLE_SIGN', 21);
define('RULES_UNDERTABLE_SIGN_WARNING', 0); // 10.10.1. Фол.
define('RULES_UNDERTABLE_SIGN_OK', 1); // 10.10.2. Не наказывается.

// 10.11. Жестикуляция во время заключительной минуты игрока "убитого" в первую игровую "ночь", до совершения им "лучшего хода".
// first_kill_gesture
define('RULES_FIRST_KILL_GESTURE', 22);
define('RULES_FIRST_KILL_GESTURE_OK', 0); // 10.11.2. Не наказывается.
define('RULES_FIRST_KILL_GESTURE_WARNING', 1); // 10.11.1. Фол.

// 10.21. Просьба назвать или не называть кого-либо в "лучший ход", выраженная вслух или с помощью жестикуляции.
// best_guess_advice
define('RULES_BEST_GUESS_ADVICE', 23);
define('RULES_BEST_GUESS_ADVICE_KILL', 0); // 10.21.1. Удаление.
define('RULES_BEST_GUESS_ADVICE_WARN', 1); // 10.21.2. Фол.
define('RULES_BEST_GUESS_ADVICE_OK', 2); // 10.21.3. Не наказывается.

// 10.22. Апелляция к влиянию на "лучший ход" с целью доказательства игровой роли или объяснения игровых действий.
// best_guess_argument
define('RULES_BEST_GUESS_ARGUMENT', 24);
define('RULES_BEST_GUESS_ARGUMENT_KILL', 0); // 10.22.1. Удаление.
define('RULES_BEST_GUESS_ARGUMENT_WARN', 1); // 10.22.2. Фол.
define('RULES_BEST_GUESS_ARGUMENT_OK', 2); // 10.22.3. Не наказывается.

// 10.24. Сознательное подглядывание для получения информации, иные неигровые способы получения информации. Удаление.
// pry
define('RULES_PRY', 25);
define('RULES_PRY_LOSS', 0); // 10.24.1. Поражение всей команды.
define('RULES_PRY_KILL', 1); // 10.24.2. Удаление.

// 10.25. Получение подсказки из зрительного зала. Любая коммуникация со зрительным залом.
// audience_tip
define('RULES_AUDIENCE_TIP', 26);
define('RULES_AUDIENCE_TIP_LOSS', 0); // 10.25.1. Поражение всей команды.
define('RULES_AUDIENCE_TIP_KILL', 1); // 10.25.2. Удаление.

// 10.26. Клятва или пари в любой форме и их аналоги.
// oath
define('RULES_OATH', 27);
define('RULES_OATH_LOSS', 0); // 10.26.1. Поражение всей команды.
define('RULES_OATH_KILL', 1); // 10.26.2. Удаление.
define('RULES_OATH_WARNING', 2); // 10.26.3. Фол.

// 10.27. Шантаж, угрозы, подкуп.
// threat
define('RULES_THREAT', 28);
define('RULES_THREAT_LOSS', 0); // 10.27.1. Поражение всей команды.
define('RULES_THREAT_KILL', 1); // 10.27.2. Удаление.

define('RULE_OPTIONS_COUNT', 29);

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
	array('no_kill_on_four', array('red', 'draw'), 0, 5, false),
	array('night_pose', array('figm', 'maf-club', 'free'), 3, 4, false),
	array('night_seq', array('figm', 'maf-club', 'quick'), 4, 3, false),
	array('rotation', array('first', 'last'), 5, 2, false),
	array('nomination_thumb', array(false, true), 6, 2, false),
	array('withdraw', array('allowed', 'auto', 'no'), 6, 4, false),
	array('nomination_question', array(true, false), 6, 6, false),
	array('voting_pose', array('figm', 'maf-club'), 6, 9, false),
	array('first_voting', array('standard', 'to-talk'), 6, 15, false),
	array('alt_split', array('continue', 'revote'), 6, 18, false),
	array('kill_all', array('no', 'draw'), 6, 19, false),
	array('split_on_four', array(true, false), 6, 20, true),
	array('shooting', array('all', 'alive'), 7, 2, false),
	array('killed_nom', array(false, true), 7, 5, false),
	array('sheriff_response', array('nod', 'thumb', 'both', 'either'), 7, 12, false),
	array('best_guess', array(true, false), 7, 16, false),
	array('game_end_speech', array(true, false), 7, 18, false),
	array('extra_points', array('figm', 'maf-club', 'best-player', 'best-player-move'), 7, 19, false),
	array('three_warnings_nomination', array(true, false), 8, 5, false),
	array('antimonster', array('no-voting', 'no', 'nominated', 'participation'), 8, 9, false),
	array('early_vote', array('warn', 'ok'), 9, 8, false),
	array('undertable_sign', array('warn', 'ok'), 9, 9, false),
	array('first_kill_gesture', array('ok', 'warn'), 9, 10, false),
	array('best_guess_advice', array('ok', 'warn', 'kill'), 9, 20, false),
	array('best_guess_argument', array('ok', 'warn', 'kill'), 9, 21, false),
	array('pry', array('loss', 'kill'), 9, 23, false),
	array('audience_tip', array('loss', 'kill'), 9, 24, false),
	array('oath', array('loss', 'kill', 'warn'), 9, 25, false),
	array('threat', array('loss', 'kill'), 9, 26, false),
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

function default_rules_code()
{
	return str_pad('', RULE_OPTIONS_COUNT, '0');
}

function rules_code_from_object($obj)
{
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

// returns the list of the rules that violate rules filter
function get_rules_violation_list($rules_code, $filter)
{
	global $_rules_options;
	
	if (is_string($filter))
	{
		$filter = json_decode($filter, true, 512, JSON_THROW_ON_ERROR);
	}
	
	$delimiter = '';
	$violated_rules = '';
	for ($i = 0; $i < RULE_OPTIONS_COUNT; ++$i)
	{
		$rule = $_rules_options[$i];
		$RULE_OPTION_NAME = $rule[RULE_OPTION_NAME];
		if (isset($filter->$RULE_OPTION_NAME))
		{
			$rule_value = $rule[RULE_OPTION_VALUES][get_rule($rules_code, $i)];
			$options = $filter->$RULE_OPTION_NAME;
			$match = false;
			foreach ($options as $option)
			{
				if ($option == $rule_value)
				{
					$match = true;
					break;
				}
			}
			
			if (!$match)
			{
				$violated_rules .= $delimiter . ($rule[RULE_OPTION_PARAGRAPH] + 1) . '.' . ($rule[RULE_OPTION_ITEM] + 1) . '.';
				$delimiter = ', ';
			}
		}
	}
	return $violated_rules;
}

function api_rules_help($rules_param, $show_code_param = false)
{
	global $_rules_options;
	
	$rules = include '../../include/languages/en/rules.php';
	if ($show_code_param)
	{
		$rules_param->sub_param('code', 'Rules code in the form "' . default_rules_code() . '". It uniquiely identifies the rules. It is guaranteed that games/events/tournaments/clubs with the same code are using the same rules.');
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

define('RULES_VIEW_FULL', 0);
define('RULES_VIEW_SHORT', 1);
define('RULES_VIEW_SHORTEST', 2);

function show_rules($rules_code, $view)
{
	global $_lang_code, $_rules_options;
	
	$rules = include 'include/languages/' . $_lang_code . '/rules.php';
	echo '<big><table class="bordered light" width="100%">';
	if ($view <= RULES_VIEW_FULL)
	{
		for ($i = 0; $i < count($rules); ++$i)
		{
			$paragraph = $rules[$i];
			$items = $paragraph[RULE_PARAGRAPH_ITEMS];
			echo '<tr><td class="dark"><b>' . ($i + 1) . '. ' . $paragraph[RULE_PARAGRAPH_NAME] . '</b></td></tr><tr><td>';
			for ($j = 0; $j < count($items); ++$j)
			{
				$item = $items[$j];
				if (!is_string($item))
				{
					$index = $item[RULE_ITEM_INDEX];
					$rule_value = get_rule($rules_code, $index);
					$item = $item[RULE_ITEM_OPTIONS][$rule_value];
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
			$rule_value = get_rule($rules_code, $i);
			$option = $_rules_options[$i];
			if ($view >= RULES_VIEW_SHORTEST && $rule_value <= 0 && !$option[RULE_OPTION_IMPORTANCE])
			{
				continue;
			}
			
			$paragraph_index = $option[RULE_OPTION_PARAGRAPH];
			$item_index = $option[RULE_OPTION_ITEM];
			$item = $rules[$paragraph_index][RULE_PARAGRAPH_ITEMS][$item_index];
			echo '<tr><td class="dark"><b>' . ($paragraph_index + 1) . '.' . ($item_index + 1) . '. ' . $item[RULE_ITEM_NAME] . '</b></td></tr><tr><td><p>' . $item[RULE_ITEM_OPTIONS_SHORT][$rule_value] . '</p></td></tr>';
		}
	}
	echo '</table></big>';
}

//--------------------------------------------------------------------------------------------------------------------------------------------
// remove after the database will be converted to the new rules
require_once __DIR__ . '/game_rules.php';

function convert_old_rules($flags)
{
	$rules_code = default_rules_code();
	
	if ($flags & RULES_FLAG_NO_CRASH_4)
	{
		$rules_code = set_rule($rules_code, RULES_SPLIT_ON_FOUR, RULES_SPLIT_ON_FOUR_PROHIBITED);
	}
	
	if ($flags & RULES_FLAG_DAY1_NO_KILL)
	{
		$rules_code = set_rule($rules_code, RULES_FIRST_DAY_VOTING, RULES_FIRST_DAY_VOTING_TO_TALK);
	}
	
	if ($flags & RULES_FLAG_NIGHT_KILL_CAN_NOMINATE)
	{
		$rules_code = set_rule($rules_code, RULES_KILLED_NOMINATE, RULES_KILLED_NOMINATE_ALLOWED);
	}
	
	switch ($flags & RULES_MASK_VOTING_CANCEL_MASK)
	{
		case RULES_VOTING_CANCEL_NO:
			$rules_code = set_rule($rules_code, RULES_ANTIMONSTER, RULES_ANTIMONSTER_NO);
			break;
		case RULES_VOTING_CANCEL_BY_NOM:
			$rules_code = set_rule($rules_code, RULES_ANTIMONSTER, RULES_ANTIMONSTER_NOMINATED);
			break;
	}
	
	if ($flags & RULES_BEST_PLAYER)
	{
		if ($flags & RULES_BEST_MOVE)
		{
			$rules_code = set_rule($rules_code, RULES_EXTRA_POINTS, RULES_EXTRA_POINTS_BEST_PLAYER_AND_MOVE);
		}
		else
		{
			$rules_code = set_rule($rules_code, RULES_EXTRA_POINTS, RULES_EXTRA_POINTS_BEST_PLAYER);
		}
	}
	
	if ($flags & RULES_GUESS_MAFIA)
	{
		$rules_code = set_rule($rules_code, RULES_BEST_GUESS, RULES_BEST_GUESS_YES);
	}
	else
	{
		$rules_code = set_rule($rules_code, RULES_BEST_GUESS, RULES_BEST_GUESS_NO);
	}
	
	return $rules_code;
}

?>