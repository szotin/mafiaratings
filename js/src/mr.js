var mr = new function()
{
	//--------------------------------------------------------------------------------------
	// profile
	//--------------------------------------------------------------------------------------
	this.createAccount = function(name, email)
	{
		dlg.form("account_create.php", function(){}, 400);
	}

	this.initProfile = function()
	{
		dlg.form("profile_init.php", refr, 600);
	}

	this.changePassword = function()
	{
		dlg.form("password_change.php", refr, 400);
	}

	this.mobileStyleChange = function()
	{
		json.post("api/ops/account.php", { op: "site_style", style: $('#mobile').val() }, refr);
	}

	this.browserLangChange = function()
	{
		json.post("api/ops/account.php", { op: "browser_lang", lang: $('#browser_lang').val() }, refr);
	}

	this.resetPassword = function()
	{
		dlg.form("password_reset.php", refr, 400);
	}

	this.editAccount = function()
	{
		dlg.form("account_edit.php", refr, 600);
	}
	
	//--------------------------------------------------------------------------------------
	// administration
	//--------------------------------------------------------------------------------------
	this.lockSite = function(val)
	{
		if (val)
		{
			json.post("api/ops/repair.php", { op: 'lock' }, refr);
		}
		else
		{
			json.post("api/ops/repair.php", { op: 'unlock' }, refr);
		}
	}
	
	//--------------------------------------------------------------------------------------
	// langs
	//--------------------------------------------------------------------------------------
	this.getLangs = function(prefix)
	{
		if (typeof prefix == "undefined")
		{
			prefix = "";
		}
		
		var elem = $('#' + prefix + 'langs');
		if (elem.length > 0)
		{
			return elem.val();
		}
		
		var langs = 0;
		elem = $('#' + prefix + 'en');
		if (elem.length > 0 && elem.attr('checked'))
		{
			langs |= 1;
		}
		
		elem = $('#' + prefix + 'ru');
		if (elem.length > 0 && elem.attr('checked'))
		{
			langs |= 2;
		}
		return langs;
	}

	this.setLangs = function(langs, prefix)
	{
		if (typeof prefix == "undefined")
		{
			prefix = "";
		}
		
		var elem = $('#' + prefix + 'en');
		if (elem.length > 0)
		{
			elem.prop('checked', (langs & 1) != 0);
		}
		
		elem = $('#' + prefix + 'ru');
		if (elem.length > 0)
		{
			elem.prop('checked', (langs & 2) != 0);
		}
	}

	//--------------------------------------------------------------------------------------
	// note
	//--------------------------------------------------------------------------------------
	this.editNote = function(id)
	{
		dlg.form("note_edit.php?note=" + id, refr);
	}

	this.deleteNote = function(id, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/note.php", { op: 'delete', note_id: id }, refr);
		}

		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}

	this.upNote = function(id)
	{
		json.post("api/ops/note.php", { op: 'up', note_id: id, up: "" }, refr);
	}

	this.createNote = function(clubId)
	{
		dlg.form("note_create.php?club=" + clubId, refr);
	}

	//--------------------------------------------------------------------------------------
	// advert
	//--------------------------------------------------------------------------------------
	this.editAdvert = function(id)
	{
		dlg.form("advert_edit.php?advert=" + id, refr);
	}

	this.deleteAdvert = function(id, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/advert.php", { op: 'delete', advert_id: id }, refr);
		}

		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}

	this.createAdvert = function(clubId)
	{
		dlg.form("advert_create.php?club=" + clubId, refr);
	}

	//--------------------------------------------------------------------------------------
	// Season
	//--------------------------------------------------------------------------------------
	this.editSeason = function(id)
	{
		dlg.form("season_edit.php?season=" + id, refr);
	}

	this.deleteSeason = function(id, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/season.php", { op: 'delete', season_id: id }, refr);
		}

		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}

	this.createSeason = function(clubId)
	{
		dlg.form("season_create.php?club=" + clubId, refr);
	}

	//--------------------------------------------------------------------------------------
	// address
	//--------------------------------------------------------------------------------------
	this.createAddr = function(clubId)
	{
		dlg.form("address_create.php?club=" + clubId, refr, 600);
	}

	this.restoreAddr = function(addrId)
	{
		json.post("api/ops/address.php", { op: "restore", address_id: addrId }, refr);
	}

	this.retireAddr = function(addrId)
	{
		json.post("api/ops/address.php", { op: "retire", address_id: addrId }, refr);
	}

	this.genAddr = function(addrId, confirmMessage)
	{
		function gen()
		{
			json.post("api/ops/address.php", { op: "google_map", address_id: addrId }, refr);
		}

		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, gen);
		}
		else
		{
			gen();
		}
	}

	this.editAddr = function(id)
	{
		dlg.form("address_edit.php?id=" + id, refr, 600);
	}

	//--------------------------------------------------------------------------------------
	// city
	//--------------------------------------------------------------------------------------
	this.createCity = function()
	{
		dlg.form("city_create.php", refr);
	}

	this.deleteCity = function(id)
	{
		dlg.form("city_delete.php?id=" + id, refr);
	}

	this.editCity = function(id)
	{
		dlg.form("city_edit.php?id=" + id, refr);
	}

	//--------------------------------------------------------------------------------------
	// club
	//--------------------------------------------------------------------------------------
	this.createClub = function()
	{
		dlg.form("club_create.php", refr, 600);
	}

	this.restoreClub = function(id)
	{
		json.post("api/ops/club.php", { op: "restore", club_id: id }, refr);
	}

	this.retireClub = function(id)
	{
		json.post("api/ops/club.php", { op: "retire", club_id: id }, refr);
	}

	this.editClub = function(id)
	{
		dlg.form("club_edit.php?id=" + id, refr, 600);
	}

	this.joinClub = function(id)
	{
		json.post("api/ops/account.php", { op: 'join_club', club_id: id }, refr);
	}

	this.quitClub = function(id, confirmMessage)
	{
		function proceed()
		{
			json.post("api/ops/account.php", { op: 'quit_club', club_id: id }, refr);
		}
		
		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, proceed);
		}
		else
		{
			proceed();
		}
	}

	this.playClub = function(id)
	{
		window.location.replace("game.php?club=" + id);
	}

	this.acceptClub = function(id)
	{
		dlg.form("club_accept.php?id=" + id, refr, 600);
	}

	this.declineClub = function(id)
	{
		dlg.form("club_decline.php?id=" + id, refr);
	}

	//--------------------------------------------------------------------------------------
	// country
	//--------------------------------------------------------------------------------------
	this.createCountry = function()
	{
		dlg.form("country_create.php", refr);
	}

	this.deleteCountry = function(id)
	{
		dlg.form("country_delete.php?id=" + id, refr);
	}

	this.editCountry = function(id)
	{
		dlg.form("country_edit.php?id=" + id, refr);
	}

	//--------------------------------------------------------------------------------------
	// event
	//--------------------------------------------------------------------------------------
	this.createEvent = function(clubId)
	{
		dlg.form("event_create.php?club=" + clubId, function(obj)
		{
			var ids = obj.events;
			var delim = '';
			var id_str = '';
			for (var i = 0; i < ids.length; ++i)
			{
				id_str += delim + ids[i];
				delim = ',';
			}
			window.location.replace('create_event_mailing.php?bck=1&for=1&msg=0&events=' + ids);
		});
	}
	
	this.restoreEvent = function(id)
	{
		json.post("api/ops/event.php", { op: "restore", event_id: id }, function(obj)
		{
			if (typeof obj.question == "string")
			{
				dlg.yesNo(obj.question, null, null, function() { window.location.replace('create_event_mailing.php?bck=1&events=' + id); }, refr);
			}
			else
			{
				refr();
			}
		});
	}

	this.cancelEvent = function(id, confirmMessage)
	{
		function _cancel()
		{
			json.post("api/ops/event.php", { op: "cancel", event_id: id }, function(obj)
			{
				if (typeof obj.question == "string")
				{
					dlg.yesNo(obj.question, null, null, function() { window.location.replace('create_event_mailing.php?bck=1&for=2&events=' + id); }, refr);
				}
				else
				{
					refr();
				}
			})
		}
		
		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _cancel);
		}
		else
		{
			_delete();
		}
	}

	this.editEvent = function(id)
	{
		window.location.replace("edit_event.php?bck=1&id=" + id);
	}

	this.eventMailing = function(id)
	{
		window.location.replace("event_mailings.php?bck=1&id=" + id);
	}

	this.attendEvent = function(id, url)
	{
		dlg.form("event_attend.php?id=" + id, function()
		{
			refr(url);
		});
	}

	this.passEvent = function(id, url, message)
	{
		json.post("api/ops/event.php", { op: "attend", event_id: id, odds: 0 }, function()
		{
			if (typeof message == "undefined")
				refr(url);
			else
				dlg.info(message, null, null, function() { refr(url); });
		});
	}

	this.playEvent = function(id)
	{
		window.location.replace("game.php?event=" + id);
	}

	this.extendEvent = function(id)
	{
		dlg.form("event_extend.php?id=" + id, refr);
	}
	
	//--------------------------------------------------------------------------------------
	// scoring system
	//--------------------------------------------------------------------------------------
	this.deleteScoringSystem = function(id, confirmMessage)
	{
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("api/ops/scoring.php", { op: 'delete', scoring_id: id }, refr);
		});
	}

	this.createScoringSystem = function(clubId)
	{
		dlg.form("scoring_create.php?club=" + clubId, refr, 400);
	}

	this.editScoringSystem = function(id)
	{
		dlg.form("scoring_edit.php?id=" + id, refr, 400);
	}
	
	this.createScoringRule = function(systemId, category)
	{
		dlg.form("scoring_rule_create.php?scoring=" + systemId + '&category=' + category, refr);
	}
	
	this.deleteScoringRule = function(systemId, category, matter, confirmMessage)
	{
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("api/ops/scoring.php", { op: 'delete_rule', scoring_id: systemId, matter: matter, category: category }, refr);
		});
	}
	
	this.editScoringSorting = function(systemId)
	{
		dlg.form("scoring_sorting_edit.php?scoring=" + systemId, refr, 600);
	}
	
	this.showScoring = function(systemId)
	{
		dlg.infoForm("scoring_show.php?id=" + systemId);
	}

	//--------------------------------------------------------------------------------------
	// rules
	//--------------------------------------------------------------------------------------
	this.createRules = function(clubId, onSuccess)
	{
		if (typeof onSuccess == "undefined")
			onSuccess = refr;
		dlg.form("rules_create.php?club=" + clubId, onSuccess);
	}

	this.editRules = function(clubId, rulesId)
	{
		var u = "rules_edit.php?club=" + clubId;
		if (typeof rulesId != "undefined")
			u += "&id=" + rulesId;
		dlg.form(u, refr);
	}

	this.deleteRules = function(clubId, rulesId, confirmMessage)
	{
		function _delete()
		{
			json.post("api/ops/rules.php", { op: 'delete', club_id: clubId, rules_id: rulesId }, refr);
		}

		if (typeof confirmMessage == "string")
		{
			dlg.yesNo(confirmMessage, null, null, _delete);
		}
		else
		{
			_delete();
		}
	}
	
	//--------------------------------------------------------------------------------------
	// game
	//--------------------------------------------------------------------------------------
	this.deleteGame = function(gameId, confirmMessage, onSuccess)
	{
		if (typeof onSuccess == "undefined")
			onSuccess = refr;
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("api/ops/game.php", { op: 'delete', game_id: gameId }, onSuccess);
		});
	}
	
	this.editGame = function(gameId)
	{
		var gotoGame = function(data)
		{
			var link = "game.php?edit&back=" + encodeURIComponent(window.location.href);
			if (typeof data.club_id !== "undefined")
				link += "&club=" + data.club_id;
			window.location.replace(link);
		}
		
		json.post("api/ops/game.php", { op: 'change', game_id: gameId  }, gotoGame, function(errorMessage, data) 
		{
			gotoGame(data);
		});
	}
	
	this.setGameVideo = function(gameId)
	{
		dlg.form("game_video_edit.php?game=" + gameId, refr, 600);
	}
	
	this.watchGameVideo = function(gameId)
	{
		dlg.page("game_video.php?game=" + gameId);
	}
	
	//--------------------------------------------------------------------------------------
	// find
	//--------------------------------------------------------------------------------------
	this.gotoFind = function(data)
	{
		var url = window.location.href;
		var i = url.indexOf('?');
		if (i >= 0)
		{
			i = url.indexOf('page=');
			if (i >= 0)
			{
				i += 5;
				var end = url.indexOf('&', i);
				url = url.substring(0, i) + "-" + data.id;
				if (end >= 0)
					url += url.substring(end);
			}
			else
			{
				url += "&page=-" + data.id;
			}
		}
		else
		{
			url += "?page=-" + data.id;
		}
		window.location.replace(url);
	}
	
	//--------------------------------------------------------------------------------------
	// user
	//--------------------------------------------------------------------------------------
	this.banUser = function(userId, clubId)
	{
		var params = { op: 'ban', user_id: userId  };
		if (typeof clubId != "undefined")
		{
			json.post("api/ops/user.php", { op: 'ban', user_id: userId, club_id: clubId }, refr);
		}
		else
		{
			json.post("api/ops/user.php", { op: 'site_ban', user_id: userId }, refr);
		}
	}
	
	this.unbanUser = function(userId, clubId)
	{
		var params = { op: 'ban', user_id: userId  };
		if (typeof clubId != "undefined")
		{
			json.post("api/ops/user.php", { op: 'unban', user_id: userId, club_id: clubId }, refr);
		}
		else
		{
			json.post("api/ops/user.php", { op: 'site_unban', user_id: userId }, refr);
		}
	}
	
	this.editUserAccess = function(userId, clubId)
	{
		var url = "user_access.php?id=" + userId;
		if (typeof clubId != "undefined")
		{
			url += "&club=" + clubId;
		}
		dlg.form(url, refr, 400);
	}
	
	//--------------------------------------------------------------------------------------
	// comments
	//--------------------------------------------------------------------------------------
	this.showComments = function(object_name, object_id, limit, show_all, edit_class)
	{
		var url = "show_comments.php?" + object_name + "=" + object_id;
		if (typeof limit == "number")
		{
			url += "&limit=" + limit;
		}
		
		if (typeof edit_class != "undefined")
		{
			url += "&class=" + edit_class;
		}
		
		if (typeof show_all != "undefined" && show_all)
		{
			url += "&all";
		}
		
		html.get(url, function(text, title)
		{
			$('#comments').html(text);
			$("#comment").keypress(function (e)
			{
				if (e.which == 13 && !e.shiftKey) 
				{
					var message = $("#comment").val().trim();
					if (message.length > 0)
					{
						json.post("api/ops/" + object_name + ".php", { op: "comment", id: object_id, comment: message }, function()
						{
							$("#comment").val("");
							mr.showComments(object_name, object_id, limit, show_all);
						});
					}
					return false;
				}
			});			
		});
	}
	
    this.checkCommentArea = function()
	{
		var elem = document.getElementById("comment");
		var val = elem.scrollHeight;
		var h = elem.offsetHeight;
		var cal = parseInt(h) - 2;
		if(val > cal)
		{
			var fontSize = parseInt($('#comment').css("fontSize"));
			cal = cal + fontSize;
			$('#comment').css('height', cal + 'px');
		}
    }
	
	//--------------------------------------------------------------------------------------
	// videos
	//--------------------------------------------------------------------------------------
	this.createVideo = function(vtype, clubId, eventId)
	{
		if (typeof eventId != "undefined")
		{
			dlg.form("video_create.php?event=" + eventId + "&vtype=" + vtype, refr, 600);
		}
		else
		{
			dlg.form("video_create.php?club=" + clubId + "&vtype=" + vtype, refr, 600);
		}
	}
	
	this.editVideo = function(videoId)
	{
		dlg.form("video_edit.php?id=" + videoId, refr, 600);
	}
	
	this.deleteVideo = function(videoId, confirmMessage, urlToGo)
	{
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("api/ops/video.php", { 'op': 'delete', 'video_id': videoId  }, function() { refr(urlToGo); });
		});
	}
	
	this.showVideoUsers = function(videoId)
	{
		var url = "video_users.php?id=" + videoId;
		html.get(url, function(text, title)
		{
			$('#tagged').html(text);
			var tagControl = $("#tag_user");
			if (typeof tagControl == "object")
			{
				tagControl.autocomplete(
				{ 
					source: function( request, response )
					{
						$.getJSON("api/control/user.php",
						{
							num: 8,
							term: tagControl.val()
						}, response);
					}
					, select: function(event, ui) { mr.tagVideo(ui.item.id, videoId); }
					, minLength: 0
				})
				.on("focus", function () { $(this).autocomplete("search", ''); });
			}
		});
	}
	
	this.tagVideo = function(userId, videoId)
	{
		json.post("api/ops/video.php", { 'op': 'tag', 'video_id': videoId, 'user_id': userId }, function() 
		{ 
			mr.showVideoUsers(videoId);
		});
	}
	
	this.untagVideo = function(userId, videoId)
	{
		json.post("api/ops/video.php", { 'op': 'untag', 'video_id': videoId, 'user_id': userId }, function() 
		{ 
			mr.showVideoUsers(videoId);
		});
	}
}

var swfu = null;
