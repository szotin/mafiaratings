// This is how time of the game is set for workings surrenders and kick-outs. (...) - mean one of the set.
{ "night": 0, "time": ("start" / "arrangement" / "don" / "sheriff") }
{ "night": (1 / 2 / etc), "time": ("shooting" / "don" / "sheriff") }
{ "day": (0, 1, 2, etc), "time": ("killed" / "voting" / "speaking" / "nominee"), "speaker": (0 / 1 / .. / 10) }


// Player numbers are between 1 and 10. Note that numbering is not started with 0.
// Rounds are numerated starting from 0. Night-0 is the arrangement night. Day-0 is the initial day with 10 players. Night-1 is the first night when mafia is shooting and don/sheriff are checking. Day-1 is 9 players round (if mafia is not missing). Etc.
{
	"options": "a",                                    // Options is set when the game is requested. It specifies what game details are needed. Every letter means some details.
	                                                   // A code that is parsing the game can generate options based on available fields. So it can find out what is known about the game and what is not known.
	                                                   // For example, if the record does not contain consistent voting information the code can save this game but mark that this game should not be used in voting analitics.
	                                                   // Or the code can decline this game.
	                                                   // Here are the codes:
	                                                   // a - arrangement. The record contains static arrangement information. If it does not, this can have two meanings: either arrangement was dynamic, or there is no information on arrangements. At this time there is no way to distinct between these to. Normally it is not needed.
	                                                   // c - sheriff checks.
	                                                   // d - don checks.
	                                                   // l - legacy.
													   // m - information on when player was killed.
	                                                   // n - nominating. The record contains nomination information. Who nominates who in each round.
													   // o - information if player was killed withot the details when.
	                                                   // s - shooting.
	                                                   // v - voting. The record contains voting information. Who votes for whom in each round.
	                                                   // w - voting without the information who was voting for killing all players. Sometimes a game contains voting info but does not contain the information who voted to kill all when split happens. This setting is for games where this info does not exist.
	"games":
	[
		{
			"id": "1130",                              // Game id. Optional. Can be set to identify the game in a specific system.
			"clubId": 1,                               // Club id. Optional. Can be set to identify the club in a specific system.
			"clubName": "Mafia of Vancouver",          // Club name. Optional. Can be set to identify the club in a specific system.
			"eventId": 7854,                           // Event id. Optional. Can be set to identify the event in a specific system.
			"startTime": 1483172014,                   // Time when the game started. Mandatory. Can be set to integer, which is UNIX timestamp. Or a string in ISO 8601 format - for example "2020-10-18T20:48:57+00:00" or "20201018T204857Z"
			"endTime": 1483174497,                     // Time when the game ended. Mandatory. Can be set to integer, which is UNIX timestamp. Or a string in ISO 8601 format - for example "2020-10-18T20:48:57+00:00" or "20201018T204857Z"
			                                           // For import: start and end times can also be used by a system to identify which event this game belongs to.
			"language": "ru",                          // Two letter code of the language used in the game. Optional. When missing "ru" is assumed.
			"winner": "civ",                           // The winner of the game: "civ" for civilians, "maf" for mafia. Mandatory.
			"moderator":
			{
				"id": 138,                             // User id of the moderator. Optional.
				                                       // For import: when id is missing, the system is trying to find moderator by email. 
				"email": "moder@mafiaratings.com",     // Email of the moderator. Optional.
				                                       // For import: it is used to identify the moderator when id is missing. If they both are missing, the system is trying to find moderator by name. 
				"name": "Fantomas",                    // Moderator name. Optional.
				                                       // For import: it is used to identify the moderator when both id and email are missing, or when there is more than one user found by email. If all three are missing, or more than one user is found, the error should be generated. 
			}
			"players": 
			[
				// player 1
				{
					"id": 782,                         // User id of the player. Optional.
					                                   // For import: when id is missing, the system is trying to find player by email. 
					"email": "moder@mafiaratings.com", // Email of the player. Optional.
					                                   // For import: it is used to identify the player when id is missing. If they both are missing, the system is trying to find player by name. 
					"name": "Shapoklyak",              // Player name. Optional.
					                                   // For import: it is used to identify the player when both id and email are missing, or when there is more than one user found by email. If all three are missing, or more than one user is found, the error should be generated. 
					"role": "civ",                     // Player role. One of "civ", "maf", "don", or "sheriff"
					
					"arranged": 3,                     // Only set when "a" option is set and when player was staticly arranged. The number is night number when they suppose to be killed.
					"voting": [null, 8, 9, 4]          // Only set when "v" or "w" option is set. How the player voted in each round. Null means there was no nominations and no voting in this round. When splitting happens it is represented as a sub-array. For example [null, [8,8], 9, 4] means that the player voted two times for 8 in round 1. If option "v" is set this sub-array contains in addition how the player voted when they was asked if they want to kill all. For example [null, [8,8,true], 9, 4] means that the player in round 1 voted for 8 twice and then voted yes to kill all voting leaders.
				},
				// player 2
				{
					"id": 58,
					"email": "moder@mafiaratings.com",
					"name": "cherry",
					"role": "srf",
					"dead"                             // Only when "m" or "o" option is set. When "m" option is set it contains additional information when the player was killed. When "o" is set it is only true or false (if missing false is assumed).
					{
						"night": 2
					}
					"arranged": 2,
					"best_player": true,               // Is set to true when the player gets "best player" title. Mandatory to all fotmats.
					"nominating": [null, 9],           // Only set when "n" option is set. How the player was nominating in each round. Null means the player was not nominating in the appropriate round.
					"voting": [null, 9],
					"warnings":
					[
						{
						}
					]
				},
				// player 3
				{
					"id": 774,
					"email": "moder@mafiaratings.com",
					"name": "Max",
					"role": "civ",
					"nominating": [null, 4, 9],
					"voting": [null, 8, 9, 4]
				},
				// player 4
				{
					"id": 739,
					"email": "moder@mafiaratings.com",
					"name": "pravda",
					"role": "maf",
					"dead"
					{
						"day": 3
					}
					"voting": [null, 8, 9, 6],
					"nominating": [null, null, null, 5],
					"shooting": [5, 2, 10]             // Only set when "s" option is set and the player is maf. How the player was shooting each night. Null means the player did not make a shot, or did an incorrect shot. Note that unlike voting arrays this array starts from round-1. Becaus mafia does not shoot in night-0.
				},
				// player 5
				{
					"id": 39,
					"email": "moder@mafiaratings.com",
					"name": "Watson",
					"role": "civ",
					"dead"
					{
						"night": 1,
					}
					"arranged": 1,
					"legacy": [1, 4, 8]                // Only when "l" option is set and when applicable. By the most of the rules only the player killed first night leaves the legacy. But some rules (Kharkiv) support legacies after any night.
				},
				// player 6
				{
					"id": 25,
					"email": "moder@mafiaratings.com",
					"name": "Fantomas",
					"role": "civ",
					"don": 1,                          // Only when "d" option is set and when applicable. The night when this player was checked by the don.
					"voting": [null, 2, 9, 4],
					"nominating": [null, null, null, 3]
				},
				// player 7
				{
					"id": 208,
					"email": "moder@mafiaratings.com",
					"name": "babadysia",
					"role": "civ",
					"nominating": [null, 1],
					"voting": [null, 8, 9, 4]
				},
				// player 8
				{
					"id": 776,
					"email": "moder@mafiaratings.com",
					"name": "Maha88",
					"role": "maf",
					"dead"
					{
						"day": 1
					}
					"voting": [null, 1],
					"shooting": [5]
				},
				// player 9
				{
					"id": 6,
					"name": "snake",
					"email": "moder@mafiaratings.com",
					"role": "don",
					"dead"
					{
						"day": 2
					}
					"sheriff": 1,                      // Only when "c" option is set and when applicable. The night when this player was checked by the sheriff.
					"nominating": [null, 8],
					"voting": [null, 8, 9],
					"shooting": [5, 2]
				},
				// player 10
				{
					"id": 137,
					"email": "moder@mafiaratings.com",
					"name": "Momento Mori",
					"role": "civ",
					"dead"
					{
						"night": 3
					}
					"don": 2,
					"sheriff": 2,
					"nominating": [null, 2],
					"voting": [null, 2, 9]
				}
			]
		},
		{
			"id": "1129",
			"club_id": 1,
			"event_id": 7854,
			"start_time": 1483169354,
			"end_time": 1483171524,
			"language": 2,
			"moderator_id": 138,
			"winner": "maf",
			"players": [
        {
          "user_id": 667,
          "nick_name": "Igorbek",
          "role": "don",
          "best_player": true,
          "voting": {
            "round_0": 0,
            "round_1": 3,
            "round_2": 4
          },
          "nominating": {
            "round_1": 3,
            "round_2": 3
          },
          "shooting": {
            "round_0": 9,
            "round_1": 5
          }
        },
        {
          "user_id": 671,
          "nick_name": "bobbi",
          "role": "civ",
          "death_round": 1,
          "death_type": "day",
          "warnings": 1,
          "arranged_for_round": 1,
          "nominating": {
            "round_0": 0,
            "round_1": 5
          },
          "voting": {
            "round_0": 0,
            "round_1": 5
          }
        },
        {
          "user_id": 739,
          "nick_name": "pravda",
          "role": "civ",
          "voting": {
            "round_0": 0,
            "round_1": 5,
            "round_2": 3
          }
        },
        {
          "user_id": 137,
          "nick_name": "Momento Mori",
          "role": "civ",
          "death_round": 2,
          "d*eath_type": "day",
          "voting": {
            "round_0": 0,
            "round_1": 1,
            "round_2": 4
          },
          "nominating": {
            "round_1": 2,
            "round_2": 0
          }
        },
        {
          "user_id": 39,
          "nick_name": "Watson",
          "role": "maf",
          "checked_by_srf": 1,
          "voting": {
            "round_0": 0,
            "round_1": 1,
            "round_2": 3
          },
          "nominating": {
            "round_1": 1
          },
          "shooting": {
            "round_0": 9,
            "round_1": 5
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "civ",
          "death_round": 1,
          "death_type": "night",
          "warnings": 1,
          "arranged_for_round": 2,
          "checked_by_don": 0,
          "checked_by_srf": 0,
          "voting": {
            "round_0": 0,
            "round_1": 1
          }
        },
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "civ",
          "voting": {
            "round_0": 0,
            "round_1": 3,
            "round_2": 3
          }
        },
        {
          "user_id": 58,
          "nick_name": "cherry",
          "role": "srf",
          "checked_by_don": 1,
          "voting": {
            "round_0": 0,
            "round_1": 1,
            "round_2": 4
          },
          "nominating": {
            "round_2": 4
          }
        },
        {
          "user_id": 774,
          "nick_name": "Max",
          "role": "maf",
          "voting": {
            "round_0": 0,
            "round_1": 1,
            "round_2": 3
          },
          "shooting": {
            "round_0": 9,
            "round_1": 5
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "civ",
          "death_round": 0,
          "death_type": "night",
          "arranged_for_round": 0,
          "mafs_guessed": 1,
          "voting": {
            "round_0": 0
          }
        }
      ]
    },
    {
      "id": "1128",
      "club_id": 1,
      "event_id": 7854,
      "start_time": 1483166196,
      "end_time": 1483168965,
      "language": 2,
      "moderator_id": 138,
      "winner": "civ",
      "players": [
        {
          "user_id": 137,
          "nick_name": "Momento Mori",
          "role": "civ",
          "death_round": 0,
          "death_type": "night",
          "arranged_for_round": 0,
          "mafs_guessed": 2
        },
        {
          "user_id": 645,
          "nick_name": "Mafia",
          "role": "civ",
          "checked_by_don": 0,
          "voting": {
            "round_1": 2,
            "round_2": 9,
            "round_3": 6
          }
        },
        {
          "user_id": 39,
          "nick_name": "Watson",
          "role": "maf",
          "death_round": 1,
          "death_type": "day",
          "nominating": {
            "round_1": 1
          },
          "voting": {
            "round_1": 8
          },
          "shooting": {
            "round_0": 0
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "civ",
          "death_round": 2,
          "death_type": "night",
          "arranged_for_round": 2,
          "checked_by_srf": 0,
          "nominating": {
            "round_1": 2,
            "round_2": 8
          },
          "voting": {
            "round_1": 2,
            "round_2": 9
          }
        },
        {
          "user_id": 670,
          "nick_name": "yukonsam",
          "role": "srf",
          "death_round": 1,
          "death_type": "night",
          "arranged_for_round": 1,
          "checked_by_don": 1,
          "best_player": true,
          "voting": {
            "round_1": 2
          }
        },
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "civ",
          "voting": {
            "round_1": 2,
            "round_2": 9,
            "round_3": 6
          },
          "nominating": {
            "round_2": 9
          }
        },
        {
          "user_id": 667,
          "nick_name": "Igorbek",
          "role": "maf",
          "death_round": 3,
          "death_type": "day",
          "voting": {
            "round_1": 2,
            "round_2": 9,
            "round_3": 8
          },
          "nominating": {
            "round_3": 8
          },
          "shooting": {
            "round_0": 0,
            "round_1": 4,
            "round_2": 3
          }
        },
        {
          "user_id": 739,
          "nick_name": "pravda",
          "role": "civ",
          "checked_by_srf": 1,
          "voting": {
            "round_1": 2,
            "round_2": 9,
            "round_3": 6
          }
        },
        {
          "user_id": 776,
          "nick_name": "Maha88",
          "role": "civ",
          "voting": {
            "round_1": 2,
            "round_2": 1,
            "round_3": 6
          },
          "nominating": {
            "round_2": 6,
            "round_3": 6
          }
        },
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "don",
          "death_round": 2,
          "death_type": "day",
          "nominating": {
            "round_1": 8,
            "round_2": 1
          },
          "voting": {
            "round_1": 2,
            "round_2": 8
          },
          "shooting": {
            "round_0": 0,
            "round_1": 4
          }
        }
      ]
    },
    {
      "id": "1127",
      "club_id": 1,
      "event_id": 7854,
      "start_time": 1483162741,
      "end_time": 1483165367,
      "language": 2,
      "moderator_id": 138,
      "winner": "civ",
      "players": [
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "civ",
          "death_round": 0,
          "death_type": "night",
          "arranged_for_round": 0,
          "mafs_guessed": 2,
          "voting": {
            "round_0": 1
          }
        },
        {
          "user_id": 667,
          "nick_name": "Igorbek",
          "role": "civ",
          "death_round": 1,
          "death_type": "day",
          "checked_by_don": 0,
          "voting": {
            "round_0": 1,
            "round_1": 6
          }
        },
        {
          "user_id": 819,
          "nick_name": "Mr Kartoshka",
          "role": "civ",
          "nominating": {
            "round_0": 1,
            "round_2": 5,
            "round_4": 9
          },
          "voting": {
            "round_0": 1,
            "round_1": 1,
            "round_2": 5,
            "round_3": 6,
            "round_4": 9
          }
        },
        {
          "user_id": 58,
          "nick_name": "cherry",
          "role": "civ",
          "death_round": 2,
          "death_type": "night",
          "voting": {
            "round_0": 1,
            "round_1": 6,
            "round_2": 5
          },
          "nominating": {
            "round_1": 4
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "civ",
          "warnings": 1,
          "arranged_for_round": 2,
          "checked_by_don": 1,
          "voting": {
            "round_0": 1,
            "round_1": 5,
            "round_2": 5,
            "round_3": 6,
            "round_4": 9
          },
          "nominating": {
            "round_1": 5,
            "round_3": 6
          }
        },
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "maf",
          "death_round": 2,
          "death_type": "day",
          "checked_by_srf": 0,
          "voting": {
            "round_0": 1,
            "round_1": 4,
            "round_2": 5
          },
          "shooting": {
            "round_0": 0,
            "round_1": 7
          }
        },
        {
          "user_id": 137,
          "nick_name": "Momento Mori",
          "role": "maf",
          "death_round": 3,
          "death_type": "day",
          "checked_by_srf": 1,
          "voting": {
            "round_0": 1,
            "round_1": 4,
            "round_2": 5,
            "round_3": 4
          },
          "nominating": {
            "round_1": 9,
            "round_2": 3,
            "round_3": 4
          },
          "shooting": {
            "round_0": 0,
            "round_1": 7,
            "round_2": 3
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "srf",
          "death_round": 1,
          "death_type": "night",
          "arranged_for_round": 1,
          "best_player": true,
          "voting": {
            "round_0": 1,
            "round_1": 5
          }
        },
        {
          "user_id": 774,
          "nick_name": "Max",
          "role": "civ",
          "death_round": 3,
          "death_type": "night",
          "voting": {
            "round_0": 1,
            "round_1": 1,
            "round_2": 5,
            "round_3": 6
          },
          "nominating": {
            "round_1": 6
          }
        },
        {
          "user_id": 6,
          "nick_name": "snake",
          "role": "don",
          "death_round": 4,
          "death_type": "day",
          "voting": {
            "round_0": 1,
            "round_1": 1,
            "round_2": 3,
            "round_3": 4,
            "round_4": 9
          },
          "nominating": {
            "round_1": 1
          },
          "shooting": {
            "round_0": 0,
            "round_1": 7,
            "round_2": 3,
            "round_3": 8
          }
        }
      ]
    },
    {
      "id": "1126",
      "club_id": 1,
      "event_id": 7854,
      "start_time": 1483159252,
      "end_time": 1483162240,
      "language": 2,
      "moderator_id": 138,
      "winner": "civ",
      "players": [
        {
          "user_id": 645,
          "nick_name": "Mafia",
          "role": "civ",
          "death_round": 3,
          "death_type": "night",
          "checked_by_don": 1,
          "checked_by_srf": 2,
          "nominating": {
            "round_1": 9
          },
          "voting": {
            "round_1": [
              4,
              9
            ],
            "round_2": 3,
            "round_3": 7
          }
        },
        {
          "user_id": 774,
          "nick_name": "Max",
          "role": "maf",
          "death_round": 3,
          "death_type": "day",
          "voting": {
            "round_1": [
              4,
              4
            ],
            "round_2": 3,
            "round_3": 7
          },
          "nominating": {
            "round_3": 7
          },
          "shooting": {
            "round_0": 2,
            "round_1": 4,
            "round_2": 8
          }
        },
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "civ",
          "death_round": 0,
          "death_type": "night",
          "arranged_for_round": 0,
          "mafs_guessed": 2
        },
        {
          "user_id": 783,
          "nick_name": "Tortik",
          "role": "don",
          "death_round": 2,
          "death_type": "day",
          "checked_by_srf": 1,
          "voting": {
            "round_1": [
              4,
              4
            ],
            "round_2": 3
          },
          "nominating": {
            "round_2": 1
          },
          "shooting": {
            "round_0": 2,
            "round_1": 4
          }
        },
        {
          "user_id": 6,
          "nick_name": "snake",
          "role": "civ",
          "death_round": 1,
          "death_type": "night",
          "arranged_for_round": 1,
          "nominating": {
            "round_1": 1
          },
          "voting": {
            "round_1": [
              9,
              9
            ]
          }
        },
        {
          "user_id": 137,
          "nick_name": "Momento Mori",
          "role": "civ",
          "checked_by_don": 0,
          "checked_by_srf": 0,
          "nominating": {
            "round_1": 4,
            "round_2": 3,
            "round_3": 1,
            "round_4": 7
          },
          "voting": {
            "round_1": [
              9,
              9
            ],
            "round_2": 3,
            "round_3": 1,
            "round_4": 6
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "maf",
          "death_round": 4,
          "death_type": "day",
          "voting": {
            "round_1": [
              9,
              9
            ],
            "round_2": 3,
            "round_3": 1,
            "round_4": 7
          },
          "shooting": {
            "round_0": 2,
            "round_1": 4,
            "round_2": 8,
            "round_3": 0
          }
        },
        {
          "user_id": 58,
          "nick_name": "cherry",
          "role": "civ",
          "arranged_for_round": 2,
          "nominating": {
            "round_1": 0,
            "round_4": 6
          },
          "voting": {
            "round_1": [
              9,
              9
            ],
            "round_2": 3,
            "round_3": 1,
            "round_4": 6
          }
        },
        {
          "user_id": 776,
          "nick_name": "Maha88",
          "role": "srf",
          "death_round": 2,
          "death_type": "night",
          "best_player": true,
          "voting": {
            "round_1": [
              4,
              4
            ],
            "round_2": 3
          }
        },
        {
          "user_id": 789,
          "nick_name": "Drawe",
          "role": "civ",
          "death_round": 1,
          "death_type": "day",
          "voting": {
            "round_1": [
              0,
              4
            ]
          }
        }
      ]
    },
    {
      "id": "1125",
      "club_id": 1,
      "event_id": 7853,
      "start_time": 1482564199,
      "end_time": 1482567258,
      "language": 2,
      "moderator_id": 645,
      "winner": "civ",
      "players": [
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "civ",
          "death_round": 1,
          "death_type": "night",
          "arranged_for_round": 0,
          "voting": {
            "round_1": 1
          }
        },
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "maf",
          "death_round": 1,
          "death_type": "day",
          "nominating": {
            "round_1": 5
          },
          "voting": {
            "round_1": 5
          },
          "shooting": {
            "round_0": 9
          }
        },
        {
          "user_id": 229,
          "nick_name": "Kate",
          "role": "civ",
          "voting": {
            "round_1": 1,
            "round_2": 6,
            "round_3": 6,
            "round_4": 4
          },
          "nominating": {
            "round_2": 6
          }
        },
        {
          "user_id": 776,
          "nick_name": "Maha88",
          "role": "maf",
          "death_round": 2,
          "death_type": "day",
          "voting": {
            "round_1": 1,
            "round_2": 3
          },
          "shooting": {
            "round_0": 9,
            "round_1": 0
          }
        },
        {
          "user_id": 774,
          "nick_name": "Max",
          "role": "don",
          "death_round": 4,
          "death_type": "day",
          "voting": {
            "round_1": 1,
            "round_2": 3,
            "round_3": 6,
            "round_4": 4
          },
          "nominating": {
            "round_2": 8,
            "round_3": 6
          },
          "shooting": {
            "round_0": 9,
            "round_1": 0,
            "round_2": 7,
            "round_3": 5
          }
        },
        {
          "user_id": 667,
          "nick_name": "Igorbek",
          "role": "civ",
          "death_round": 3,
          "death_type": "night",
          "warnings": 1,
          "checked_by_don": 1,
          "checked_by_srf": 2,
          "nominating": {
            "round_1": 1,
            "round_2": 3,
            "round_3": 4
          },
          "voting": {
            "round_1": 1,
            "round_2": 3,
            "round_3": 6
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "civ",
          "death_round": 3,
          "death_type": "day",
          "warnings": 1,
          "arranged_for_round": 1,
          "nominating": {
            "round_1": 0,
            "round_3": 2
          },
          "voting": {
            "round_1": 1,
            "round_2": 3,
            "round_3": 2
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "srf",
          "death_round": 2,
          "death_type": "night",
          "arranged_for_round": 2,
          "checked_by_don": 2,
          "nominating": {
            "round_1": 3
          },
          "voting": {
            "round_1": 1,
            "round_2": 3
          }
        },
        {
          "user_id": 58,
          "nick_name": "cherry",
          "role": "civ",
          "checked_by_srf": 1,
          "best_player": true,
          "voting": {
            "round_1": 1,
            "round_2": 3,
            "round_3": 6,
            "round_4": 4
          },
          "nominating": {
            "round_4": 4
          }
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "night"
        }
      ]
    },
    {
      "id": "1124",
      "club_id": 1,
      "event_id": 7853,
      "start_time": 1482560638,
      "end_time": 1482563475,
      "language": 2,
      "moderator_id": 645,
      "winner": "civ",
      "players": [
        {
          "user_id": 58,
          "nick_name": "cherry",
          "role": "civ",
          "warnings": 1,
          "checked_by_srf": 2,
          "voting": {
            "round_1": 1,
            "round_3": 7,
            "round_4": 6
          }
        },
        {
          "user_id": 667,
          "nick_name": "Igorbek",
          "role": "don",
          "death_round": 1,
          "death_type": "day",
          "warnings": 1,
          "voting": {
            "round_1": 6
          },
          "shooting": {
            "round_0": 8
          }
        },
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "civ",
          "death_round": 2,
          "death_type": "night",
          "arranged_for_round": 1,
          "nominating": {
            "round_1": 1
          },
          "voting": {
            "round_1": 1
          }
        },
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "civ",
          "checked_by_srf": 1,
          "voting": {
            "round_1": 6,
            "round_3": 7,
            "round_4": 6
          },
          "nominating": {
            "round_3": 7
          }
        },
        {
          "user_id": 776,
          "nick_name": "Maha88",
          "role": "srf",
          "checked_by_don": 0,
          "best_player": true,
          "voting": {
            "round_1": 1,
            "round_3": 7,
            "round_4": 6
          },
          "nominating": {
            "round_4": 6
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "civ",
          "warnings": 2,
          "arranged_for_round": 2,
          "checked_by_srf": 0,
          "nominating": {
            "round_1": 6
          },
          "voting": {
            "round_1": 6,
            "round_3": 7,
            "round_4": 6
          }
        },
        {
          "user_id": 774,
          "nick_name": "Max",
          "role": "maf",
          "death_round": 4,
          "death_type": "day",
          "voting": {
            "round_1": 1,
            "round_3": 7,
            "round_4": 6
          },
          "shooting": {
            "round_0": 7,
            "round_1": 2,
            "round_2": 2
          }
        },
        {
          "user_id": 229,
          "nick_name": "Kate",
          "role": "maf",
          "death_round": 3,
          "death_type": "day",
          "voting": {
            "round_1": 1,
            "round_3": 7
          },
          "shooting": {
            "round_0": 8,
            "round_1": 5,
            "round_2": 2
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "civ",
          "arranged_for_round": 0,
          "voting": {
            "round_1": 1,
            "round_3": 7,
            "round_4": 6
          }
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "gave-up"
        }
      ]
    },
    {
      "id": "1123",
      "club_id": 1,
      "event_id": 7853,
      "start_time": 1482557254,
      "end_time": 1482560004,
      "language": 2,
      "moderator_id": 645,
      "winner": "civ",
      "players": [
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "civ",
          "checked_by_don": 0,
          "voting": {
            "round_1": 1,
            "round_2": 5,
            "round_3": 4
          }
        },
        {
          "user_id": 776,
          "nick_name": "Maha88",
          "role": "maf",
          "death_round": 1,
          "death_type": "day",
          "checked_by_srf": 0,
          "voting": {
            "round_1": 7
          },
          "shooting": {
            "round_0": 9
          }
        },
        {
          "user_id": 229,
          "nick_name": "Kate",
          "role": "civ",
          "checked_by_srf": 2,
          "voting": {
            "round_1": 1,
            "round_2": 5,
            "round_3": 3
          }
        },
        {
          "user_id": 667,
          "nick_name": "Igorbek",
          "role": "civ",
          "warnings": 1,
          "arranged_for_round": 2,
          "nominating": {
            "round_1": 5,
            "round_3": 0
          },
          "voting": {
            "round_1": 1,
            "round_2": 5,
            "round_3": 4
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "maf",
          "death_round": 3,
          "death_type": "day",
          "nominating": {
            "round_1": 1,
            "round_2": 5,
            "round_3": 3
          },
          "voting": {
            "round_1": 1,
            "round_2": 5,
            "round_3": 3
          },
          "shooting": {
            "round_0": 9,
            "round_1": 8,
            "round_2": 7
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "don",
          "death_round": 2,
          "death_type": "day",
          "checked_by_srf": 1,
          "nominating": {
            "round_1": 7,
            "round_2": 3
          },
          "voting": {
            "round_1": 7,
            "round_2": 3
          },
          "shooting": {
            "round_0": 9,
            "round_1": 8
          }
        },
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "civ",
          "voting": {
            "round_1": 5,
            "round_2": 5,
            "round_3": 4
          },
          "nominating": {
            "round_3": 4
          }
        },
        {
          "user_id": 774,
          "nick_name": "Max",
          "role": "srf",
          "death_round": 2,
          "death_type": "night",
          "checked_by_don": 1,
          "nominating": {
            "round_1": 2
          },
          "voting": {
            "round_1": 1,
            "round_2": 5
          }
        },
        {
          "user_id": 58,
          "nick_name": "cherry",
          "role": "civ",
          "death_round": 1,
          "death_type": "night",
          "arranged_for_round": 1,
          "best_player": true,
          "voting": {
            "round_1": 1
          }
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "night",
          "arranged_for_round": 0
        }
      ]
    },
    {
      "id": "1112",
      "club_id": 42,
      "event_id": 7851,
      "start_time": 1480734760,
      "end_time": 1485579698,
      "language": 2,
      "moderator_id": 794,
      "winner": "maf",
      "players": [
        {
          "user_id": 894,
          "nick_name": "Gray",
          "role": "maf",
          "death_round": 2,
          "death_type": "day",
          "warnings": 2,
          "voting": {
            "round_1": 8,
            "round_2": [
              2,
              2
            ]
          },
          "shooting": {
            "round_0": 4,
            "round_1": 9
          }
        },
        {
          "user_id": 803,
          "nick_name": "Alinka",
          "role": "civ",
          "death_round": 4,
          "death_type": "day",
          "voting": {
            "round_1": 0,
            "round_2": [
              6,
              0
            ],
            "round_3": 6,
            "round_4": 1
          },
          "nominating": {
            "round_2": 0,
            "round_3": 6,
            "round_4": 5
          }
        },
        {
          "user_id": 831,
          "nick_name": "sunny23",
          "role": "civ",
          "nominating": {
            "round_1": 8,
            "round_2": 7,
            "round_4": 1
          },
          "voting": {
            "round_1": 8,
            "round_2": [
              7,
              0
            ],
            "round_3": 6,
            "round_4": 1
          }
        },
        {
          "user_id": 761,
          "nick_name": "MicP",
          "role": "civ",
          "death_round": 3,
          "death_type": "night",
          "checked_by_srf": 0,
          "nominating": {
            "round_1": 0,
            "round_2": 6,
            "round_3": 1
          },
          "voting": {
            "round_1": 8,
            "round_2": [
              0,
              0
            ],
            "round_3": 5
          }
        },
        {
          "user_id": 895,
          "nick_name": "Aleksei",
          "role": "srf",
          "death_round": 0,
          "death_type": "night"
        },
        {
          "user_id": 823,
          "nick_name": "Lyagushka",
          "role": "maf",
          "warnings": 1,
          "best_player": true,
          "nominating": {
            "round_1": 7
          },
          "voting": {
            "round_1": 8,
            "round_2": [
              7,
              0
            ],
            "round_3": 6,
            "round_4": 5
          },
          "shooting": {
            "round_0": 4,
            "round_1": 9,
            "round_2": 7,
            "round_3": 3
          }
        },
        {
          "user_id": 802,
          "nick_name": "Nadezda",
          "role": "civ",
          "death_round": 3,
          "death_type": "day",
          "warnings": 1,
          "voting": {
            "round_1": 0,
            "round_2": [
              0,
              0
            ],
            "round_3": 5
          },
          "nominating": {
            "round_3": 5
          }
        },
        {
          "user_id": 825,
          "nick_name": "Zeke",
          "role": "civ",
          "death_round": 2,
          "death_type": "night",
          "warnings": 1,
          "checked_by_don": 0,
          "voting": {
            "round_1": 8,
            "round_2": [
              2,
              0
            ]
          },
          "nominating": {
            "round_2": 2
          }
        },
        {
          "user_id": 814,
          "nick_name": "Max",
          "role": "don",
          "death_round": 1,
          "death_type": "day",
          "nominating": {
            "round_1": 2
          },
          "voting": {
            "round_1": 2
          },
          "shooting": {
            "round_0": 4
          }
        },
        {
          "user_id": 822,
          "nick_name": "Romashka",
          "role": "civ",
          "death_round": 1,
          "death_type": "night",
          "voting": {
            "round_1": 8
          }
        }
      ]
    },
    {
      "id": "1111",
      "club_id": 42,
      "event_id": 7850,
      "start_time": 1480220079,
      "end_time": 1480223453,
      "language": 2,
      "moderator_id": 801,
      "winner": "civ",
      "players": [
        {
          "user_id": 794,
          "nick_name": "Dimtom",
          "role": "civ",
          "warnings": 3,
          "best_player": true,
          "nominating": {
            "round_0": 0,
            "round_1": 7,
            "round_3": 4
          },
          "voting": {
            "round_0": [
              1,
              1
            ],
            "round_1": 3,
            "round_2": 8,
            "round_3": 4,
            "round_4": 9
          }
        },
        {
          "user_id": 2031,
          "nick_name": "andrim",
          "role": "srf",
          "death_round": 3,
          "death_type": "night",
          "voting": {
            "round_0": [
              0,
              0
            ],
            "round_1": 4,
            "round_2": 8,
            "round_3": 4
          },
          "nominating": {
            "round_1": 4
          }
        },
        {
          "user_id": 803,
          "nick_name": "Alinka",
          "role": "civ",
          "death_round": 0,
          "death_type": "night",
          "voting": {
            "round_0": [
              0,
              0
            ]
          }
        },
        {
          "user_id": 807,
          "nick_name": "Irina Pivovarova",
          "role": "maf",
          "death_round": 1,
          "death_type": "day",
          "voting": {
            "round_0": [
              0,
              0
            ],
            "round_1": 4
          },
          "shooting": {
            "round_0": 2
          }
        },
        {
          "user_id": 802,
          "nick_name": "Nadezda",
          "role": "don",
          "death_round": 3,
          "death_type": "day",
          "warnings": 1,
          "voting": {
            "round_0": [
              0,
              0
            ],
            "round_1": 7,
            "round_2": 8,
            "round_3": 0
          },
          "shooting": {
            "round_0": 2,
            "round_1": 5,
            "round_2": 5
          }
        },
        {
          "user_id": 818,
          "nick_name": "Alena",
          "role": "civ",
          "death_round": 2,
          "death_type": "night",
          "checked_by_srf": 2,
          "voting": {
            "round_0": [
              0,
              0
            ],
            "round_1": 3,
            "round_2": 1
          },
          "nominating": {
            "round_1": 8
          }
        },
        {
          "user_id": 814,
          "nick_name": "Max",
          "role": "civ",
          "warnings": 1,
          "checked_by_don": 0,
          "checked_by_srf": 0,
          "nominating": {
            "round_0": 1,
            "round_2": 8,
            "round_3": 0
          },
          "voting": {
            "round_0": [
              1,
              1
            ],
            "round_1": 8,
            "round_2": 8,
            "round_3": 4,
            "round_4": 9
          }
        },
        {
          "user_id": 798,
          "nick_name": "Luchik",
          "role": "civ",
          "checked_by_srf": 1,
          "voting": {
            "round_0": [
              1,
              1
            ],
            "round_1": 3,
            "round_2": 8,
            "round_3": 4,
            "round_4": 9
          },
          "nominating": {
            "round_2": 1,
            "round_3": 1,
            "round_4": 9
          }
        },
        {
          "user_id": 800,
          "nick_name": "Saga",
          "role": "civ",
          "death_round": 2,
          "death_type": "day",
          "warnings": 3,
          "voting": {
            "round_0": [
              1,
              1
            ],
            "round_1": 3,
            "round_2": 6
          },
          "nominating": {
            "round_1": 3,
            "round_2": 6
          }
        },
        {
          "user_id": 816,
          "nick_name": "Luiza",
          "role": "maf",
          "death_round": 4,
          "death_type": "day",
          "checked_by_srf": 3,
          "voting": {
            "round_0": [
              1,
              1
            ],
            "round_1": 4,
            "round_2": 8,
            "round_3": 4,
            "round_4": 9
          },
          "shooting": {
            "round_0": 2,
            "round_1": 0,
            "round_2": 5,
            "round_3": 1
          }
        }
      ]
    },
    {
      "id": "1110",
      "club_id": 42,
      "event_id": 7850,
      "start_time": 1480219161,
      "end_time": 1480219477,
      "language": 2,
      "moderator_id": 803,
      "winner": "civ",
      "players": [
        {
          "user_id": 830,
          "nick_name": "Elena",
          "role": "srf",
          "death_round": 2,
          "death_type": "night",
          "voting": {
            "round_2": [
              6,
              6
            ]
          }
        },
        {
          "user_id": 802,
          "nick_name": "Nadezda",
          "role": "civ",
          "checked_by_don": 0,
          "voting": {
            "round_2": [
              6,
              6
            ],
            "round_3": 5,
            "round_4": [
              8,
              8
            ]
          }
        },
        {
          "user_id": 801,
          "nick_name": "Vicky",
          "role": "civ",
          "checked_by_don": 1,
          "nominating": {
            "round_2": 6
          },
          "voting": {
            "round_2": [
              6,
              6
            ],
            "round_3": 5,
            "round_4": [
              7,
              7
            ]
          }
        },
        {
          "user_id": 2031,
          "nick_name": "andrim",
          "role": "civ",
          "death_round": 3,
          "death_type": "night",
          "checked_by_srf": 0,
          "voting": {
            "round_2": [
              0,
              6
            ],
            "round_3": 5
          },
          "nominating": {
            "round_3": 5
          }
        },
        {
          "user_id": 814,
          "nick_name": "Max",
          "role": "civ",
          "voting": {
            "round_2": [
              6,
              6
            ],
            "round_3": 5,
            "round_4": [
              7,
              7
            ]
          },
          "nominating": {
            "round_4": 7
          }
        },
        {
          "user_id": 816,
          "nick_name": "Luiza",
          "role": "don",
          "death_round": 3,
          "death_type": "day",
          "checked_by_srf": 2,
          "voting": {
            "round_2": [
              6,
              6
            ],
            "round_3": 5
          },
          "shooting": {
            "round_0": 2,
            "round_1": 2,
            "round_2": 0
          }
        },
        {
          "user_id": 800,
          "nick_name": "Saga",
          "role": "maf",
          "death_round": 2,
          "death_type": "day",
          "nominating": {
            "round_2": 4
          },
          "voting": {
            "round_2": [
              0,
              0
            ]
          },
          "shooting": {
            "round_0": 1,
            "round_1": 1
          }
        },
        {
          "user_id": 815,
          "nick_name": "NNN",
          "role": "civ",
          "death_round": 4,
          "death_type": "day",
          "nominating": {
            "round_2": 0
          },
          "voting": {
            "round_2": [
              0,
              6
            ],
            "round_3": 5,
            "round_4": [
              8,
              8
            ]
          }
        },
        {
          "user_id": 813,
          "nick_name": "Zhenya",
          "role": "maf",
          "death_round": 4,
          "death_type": "day",
          "voting": {
            "round_2": [
              0,
              6
            ],
            "round_3": 5,
            "round_4": [
              8,
              8
            ]
          },
          "shooting": {
            "round_0": 0,
            "round_1": 0,
            "round_2": 0,
            "round_3": 3
          }
        },
        {
          "user_id": 794,
          "nick_name": "Dimtom",
          "role": "civ",
          "checked_by_srf": 1,
          "nominating": {
            "round_2": 2,
            "round_4": 8
          },
          "voting": {
            "round_2": [
              0,
              6
            ],
            "round_3": 5,
            "round_4": [
              7,
              7
            ]
          }
        }
      ]
    },
    {
      "id": "1116",
      "club_id": 1,
      "event_id": 7843,
      "start_time": 1480148625,
      "end_time": 1480150360,
      "language": 2,
      "moderator_id": 616,
      "winner": "civ",
      "players": [
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "maf",
          "death_round": 2,
          "death_type": "day",
          "voting": {
            "round_1": [
              6,
              4
            ],
            "round_2": 5
          },
          "shooting": {
            "round_0": 7,
            "round_1": 3
          }
        },
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "civ",
          "voting": {
            "round_1": [
              4,
              4
            ],
            "round_2": 0
          }
        },
        {
          "user_id": 264,
          "nick_name": "Bagel",
          "role": "civ",
          "checked_by_don": 0,
          "checked_by_srf": 0,
          "nominating": {
            "round_1": 5,
            "round_2": 5
          },
          "voting": {
            "round_1": [
              6,
              6
            ],
            "round_2": 1
          }
        },
        {
          "user_id": 810,
          "nick_name": "Shtirlitz",
          "role": "srf",
          "death_round": 1,
          "death_type": "night",
          "best_player": true,
          "voting": {
            "round_1": [
              6,
              6
            ]
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "don",
          "death_round": 1,
          "death_type": "day",
          "nominating": {
            "round_1": 6
          },
          "voting": {
            "round_1": [
              5,
              6
            ]
          },
          "shooting": {
            "round_0": 7
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "civ",
          "warnings": 1,
          "arranged_for_round": 2,
          "nominating": {
            "round_1": 4,
            "round_2": 0
          },
          "voting": {
            "round_1": [
              4,
              4
            ],
            "round_2": 0
          }
        },
        {
          "user_id": 667,
          "nick_name": "Igorbek",
          "role": "civ",
          "arranged_for_round": 1,
          "checked_by_srf": 1,
          "voting": {
            "round_1": [
              4,
              4
            ],
            "round_2": 0
          },
          "nominating": {
            "round_2": 1
          }
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "night",
          "arranged_for_round": 0
        },
        {
          "nick_name": "",
          "role": "maf",
          "death_round": 0,
          "death_type": "kick-out"
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "kick-out"
        }
      ]
    },
    {
      "id": "1115",
      "club_id": 1,
      "event_id": 7843,
      "start_time": 1480145842,
      "end_time": 1480147735,
      "language": 2,
      "moderator_id": 667,
      "winner": "maf",
      "players": [
        {
          "user_id": 616,
          "nick_name": "Crazy",
          "role": "civ",
          "warnings": 3,
          "voting": {
            "round_1": 3,
            "round_2": 0
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "civ",
          "death_round": 1,
          "death_type": "night",
          "warnings": 2,
          "checked_by_srf": 1,
          "voting": {
            "round_1": 3
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "civ",
          "death_round": 2,
          "death_type": "night",
          "warnings": 2,
          "checked_by_don": 0,
          "checked_by_srf": 0,
          "nominating": {
            "round_1": 3,
            "round_2": 0
          },
          "voting": {
            "round_1": 3,
            "round_2": 5
          }
        },
        {
          "user_id": 810,
          "nick_name": "Shtirlitz",
          "role": "maf",
          "death_round": 1,
          "death_type": "day",
          "warnings": 1,
          "voting": {
            "round_1": 1
          },
          "shooting": {
            "round_0": 7
          }
        },
        {
          "user_id": 264,
          "nick_name": "Bagel",
          "role": "civ",
          "death_round": 3,
          "death_type": "warning",
          "warnings": 4,
          "voting": {
            "round_1": 3,
            "round_2": 5
          },
          "nominating": {
            "round_2": 5,
            "round_3": 6
          }
        },
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "srf",
          "death_round": 2,
          "death_type": "day",
          "checked_by_don": 1,
          "voting": {
            "round_1": 3,
            "round_2": 5
          }
        },
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "don",
          "best_player": true,
          "nominating": {
            "round_1": 1
          },
          "voting": {
            "round_1": 1,
            "round_2": 5
          },
          "shooting": {
            "round_0": 7,
            "round_1": 1,
            "round_2": 2
          }
        },
        {
          "nick_name": "",
          "role": "maf",
          "death_round": 0,
          "death_type": "night"
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "kick-out"
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "kick-out"
        }
      ]
    },
    {
      "id": "1114",
      "club_id": 1,
      "event_id": 7843,
      "start_time": 1480143225,
      "end_time": 1480145142,
      "language": 2,
      "moderator_id": 667,
      "winner": "civ",
      "players": [
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "civ",
          "voting": {
            "round_1": 3,
            "round_2": 1
          }
        },
        {
          "user_id": 616,
          "nick_name": "Crazy",
          "role": "don",
          "death_round": 2,
          "death_type": "day",
          "nominating": {
            "round_1": 3,
            "round_2": 0
          },
          "voting": {
            "round_1": 3,
            "round_2": 0
          },
          "shooting": {
            "round_0": 7,
            "round_1": 5
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "civ",
          "warnings": 2,
          "checked_by_srf": 0,
          "voting": {
            "round_1": 1,
            "round_2": 1
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "maf",
          "death_round": 1,
          "death_type": "day",
          "nominating": {
            "round_1": 1
          },
          "voting": {
            "round_1": 1
          },
          "shooting": {
            "round_0": 7
          }
        },
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "srf",
          "warnings": 3,
          "checked_by_don": 1,
          "best_player": true,
          "voting": {
            "round_1": 1,
            "round_2": 1
          },
          "nominating": {
            "round_2": 1
          }
        },
        {
          "user_id": 810,
          "nick_name": "Shtirlitz",
          "role": "civ",
          "death_round": 1,
          "death_type": "night",
          "voting": {
            "round_1": 3
          }
        },
        {
          "user_id": 264,
          "nick_name": "Bagel",
          "role": "civ",
          "warnings": 1,
          "checked_by_don": 0,
          "checked_by_srf": 1,
          "voting": {
            "round_1": 3,
            "round_2": 0
          }
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "night"
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "kick-out"
        },
        {
          "nick_name": "",
          "role": "maf",
          "death_round": 0,
          "death_type": "kick-out"
        }
      ]
    },
    {
      "id": "1113",
      "club_id": 1,
      "event_id": 7843,
      "start_time": 1480140531,
      "end_time": 1480142707,
      "language": 2,
      "moderator_id": 616,
      "winner": "maf",
      "players": [
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "civ",
          "death_round": 1,
          "death_type": "day",
          "voting": {
            "round_1": 1
          }
        },
        {
          "user_id": 810,
          "nick_name": "Shtirlitz",
          "role": "civ",
          "death_round": 3,
          "death_type": "day",
          "warnings": 2,
          "voting": {
            "round_1": 0,
            "round_2": 3,
            "round_3": 4
          },
          "nominating": {
            "round_3": 4
          }
        },
        {
          "user_id": 264,
          "nick_name": "Bagel",
          "role": "srf",
          "death_round": 1,
          "death_type": "night",
          "warnings": 1,
          "checked_by_don": 0,
          "best_player": true,
          "voting": {
            "round_1": 0
          }
        },
        {
          "user_id": 667,
          "nick_name": "Igorbek",
          "role": "don",
          "death_round": 2,
          "death_type": "day",
          "warnings": 3,
          "checked_by_srf": 0,
          "nominating": {
            "round_1": 1
          },
          "voting": {
            "round_1": 1,
            "round_2": 1
          },
          "shooting": {
            "round_0": 7,
            "round_1": 2
          }
        },
        {
          "user_id": 208,
          "nick_name": "babadysia",
          "role": "maf",
          "warnings": 1,
          "checked_by_srf": 1,
          "voting": {
            "round_1": 0,
            "round_2": 1,
            "round_3": 1
          },
          "nominating": {
            "round_2": 1,
            "round_3": 1
          },
          "shooting": {
            "round_0": 7,
            "round_1": 2,
            "round_2": 6
          }
        },
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "civ",
          "warnings": 3,
          "nominating": {
            "round_1": 0,
            "round_2": 3
          },
          "voting": {
            "round_1": 0,
            "round_2": 3,
            "round_3": 1
          }
        },
        {
          "user_id": 782,
          "nick_name": "Shapoklyak",
          "role": "civ",
          "death_round": 2,
          "death_type": "night",
          "voting": {
            "round_1": 0,
            "round_2": 3
          },
          "nominating": {
            "round_2": 4
          }
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "night",
          "arranged_for_round": 0,
          "mafs_guessed": 0
        },
        {
          "nick_name": "",
          "role": "maf",
          "death_round": 0,
          "death_type": "kick-out"
        },
        {
          "nick_name": "",
          "role": "civ",
          "death_round": 0,
          "death_type": "kick-out"
        }
      ]
    },
    {
      "id": "1109",
      "club_id": 42,
      "event_id": 7848,
      "start_time": 1479698646,
      "end_time": 1479701870,
      "language": 2,
      "moderator_id": 761,
      "winner": "civ",
      "players": [
        {
          "user_id": 800,
          "nick_name": "Saga",
          "role": "don",
          "death_round": 3,
          "death_type": "day",
          "warnings": 2,
          "voting": {
            "round_1": 1,
            "round_2": 7,
            "round_3": 2
          },
          "shooting": {
            "round_0": 5,
            "round_1": 9,
            "round_2": 3
          }
        },
        {
          "user_id": 25,
          "nick_name": "Fantomas",
          "role": "civ",
          "death_round": 1,
          "death_type": "day",
          "warnings": 1,
          "voting": {
            "round_1": 7
          }
        },
        {
          "user_id": 2031,
          "nick_name": "andrim",
          "role": "civ",
          "warnings": 1,
          "nominating": {
            "round_1": 6,
            "round_2": 7,
            "round_3": 0,
            "round_4": 6
          },
          "voting": {
            "round_1": 6,
            "round_2": 7,
            "round_3": 0,
            "round_4": 6
          }
        },
        {
          "user_id": 668,
          "nick_name": "Len4a",
          "role": "civ",
          "death_round": 2,
          "death_type": "night",
          "warnings": 3,
          "nominating": {
            "round_1": 7
          },
          "voting": {
            "round_1": 6,
            "round_2": 7
          }
        },
        {
          "user_id": 801,
          "nick_name": "Vicky",
          "role": "civ",
          "nominating": {
            "round_1": 0
          },
          "voting": {
            "round_1": 7,
            "round_2": 7,
            "round_3": 0,
            "round_4": 6
          }
        },
        {
          "user_id": 803,
          "nick_name": "Alinka",
          "role": "civ",
          "death_round": 0,
          "death_type": "night",
          "warnings": 1
        },
        {
          "user_id": 798,
          "nick_name": "Luchik",
          "role": "maf",
          "death_round": 4,
          "death_type": "day",
          "voting": {
            "round_1": 1,
            "round_2": 7,
            "round_3": 2,
            "round_4": 2
          },
          "nominating": {
            "round_3": 2,
            "round_4": 2
          },
          "shooting": {
            "round_0": 5,
            "round_1": 9,
            "round_2": 3,
            "round_3": 8
          }
        },
        {
          "user_id": 667,
          "nick_name": "Igorbek",
          "role": "maf",
          "death_round": 2,
          "death_type": "day",
          "warnings": 2,
          "checked_by_srf": 1,
          "nominating": {
            "round_1": 1
          },
          "voting": {
            "round_1": 1,
            "round_2": 7
          },
          "shooting": {
            "round_0": 5,
            "round_1": 9
          }
        },
        {
          "user_id": 794,
          "nick_name": "dbimatov",
          "role": "civ",
          "death_round": 3,
          "death_type": "night",
          "warnings": 2,
          "checked_by_srf": 0,
          "nominating": {
            "round_1": 2,
            "round_3": 6
          },
          "voting": {
            "round_1": 0,
            "round_2": 7,
            "round_3": 0
          }
        },
        {
          "user_id": 799,
          "nick_name": "handroid",
          "role": "srf",
          "death_round": 1,
          "death_type": "night",
          "checked_by_don": 0,
          "voting": {
            "round_1": 2
          }
        }
      ]
    }
  ],
  "version": 0
}