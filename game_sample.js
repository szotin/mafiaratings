// General terms.
//
// 1. This format can be used to push a new game from a Client to a Server. 
// 2. This format can be used to pull the exising games from a Server.
// 3. In both cases all ids used are the Server's ids. Event Id, Club Id, and Game Id must all be knows to the Server.
// 4. Time can be specified as a UNIX timestamp, or in ISO 8601 (php date format "c").
//      Example of ISO 8601: 2004-02-12T15:19:21+00:00
//      The support of other formats is not guaranteed.
// 5. All systems must support email as user id. However it is very much not reccomended for the Server to use emails 
//    in the pull responses.

{
	// Game id on the Server. 
	// When pushing this field is ignored and should be missing. The Server returns the game with the new id in response to pushing.
	// When pulling this field always contains game id as is customary on the Server.
	"id": "1299",
	
	// In the systems where the games are grouped by events, either "event_id" or "event" should be specified.
	// It depends on the server whether these fields are mandatory or not.
	// Event can be a tournament or any other club night where games were played.
	// On pushing:
	//     - If "event_id" is specified, the event should always exist on the Server. Field "event" is ignored. When the Server 
	//       does not support events, both "event" and "event_id" are ignored. 
	//	   - If "event_id" is mising, the server is trying to find the event using the information provided by the "event" 
	//       field. If the event can not be found, the server creates one and returns "event_id" in the response. Specitying "event" 
	//       in the response is optional.
	//     - If both "event" and "event_id" are missing, it is up to the Server to make the decision. Possible decisions are: 
	//       return an error; add the game to the most current event; create a generic event.
	// On pulling:
	//	   - The Server must specify "event_id" if it supports it. If "event_id" is missing, this means that the Sever does not
	//       support events. Specitying "event" is optional. 
	"event_id": 7927,
	"event":
	{
		// Name of the event. Mandatory field when event is specified.
		"name": "VaWaCa-2017",
		// Start time of the event. Mandatory field when event is specified.
		"start_time": "2017-05-20T09:00:00-07:00",
		// End time of the event. Optional. The server can calculate it using the last game "end_time".
		"end_time": 1495393824,
		// Address where the event happened excluding the city. Optional. If missing the server should guess the address.
		"address": "Microsoft The Commons",
		// The city where the event happened. Optional. If missing the server should guess the city. Most likely it will use club's city.
		"city": "Redmond"
	},
	
	// In the systems where a game always belongs to a club, either "club_id" or "club" should be specified.
	// It depends on the server whether these fields are mandatory or not.
	// On pushing:
	//     - If "club_id" is specified, the club should always exist on the Server. Field "club" is ignored. When the Server 
	//       does not support clubs, both "club" and "club_id" are ignored. 
	//	   - If "club_id" is mising, the server is trying to find the club using the information provided by the "club" 
	//       field. If the club can not be found, the server creates one and returns "club_id" in the response. Specitying "club" 
	//       in the response is optional.
	//     - If both "club" and "club_id" are missing, it is up to the Server to make the decision. Possible decisions are: 
	//       return an error; add the game to the most current club; create a generic club.
	// On pulling:
	//	   - The Server must specify "club_id" if it supports it. If "club_id" is missing, this means that the Sever does not
	//       support clubs. Specitying "club" is optional. 
	"club_id": 42,
	"club":
	{
		// Club name. Mandatory.
		"name": "Seattle Mafia Club",
		// Club city. Mandatory.
		"city": "Seattle",
		// Club email. Optional.
		"email": "godfather@mafiaratings.com",
		// Club web site. Optional.
		"web": "https://www.facebook.com/SeattleMafiaClub/"
	},
	
	// In the systems where games include moderator information, either "moderator_id", or "moderator" should be specified.
	// It depends on the server whether these fields are mandatory or not.
	// On pushing:
	//     - If "moderator_id" is specified, the moderator should always exist on the Server. Field "moderator" is ignored. When the Server 
	//       does not support moderators, both "moderator" and "moderator_id" are ignored. 
	//	   - If "moderator_id" is mising, the server is trying to find the moderator using the information provided by the "moderator" 
	//       field. If the moderator can not be found, the server creates one and returns "moderator_id" in the response. Specitying "moderator" 
	//       in the response is optional.
	//     - If both "moderator" and "moderator_id" are missing, it is up to the Server to make the decision. Possible decisions are: 
	//       return an error; add the game to the most current moderator; create a generic moderator.
	// On pulling:
	//	   - The Server must specify "moderator_id" if it supports it. If "moderator_id" is missing, this means that the Sever does not
	//       support moderators. Specitying "moderator" is optional. 
	// !!! All systems must support emails as user ids.
	"moderator_id": 894,
	"moderator":
	{
		// User nick name. Mandatory.
		"name": "Barmaley",
		// User city. Mandatory.
		"city": "Seattle",
		// User email. Mandatory for the Client requests, optional for the Server responses.
		"email": "godfather@mafiaratings.com",
		// User's club id. Optional. When missing the Server should guess the club or leave it empty. 
		"club_id": 42
	},
	
	// Game start time
	// Must be specified. The Server returns an error otherwise.
	"start_time": 1495389080,
	
	// Game end time.
	// Optional. If missing, the game duration it assumed unknown.
	"end_time": 1495393824,
	
	// Contains two letter language code. Not case sensitive. There is no difference "Ru", "ru", or "RU".
	// When not specified "ru" should be assumed.
	"language": "ru",
	
	// Who won. Possible values are: "civ", "maf", and "tie".
	"winner": "civ",
	
	// Players list.
	"players": 
	[
		// 0
		{
			// Either "user_id", or "user" must be specified.
			// It depends on the server whether these fields are mandatory or not.
			// On pushing:
			//     - If "user_id" is specified, the user should always exist on the Server. Field "user" is ignored.
			//	   - If "user_id" is mising, the server is trying to find the user using the information provided by the "user" 
			//       field. If the user can not be found, the server creates one and returns "user_id" in the response. Specitying "user" 
			//       in the response is optional.
			// On pulling:
			//	   - The Server must specify "user_id". Specitying "user" is optional. 
			// !!! All systems must support emails as user ids.
			"user_id": 794,
			"user":
			{
				// User nick name. Mandatory.
				"name": "Docent",
				// User city. Mandatory.
				"city": "Seattle",
				// User email. Mandatory for the Client requests, optional for the Server responses.
				"email": "godfather@mafiaratings.com",
				// User's club id. Optional. When missing the Server should guess the club or leave it empty. 
				"club_id": 42
			},
			
			// Nickname used in this game.
			// Optional. If missing, it is the same as user name.
			"nickname": "Доцент",
			
			// Role in the game. Mandatory.
			// Possible values are: "civ", "maf", "sheriff", or "don".
			"role": "don"
			
			// Number of warings in the game. Optional - 0 if missing.
			// If it is 4, there must be an appropriate record in one of the rounds that player was killed by warnings.
			"warnings": 1
		},
		// 1
		{
			"user_id": 838,
			"nickname": "Siberian",
			"role": "civ"
		},
		// 2
		{
			"user_id": 807,
			"nickname": "Ira Sister",
			"role": "civ",
			"warnings": 2
		},
		// 3
		{
			"user_id": 782,
			"nickname": "Shapoklyak",
			"role": "srf",
		},
		// 4
		{
			"user_id": 791,
			"nickname": "Ай-Яй",
			"role": "civ",
			"warnings": 2,
		},
		// 5
		{
			"user_id": 872,
			"nickname": "Ganik",
			"role": "maf",
			"warnings": 1,
		},
		// 6
		{
			"user_id": 843,
			"nickname": "Костер",
			"role": "maf",
			"warnings": 2,
		},
		// 7
		{
			"user_id": 848,
			"nickname": "Magenta",
			"role": "civ",
			"warnings": 3,
		},
		// 8
		{
			"user_id": 798,
			"nickname": "Luchik",
			"role": "civ",
			"warnings": 2,
		},
		// 9
		{
			"user_id": 25,
			"nickname": "Fantomas",
			"role": "civ",
			"warnings": 1,
		}
	],
	
	"rounds":
	[
		// round 0
		{
			// both "night" and "day" fields are skipped, because nothing happened in round 0.
		},
		// round 1
		{
			"night":
			{
				// Who was arranged for this night.
				// It can be missed if arrangement was dynamic, or not known.
				"arranged": 1,
				// How mafia was shooting.
				// "shooting": 1 // Mafia killed player 2
				// "shooting": { "player_0": 1, "player_5": 1, "player_7": 6 } // Players 1 and 6 were shooting 2; player 8 was shooting 7. Mafia missed.
				// Can be missed if shooting is not known.
				"shooting":
				{
					"player_0": 0,
					"player_5": 5,
					"player_7": 7
				}
				// Sheriff check.
				// In this case sheriff checked player 5.
				// Can be missed if sheriffs checks are not known.
				"sheriff": 4
			},
			"day":
			{
				// Nominations during the day.
				// In this case there was only one nomination player 1 nominated player 10.
				// When there is only one nomination, the "voting" field is skipped, because it is obvious that the nominated player was killed (or not killed if it is round 0).
				"nominating":
				{
					"player0": 9
				}
			}
		},
		// round 2
		{
			"night":
			{
				"arranged": 3,
				"shooting": 3,
				"don": 7,
				"sheriff": 2
			}
			"day":
			{
				"nominating":
				{
					"player_2": 0,
					"player_5": 7,
					"player_7": 5
				},
				"voting":
				{
					"player_0": 0,
					"player_1": 5,
					"player_2": 5,
					"player_4": 5,
					"player_5": 7,
					"player_6": 0,
					"player_7": 0,
					"player_8": 0
				}
			}
		},
		// round 3
		{
			"night":
			{
				"arranged": 4,
				"shooting": 4
			}
			"day":
			{
				"nominating":
				{
					"player_5": 7,
					"player_6": 5,
					"player_7": 6
				},
				"voting":
				{
					"player_1": 5,
					"player_2": 5,
					"player_5": 6,
					"player_6": 5,
					"player_7": 5,
					"player_8": 5
				}
			}
		},
		// round 4
		{
			"night":
			{
				"shooting": 2
			}
			"day":
			{
				"nominating":
				{
					"player_1": 8,
					"player_7": 6
				},
				"voting":
				[
					{
						"player_1": 8,
						"player_6": 8,
						"player_7": 6,
						"player_8": 6
					},
					{
						"player_1": 8,
						"player_6": 8,
						"player_7": 6,
						"player_8": 6
					},
					{
						"player_1": false,
						"player_6": true,
						"player_7": false,
						"player_8": true
					}
				]
			}
		},
		// round 5
		{
			"day":
			{
				"nominating":
				{
					"player_6": 8,
					"player_7": 6
				},
				"voting":
				[
					{
						"player_1": 8,
						"player_6": 8,
						"player_7": 6,
						"player_8": 6
					},
					{
						"player_1": 8,
						"player_6": 8,
						"player_7": 6,
						"player_8": 6
					},
					{
						"player_1": false,
						"player_6": true,
						"player_7": false,
						"player_8": true
					}
				]
			}
		},
		// round 6
		{
			"day":
			{
				"nominating":
				{
					"player_1": 6,
					"player_7": 8
				},
				"voting":
				{
					"player_1": 6,
					"player_6": 8,
					"player_7": 6,
					"player_8": 6
				}
			}
		}
	]
}
