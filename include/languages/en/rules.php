<?php

return array
(
	array
	(
		'Basic concepts.', // 1.
		array
		(
			'Ten people participate in each game. The Players are divided into two groups: Red and Black. The Red Players are law-abiding citizens (they draw the «Citizen» card); the «black» players are mafiosi (they draw the «Mafia» card). Seven red cards and three black cards are played. One of the seven red cards is different from the others - the «Sheriff» card. This Player becomes the leader of the Red Players. The Black players also have a leader, the one who draws the «Don» card.', // 1.1.
			'The game has two parts: «day» and «night».', // 1.2.
			'The Moderator observes the game and orchestrates its progress. Side Referees may assist in the game.', // 1.3.
			'The Red team wins at the moment when all the black team are eliminated from the game. The «Black» wins when the number of «Black» players is greater or equal than the «Red» ones. The player is assumed to be «killed» at the moment the vote is over and the moment after the «black» team completed their shooting.', // 1.4.
			'If during 3 following «nights» and «days» starting with «night», no player leaves the game, a draw is announced.', // 1.5.
			array // 1.6.
			(
				RULES_NO_KILL_ON_FOUR,
				'No kills three rounds with four players',
				array
				(
					'If four players are in the game and nobody leaves the game 3 cycles in a row then the «red» team wins.',
					'If four players are in the game and nobody leaves the game 3 cycles in a row it does not make any difference - a draw is still announced.',
				),
				array
				(
					'Red team wins.',
					'Draw.',
				),
			),
		),
	),
	
	array
	(
		'Environment, inventory and official language of the game.', // 2.
		array
		(
			'The game table must able to accommodate 10 players comfortably. The Moderator has the right to sit at the gaming table or near the gaming table in order to be able to see and hear everything that happens in the game.', // 2.1.
			'Each seat must have a plate with a number and a game mask for the player.', // 2.2.
			'The space must be equipped with a sound system for playing during the «night».', // 2.3.
			'The official languages ​​of the game are Russian and English. National tournaments with no foreign participants can be held in a national language.', // 2.4.
		),
	),

	array
	(
		'Players.', // 3.
		array
		(
			'Players stay at the table as long as they participate in the game process.', // 3.1.
			'The player has the right to use any equipment other than that which creates a danger to other players, offends other players or by any means gives an unfair advantage over other players.', // 3.2.
		),
	),

	array
	(
		'Beginning of the game.', // 4.
		array
		(
			'Ten players are invited to the game table.', // 4.1.
			'Seat numbers at the gaming table are played out in the order determined by the organizers of the tournament, or indicated in advance of the tournament.', // 4.2.
			'The Moderator announces the beginning of the game with the phrase «night is falling»/all players put on your masks.', // 4.3.
			'The players take turns with their eyes closed, choose a role card, take off the mask, get acquainted with the game role, and put the mask back on.', // 4.4.
			array // 4.5.
			(
				RULES_NIGHT_POSE,
				'Player’s night pose',
				array
				(
					'It is forbidden to sing, dance, talk. It is forbidden to touch the mask. It is forbidden to touch other players with any parts of the body. Players sit with their arms folded. Their heads bowed at an angle of about 45 degrees. When the music is too quiet and it cannot be made louder, players sit with their hands over their ears.',
					'It is forbidden to sing, dance, talk. It is forbidden to touch the mask. It is forbidden to touch other players with any parts of the body. Players sit with both hands on the table, head bent at an angle of about 45 degrees.',
					'It is forbidden to sing, dance, talk. It is forbidden to touch the mask. It is forbidden to touch other players with any parts of the body. Other than that players can sit as they want.',
				),
				array
				(
					'Arms folded.',
					'Hands on the table.',
					'Free.',
				),
			),
		),
	),

	array
	(
		'Starting «night».', // 5.
		array
		(
			'Mafia arrangement. The Moderator announces: «Mafia, wake up». Players who have received «black» cards take off their masks. Now they see each other. This is the only night when «black» players open their eyes together. The Don denotes themself, and assigns the order of «shooting» for the next «night». The black team has exactly 1 minute for this. At the end of the minute the Moderator announces: «The mafia is falling asleep.» After these words, «black» players put on masks back. The Moderator is advised to wait a full minute even if the mafia has agreed faster. So that the players could not draw conclusions about the composition of the mafia by how long the contract lasted.', // 5.1.
			'Meet the Don. The Moderator announces: «Don, wake up». The Don takes off his mask. The Don has 10 seconds to take a look at the players. The Don has the right not to take off the mask, but simply to designate himself with a gesture.', // 5.2.
			'Meet the Sheriff. The Moderator announces: «Sheriff, wake up». The Sheriff takes off the mask. The Sheriff has 10 seconds to take a look at the players. The Sheriff has the right not to remove the mask, but simply to designate himself with a gesture.', // 5.3.
			array // 5.4.
			(
				RULES_NIGHT_SEQ,
				'First night sequence of events',
				array
				(
					'The first night events happen in the next order: Meet the Don (5.2), then the Mafia arrangement (5.1), then Meet the Sheriff (5.3).',
					'The first night events happen in the next order: Mafia arrangement (5.1), then Meet the Don (5.2), then Meet the Sheriff (5.3).',
					'The first night only Mafia arrangement (5.1) happens. Meetings with the Don (5.2) and the Sheriff (5.3) are skipped.',
				),
				array
				(
					'Meet the don, then mafia arrangement, then meet the sheriff.',
					'Mafia arrangement, then meet the don, then meet the sheriff.',
					'Mafia arrangement. No meetings with the don and the sheriff.',
				),
			),
			'Once all of the sequences are finished, the Moderator announces: «Good morning. Everybody, wake up.»', // 5.5.
		),
	),

	array
	(
		'Game «day».', // 6.
		array
		(
			'Players take off their masks. During this phase of the day each player is given 1 min to speak.', // 6.1.
			'Players take turns in the order of seating at the gaming table. Discussion of the first day starts with player number 1.', // 6.2.
			array // 6.3.
			(
				RULES_ROTATION,
				'Rotation of the first speech',
				array
				(
					'The discussion of each subsequent «day» begins with the next player following the one who spoke first on the previous round. If the next player is killed, the word is passed to the next player. For example, if on the first day the player number 1 spoke first and number 10 spoke last. Then on the second day the player number 2 speaks first and number 1 speaks last. If the player number 1 is killed at night, number 2 still speaks first, and number 10 speaks last.',
					'The discussion begins with a player with this number so that the last person to speak is next compared to the last person who talked last day. If the next player is killed in comparison with the last speaker, then the next player speaks lasts. For example, if in the first day the player number 1 spoke first and number 10 spoke last. Then on the second day the player number 2 speaks first and number 1 speaks last. If the player number 1 is killed at night, then the player number 3 speaks first, and number 2 speaks last.',
				),
				array
				(
					'First rotates.',
					'Last rotates.',
				),
			),
			'The player should refer to other players by their game nickname or player\'s seat number.', // 6.4.
			'The player has the right to appeal to the Moderator with the phrase «Moderator».', // 6.5.
			'Players end their performance with the word «Pass» or «Thank you».', // 6.6.
		),
	),

	array
	(
		'Voting.', // 7.
		array
		(
			'Voting takes place after the «day» discussion. Voting is conducted only among the players nominated during the «day» discussion.', // 7.1.
			'A player can nominate during their speech. In order to do it they have to say: «I nominate player #...».', // 7.2.
			array // 7.3.
			(
				RULES_NOMINATION_THUMB,
				'Nomination gestures',
				array
				(
					'When player is nominating they can place a hand on the table with a raised thumb. However, this is not necessary.',
					'When player is nominating they must put their fist with a raised thumb on the table.',
				),
				array
				(
					'No fist.',
					'Fist on the table.',
				),
			),
			'If this nomination is accepted the Moderator replies: «Accepted» or «Number … accepted». The second form is obligatory when nominating several candidates one after another. So that the players will have complete clarity about who is nominated.', // 7.4.
			array // 7.5.
			(
				RULES_WITHDRAW,
				'Rescinding nomination',
				array
				(
					'A player can rescind their own nomination during their speech. They have to say: «I withdraw player #...», or «I rescind player #...», or «I recall player #...». On successful rescinding the Moderator replies: «withdrawn», or «rescinded», or «recalled». Rescinding prior nomination is required for nominating another player. Example:<blockquote><i>Player:</i> I nominate player 1.<br><i>Moderator:</i> accepted.<br><i>Player:</i> I nominate player 2, I nominate 3, I nominate 4.<br><i>(The Moderator does not reply).</i><br><i>Player:</i> I withdraw number 1.<br><i>Moderator:</i> withdrawn.<br><i>Player:</i> I nominate player 2, I nominate player 3, I nominate player 4.<br><i>Moderator:</i> number 3 is accepted.<br><i>(This means that 2 is already nominated by other players).</i></blockquote>',
					'A player can rescind their own nomination during their speech. They have to say: «I withdraw player #...», or «I rescind player #...», or «I recall player #...». On successful rescinding the Moderator replies: «withdrawn», or «rescinded», or «recalled». Rescinding prior nomination is not required for nominating another player. Previous nomination is automatically replaced by the next one. Example:<blockquote><i>Player:</i> I nominate player 1.<br><i>Moderator:</i> accepted.<br><i>Player:</i> I nominate player 2, I nominate player 3, I nominate 4.<br><i>Moderator:</i> number 4 is accepted<br><i>Player:</i> I rescind 4<br><i>Moderator:</i> 4 is rescinded.<br><i>Player:</i> I nominate player 5, I nominate player 6, I nominate player 7.<br><i>Moderator:</i> number 6 is accepted<br><i>(This means that 7 is already nominated by other players).</i></blockquote>',
					'It is impossible to withdraw the nomination. Example:<blockquote><i>Player:</i> I nominate player 5, 6, 7.<br><i>Moderator:</i> number 6 is accepted.<br><i>(This means that 5 is already nominated by other players).</i><br><i>Player:</i> I nominate player 2, 3, 4.<br><i>(The Moderator does not reply).</i><br><i>Player:</i> I recall player number 6.<br><i>(The Moderator does not reply).</i><br>Player: I nominate player 3, 4, 5.<br><i>(The Moderator does not reply).</i><br>Player number 6 is nominated at the end of the speech.</blockquote>',
				),
				array
				(
					'Allowed. Explicit.',
					'Allowed. Implicit.',
					'Not allowed.',
				),
			),
			'The player has the right to nominate only one or zero candidates in each game «day».', // 7.6.
			array // 7.7.
			(
				RULES_NOMINATION_QUESTION,
				'Asking Moderator about nominations',
				array
				(
					'Players have the right to ask the Moderator about the list of nominated players once during their speech.',
					'Players are not entitled to receive information about nominated players.',
				),
				array
				(
					'Allowed once in a speech.',
					'Not allowed.',
				),
			),
			'Nominated players are voted on in the order they were nominated during the «day» discussion.', // 7.8.
			'The Moderator must announce the nominations in the right order before voting.Then, the Moderator announces «Who wants to kill/lynch player number #...?» for each nominee. Players can vote for the announced candidate until the Moderator says «Stop» or «Thank you». The time to vote is 2 seconds, the vote will only count if it is placed in time, late votes will not be considered.', // 7.9.
			array // 7.10.
			(
				RULES_VOTING,
				'Hands positioning during voting',
				array
				(
					'Players must remove their hands from the gaming table before voting. Players put the fist with the thumb up on the gaming table in order to vote.',
					'Players put the elbow of one hand on the table before voting. Players put this hand’s fist with the thumb up on the gaming table in order to vote.',
				),
				array
				(
					'No hands on the table.',
					'Elbow on the table.',
				),
			),
			'Players must keep their fists on the surface of the table until the Moderator announces the number of voters.', // 7.11.
			'A player can vote only once at the time of voting.', // 7.12.
			'If a player has not voted, their vote goes against the player who was nominated last.', // 7.13.
			'If a player withdrew his hand during the voting, their vote is still counted.', // 7.14.
			'The player with the most votes leaves the game.', // 7.15.
			array // 7.16.
			(
				RULES_FIRST_DAY_VOTING,
				'First «day» voting',
				array
				(
					'The first game «day» has an exception to other days, if there is only one candidate then a vote is not held, and no one is eliminated. Over the next «days» any number of players put to vote will be voted on.',
					'The first game «day» has an exception to other days, the player with the most votes does not leave the game. They get another 30 sec speech instead.',
				),
				array
				(
					'One candidate is not voted.',
					'Voting as usual but the winner is not killed. They speack 30 sec instead.',
				),
			),
			'If two or more players gain the same number of votes, these players receive an additional 30 seconds for their defence speeches. Players speak in the order of voting. After an additional 30 seconds, a second vote only occurs between these players in the same order. If the vote is repeated, the Moderator raises the question: «Who wants all the players to leave the game?» If the majority votes for a knockout, both players leave the game. If the majority of players vote against, or the votes are equally divided, then the players remain in the game.', // 7.17.
			'If during the repeated vote two or more players again scored an equal number of votes, but the number of winners changed from the previous vote, the next re-ballot is held. Until one of the players wins the vote, or the same result will be obtained twice in a row.', // 7.18.
			array // 7.19.
			(
				RULES_ALT_SPLIT,
				'Repeated split with different composition',
				array
				(
					'If the same number of players won during the second voting, but they won with other compositions of voters, then voting is considered to be repeated and the question is posed that all voting players leave the table.',
					'If the same number of players won during the second voting, but they won with other compositions of voters, then it is considered that the result of the vote has changed and another re-vote is held. However, if the same number of players wins in it but with different votes, then the question is raised that all players being voted for leave the table.',
				),
				array
				(
					'As if it is the same split.',
					'Arranging third voting.',
				),
			),
			array // 7.20.
			(
				RULES_KILL_ALL,
				'Lynching all players',
				array
				(
					'If as a result of voting and then re-voting the table is shared between all the players in the game, the night is announced. No one leaves the table.',
					'If as a result of voting and then re-voting the table is shared between all the players in the game, the Moderator raises the question of all players leaving the table. If the majority votes for, the game stops and a draw is announced.',
				),
				array
				(
					'Not allowed. No one leaves the table.',
					'Draw.',
				),
			),
			array // 7.21.
			(
				RULES_SPLIT_ON_FOUR,
				'Lynching two on four',
				array
				(
					'If there are four players in the game and two of them get the same number of votes twice, then the split is treated like all other splits.. The moderator asks if both players should leave the game/ If more than two players vote for it, both players leave the table.',
					'If there are four players in the game and two of them get the same number of votes twice  further voting is not conducted. The Moderator announces «Night is coming». No one leaves the game.',
				),
				array
				(
					'Allowed.',
					'Not allowed.',
				),
			),
			'The role of the killed player is not revealed. The killed player or players have the right to the last one minute speech.', // 7.22.
		),
	),

	array
	(
		'The second and subsequent «nights».', // 8.
		array
		(
			'At the end of the first game «day» the Moderator announces: «Night is falling». All players immediately put on their masks.', // 8.1.
			'During this and the next «nights» the mafia has the opportunity to «shoot».', // 8.2.
			array // 8.3.
			(
				RULES_SHOOTING,
				'Shooting',
				array
				(
					'The Moderator announces: «Mafia is shooting». Then the Moderator announces the players\' numbers from 1 to 10 every night.',
					'The Moderator announces: «Mafia is shooting». Next, the Moderator announces in ascending order only the numbers of the players who are still in the game.',
				),
				array
				(
					'Moderator announces from 1 to 10.',
					'Moderator announces only the numbers of the players in the game.',
				),
			),
			'Players of the «black» team «shoot» with their eyes closed by simulating pulling a trigger with the fingers of a highly raised hand.', // 8.4.
			'If the players of the «black» team «shoot» at the same player, then this player leaves the game after the «night» expires. The role of the «killed» player is not revealed.', // 8.5.
			array // 8.6.
			(
				RULES_KILLED_NOMINATE,
				'Nomination by killed player ',
				array
				(
					'The «killed» player has the right to the last one minute speech. This minute is given to them at the very beginning of the next «day». They can not nominate other players during this speech.',
					'The «killed» player has the right to the last one minute speech. This minute is given to them at the very beginning of the next «day». They can nominate other players during this speech.',
				),
				array
				(
					'Not allowed.',
					'Allowed.',
				),
			),
			'If one of the members of the «black» team «shoots» another player, «shoots» more than once or does not «shoot», then mafia have «missed». In this case nobody leaves the game. At the beginning of  the next «day» Moderator announces: «The mafia has missed».', // 8.7.
			'The Moderator announces: «Don, wake up and check for the Sheriff». Don takes of the mask and shows a number of a player to the Moderator.', // 8.8.
			'To make sure that the number is understood correctly, the Moderator can show the same number back to the Don. If the number is correct, the Don nods. If the number is incorrect, the Don exchanges the number again, until both the Moderator and Don are sure that they mean the same number, then the exchange of numbers takes place again until both the Moderator and Don are sure that they mean the same number.', // 8.9.
			'The Moderator nods or shakes their head, depending if the checked player is the Sheriff or not. Then the Moderator announces: «Don, close your eyes». Don puts on the mask.', // 8.10.
			'The Moderator announces: «Sheriff, wake up and check for mafia». The Sheriff removes the mask and shows Moderator the number of the player they want to check.', // 8.11.
			'To make sure that the number is understood correctly, the Moderator can show the same number back to the Sheriff. If the number is correct, the Sheriff nods. If the number is incorrect, the Sheriff exchanges the number again, until both the Moderator and Don are sure that they mean the same number.', // 8.12.
			array // 8.13.
			(
				RULES_SHERIFF_RESPONSE,
				'Answer to the Sheriff',
				array
				(
					'The Moderator nods if the player is «black». The Moderator shakes his head if he is «red».',
					'The Moderator shows thumb down if the player is «black». The Moderator shows thumb up if the player is «red».',
					'The Moderator nods and shows thumb down if the player is «black». The Moderator shakes his head and shows thumb up if he is «red».',
					'The Moderator nods or shows thumb down if the player is «black». The Moderator shakes his head or shows thumb up if he is «red».',
				),
				array
				(
					'Nod.',
					'Thumb.',
					'Nod and thumb.',
					'Nod or thumb.',
				),
			),
			'The Moderator announces: «Sheriff close your eyes». Sheriff puts on the mask.', // 8.14.
			'Don and Sheriff can make one check every «night».', // 8.15.
			'On a game night, players must behave as it is described described in paragraph 4.5.', // 8.16.
			array // 8.17.
			(
				RULES_LEGACY,
				'«Legacy»',
				array
				(
					'The player «killed» the first «night» has the right to leave a «legacy». In their last speech, they can make a guess who the three mafia are. If they guess two or three of them right, they can get additional tournament points according to the rules of the tournament they are playing. A player is not entitled to a «legacy» if two or more players have left the game on the first day.',
					'The player «killed» the first «night» may guess three «black» players but it is not official. It will not affect the tournament results.',
					'The player «killed» in any «night» has the right to leave a «legacy». In their last speech, they can make a guess who the three mafia are. If they guess two or three of them right, they can get additional tournament points according to the rules of the tournament they are playing. A player is not entitled to a «legacy» if two or more players have left the game on the first day.',
				),
				array
				(
					'First night.',
					'No.',
					'Any night.',
				),
			),
			'In the following «days» and «nights» the course of the game does not change. The phases of the game repeat until one of the teams win.', // 8.18.
			array // 8.19.
			(
				RULES_GAME_END_SPEECH,
				'Final speech at the end',
				array
				(
					'The Moderator gives the final speech to the last killed player. Unless the last killed player was killed by warnings or was removed from the game by the Moderator. The Moderator announces the end of the game after the final speech. At the same time, warnings and penalties during the last speech cannot change the result of the game. If, for example, the last remaining «black» player gets the fourth warning during this speech, the Moderator still declares victory of the mafia.',
					'The Moderator announces the end of the game immediately without any additional speeches.',
				),
				array
				(
					'Yes.',
					'No.',
				),
			),
			array // 8.20.
			(
				RULES_EXTRA_POINTS,
				'Extra points',
				array
				(
					'The Moderator and their assistants assign additional points to players who have influenced the outcome of the game. Points can be both positive and negative. They are awarded in accordance with the tournament rules.',
					'The Moderator and their assistants may reward certain players the titles «best player» and «best move». In this case, the «best player» can be awarded only to one player. And this must be a player of the winning team. The «best move» can be assigned to any number of players from any teams. The same player can not get more than one of these titles. These titles give players additional points in accordance with the rules of the tournament.',
					'The Moderator and their assistants may reward certain player the title «best player». This title can be awarded to only one player. This player does not have to be a member of the winning team. This title gives the player additional points in accordance with the rules of the tournament.',
					'The Moderator and their assistants may reward certain players the titles «best player» and «best move». Each title can be awarded to only one player. This player does not have to be a member of the winning team. These titles give players additional points in accordance with the rules of the tournament.',
				),
				array
				(
					'Manual. Using FIGM rules.',
					'One «best player» from the winnig team; many «best moves» from any team.',
					'One «best player».',
					'One «best player» and one «best move».',
				),
			),
		),
	),

	array
	(
		'Disciplinary regulations.', // 9.
		array
		(
			'The Moderator is responsible for detecting violations of the rules.', // 9.1.
			'Players who break the rules are punished by the Moderator in accordance with the disciplinary regulations specified in paragraph 10.', // 9.2.
			'There are the following penalties. In order of increasing severity.<ul><li>Verbal warning.</li><li>Warning.</li><li>Disqualification from the game.</li><li>Team defeat.</li></ul>', // 9.3.
			'In addition to penalties 9.3.3 and 9.3.4, tournament penalties can also be applied. This is either the removal of tournament points, or disqualification from the tournament (red card), or tournament warning (yellow card). Yellow and red cards can also be accompanied by the removal of tournament points. This is determined by the tournament regulations. See the document League Rules for Tournaments for more details.', // 9.4.
			'A player with three warnings is missing the speech at their closest minute. Except for the case when it is their last word or justifying word when re-voting. A player loses the speech once. In the following days, the player will have the speech as usual.', // 9.5.
			array // 9.6.
			(
				RULES_THREE_WARNINGS_NOMINATION,
				'Nominating with three warnings',
				array
				(
					'A player who misses the speech still has the right to nominate a candidate.',
					'A player who misses the speech still has no right to nominate a candidate.',
				),
				array
				(
					'Allowed.',
					'Not allowed.',
				),
			),
			'Short speech. When there are three or four players left in the game, the player who receives three fouls is given a «shortened» speech for 30 seconds.', // 9.7.
			'If a player with three warnings gets into a re-vote situation, they have the right to speak. They will miss their speech in the following day instead.', // 9.8.
			'When receiving a fourth warning or disqualifying foul, the player immediately leaves the game without the last word.', // 9.9.
			array // 9.10.
			(
				RULES_ANTIMONSTER,
				'Anti-monster: when a player is killed by warnings',
				array
				(
					'If a player leaves the game by getting a fourth warning. or gets a disqualification from the game, the closest or current vote is canceled. Except when this player was killed at night or is already out of the game by the result of the vote. If a player leaves the gaming table, receiving a fourth or disqualifying foul, during a vote, after the result of the vote has been determined, and he is not leaving the game according to the result of this vote, then the next vote is canceled.',
					'If a player leaves the game by getting a fourth warning. Or gets a disqualification from the game, the subsequent voting is not canceled.',
					'If a player leaves the game by getting a fourth warning. or gets a disqualification from the game, the closest or current voting is canceled only if the player is nominated for this voting.',
					'If a player leaves the game by getting a fourth warning. or gets a disqualification from the game, the subsequent voting is not canceled. The player participates in the voting on a par with everyone despite the fact that they are not in the game. If they win the vote, no one else leaves the game.',
				),
				array
				(
					'Voting is canceled.',
					'Voting is not canceled. No anti-monster rule.',
					'Voting is canceled only if the monster is nominated.',
					'Voting is not canceled. The dead monster participates as a nominant.',
				),
			),
			'When a player gets a fourth warning or disqualifying foul during the last speech of another player, the result of the game is determined by the result of the vote. The removed player receives a penalty in accordance with the tournament regulations.', // 9.11.
		),
	),

	array
	(
		'Violations and penalties.', // 10.
		array
		(
			'A player speaks while it is not their minute to speak, this includes interjections and/or whispers. Warning.', // 10.1.
			'Touching a nearby player (hand or foot) during the day. Warning.', // 10.2.
			'Excessive gestures, knocking on the table, or distracting other players. Warning.', // 10.3.
			'Any gestures and calls during the voting phase. Warning.', // 10.4.
			'Not voting when the Moderator requests to put the remaining votes against the last candidate. Warning.', // 10.5.
			'Pulling the hand after touching the table when voting before the Moderator says «Thank you». Warning.', // 10.6.
			'Voting against more than one candidate. Warning.', // 10.7.
			'Night gesticulation; delay in putting on a mask when «night» is declared; disturbances of the night position described in clause 4.1.5. Warning.', // 10.8.
			array // 10.9.
			(
				RULES_EARLY_VOTE,
				'Fist on the table before voting',
				array
				(
					'Putting a fist on the table before the vote is announced. Warning.',
					'Putting a fist on the table before the vote is announced. No penalty.',
				),
				array
				(
					'Warning.',
					'No penalty.',
				),
			),
			array // 10.10.
			(
				RULES_UNDERTABLE_SIGN,
				'Gestures under the table',
				array
				(
					'Gestures below the level of the gaming table and behind the backs of the players. Warning.',
					'Gestures below the level of the gaming table and behind the backs of the players. No penalty.',
				),
				array
				(
					'Warning.',
					'No penalty.',
				),
			),
			array // 10.11.
			(
				RULES_FIRST_KILL_GESTURE,
				'Gestures on first killed player speech',
				array
				(
					'Gestures during the final minute of the player «killed» in the first «night», before making the «legacy». Warning.',
					'Gestures during the final minute of the player «killed» in the first «night», before making the «legacy». No penalty.',
				),
				array
				(
					'Warning.',
					'No penalty.',
				),
			),
			'Appeal to religious or other ethical (and ethnic) values in order to prove their role. Disqualification.', // 10.12.
			'Crying. Disqualification.', // 10.13.
			'Any shouts and conversations at «night»; any touches of players at «night»; «night» gestures showing the Sheriff or Don, whom to check. Disqualification.', // 10.14.
			'Declaration of protest to the Moderator before the end of the game. Disqualification.', // 10.15.
			'Voting with palm, finger, elbow. Disqualification.', // 10.16.
			'Using unique night information by the Sheriff. «Unique night information» refers to the actions of players or events that only the Sheriff could see during the «night» phase. Disqualification.', // 10.17.
			'Insulting another player, Moderator, or back referee. Disqualification.', // 10.18.
			'Offensive language. Disqualification.', // 10.19.
			'Rude unethical behavior, disrespect for the players, Moderator or tournament organizers. Disqualification.', // 10.20.
			array // 10.21.
			(
				RULES_LEGACY_ADVICE,
				'«legacy» influence',
				array
				(
					'Asking to add or not to add someone to the «legacy», expressed verbally or using gestures. Disqualification.',
					'Asking to add or not to add someone to the «legacy», expressed verbally or using gestures. Warning.',
					'Asking to add or not to add someone to the «legacy», expressed verbally or using gestures. No penalty.',
				),
				array
				(
					'Disqualification.',
					'Warning.',
					'No penalty.',
				),
			),
			array // 10.22.
			(
				RULES_LEGACY_ARGUMENT,
				'Referring to «legacy» influence',
				array
				(
					'Referring to influence on the «legacy» in order to prove the role in the game or to explain the actions of the game. Disqualification.',
					'Referring to influence on the «legacy» in order to prove the role in the game or to explain the actions of the game. Warning.',
					'Referring to influence on the «legacy» in order to prove the role in the game or to explain the actions of the game. No penalty.',
				),
				array
				(
					'Disqualification.',
					'Warning.',
					'No penalty.',
				),
			),
			'Involuntary peeping «at night». Disqualification.', // 10.23.
			array // 10.24.
			(
				RULES_PRY,
				'Peeping',
				array
				(
					'Conscious peeping. Other non-gaming ways of obtaining information. Team defeat.',
					'Conscious peeping. Other non-gaming ways of obtaining information. Disqualification.',
				),
				array
				(
					'Team defeat.',
					'Disqualification.',
				),
			),
			array // 10.25.
			(
				RULES_AUDIENCE_TIP,
				'Auditorium hints',
				array
				(
					'Getting hints from the auditorium. Any communication with the auditorium. Team defeat.',
					'Getting hints from the auditorium. Any communication with the auditorium. Disqualification.',
				),
				array
				(
					'Team defeat.',
					'Disqualification.',
				),
			),
			array // 10.26.
			(
				RULES_OATH,
				'Oath',
				array
				(
					'Oath in any form. For example swearing on your mother’s life. Team defeat.',
					'Oath in any form. For example swearing on your mother’s life. Disqualification.',
					'Oath in any form. For example swearing on your mother’s life. Warning.',
				),
				array
				(
					'Team defeat.',
					'Disqualification.',
					'Warning.',
				),
			),
			array // 10.27.
			(
				RULES_THREAT,
				'Blackmail, threats, bribing, betting',
				array
				(
					'Blackmail, threats, bribing, and betting. Team defeat.',
					'Blackmail, threats, bribing, and betting. Disqualification.',
				),
				array
				(
					'Team defeat.',
					'Disqualification.',
				),
			),
		),
	),
);

?>