var mr = new function()
{
	//--------------------------------------------------------------------------------------
	// profile
	//--------------------------------------------------------------------------------------
	this.createAccount = function(name, email)
	{
		dlg.form("account_create.php", function(){}, 400);
	}

	this.activateProfile = function()
	{
		dlg.form("profile_activate.php", logout);
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
		json.post("login_ops.php", { mobile: $('#mobile').val() }, refr);
	}

	this.browserLangChange = function()
	{
		json.post("login_ops.php", { browser_lang: $('#browser_lang').val() }, refr);
	}

	this.resetPassword = function()
	{
		dlg.form("password_reset.php", refr, 400);
	}

	this.editAccount = function()
	{
		dlg.form("account_edit.php", refr, 600);
	}
	
	this.editAccountPassword = function()
	{
		dlg.form("account_password.php", refr, 600);
	}
	
	//--------------------------------------------------------------------------------------
	// administration
	//--------------------------------------------------------------------------------------
	this.lockSite = function(val)
	{
		if (val)
		{
			json.post("repair_ops.php", { lock: "" }, refr);
		}
		else
		{
			json.post("repair_ops.php", { unlock: "" }, refr);
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
			json.post("note_ops.php", { 'id': id, 'delete': "" }, refr);
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
		json.post("note_ops.php", { 'id': id, up: "" }, refr);
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
			json.post("advert_ops.php", { 'id': id, 'delete': "" }, refr);
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
			json.post("season_ops.php", { 'id': id, 'delete': "" }, refr);
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
		json.post("address_ops.php", { id: addrId, restore: "" }, refr);
	}

	this.retireAddr = function(addrId)
	{
		json.post("address_ops.php", { id: addrId, retire: "" }, refr);
	}

	this.genAddr = function(addrId, confirmMessage)
	{
		function gen()
		{
			json.post("address_ops.php", { id: addrId, gen: "" }, refr);
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
		dlg.form("club_create.php", function(){}, 600);
	}

	this.restoreClub = function(id)
	{
		json.post("club_ops.php", { 'id': id, restore: "" }, refr);
	}

	this.retireClub = function(id)
	{
		json.post("club_ops.php", { 'id': id, retire: "" }, refr);
	}

	this.editClub = function(id)
	{
		dlg.form("club_edit.php?id=" + id, refr, 600);
	}

	this.joinClub = function(id)
	{
		json.post("profile_ops.php", { 'id': id, join_club: "" }, refr);
	}

	this.quitClub = function(id, confirmMessage)
	{
		function proceed()
		{
			json.post("profile_ops.php", { 'id': id, quit_club: "" }, refr);
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
		json.post("event_ops.php", { 'id': id, restore: "" }, function(obj)
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
			json.post("event_ops.php", { 'id': id, cancel: "" }, function(obj)
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
		json.post("event_ops.php", { 'id': id, odds: 0, attend: "" }, function()
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
	// changelist
	//--------------------------------------------------------------------------------------
	this.viewChangelist = function(id)
	{
		html.get('changelist_view.php?id=' + id, function(text, title)
		{
			dlg.custom(text, title, 600, 
			{
				accept: { text: l("Accept"), click: function()
				{
					$(this).dialog("close");
					json.post("game_ops.php", { 'accept_cl': id }, refr);
				}},
				decline: { text: l("Decline"), click: function()
				{
					$(this).dialog("close");
					dlg.yesNo(l('DeleteCL'), null, null, function()
					{
						json.post("game_ops.php", { 'decline_cl': id }, refr);
					});
				}},
				close: { text: l("Close"), click: function()
				{
					$(this).dialog("close");
				}}
			});
		});
	}
	
	//--------------------------------------------------------------------------------------
	// scoring system
	//--------------------------------------------------------------------------------------
	this.deleteScoringSystem = function(id, confirmMessage)
	{
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("scoring_ops.php", { 'id': id, 'delete': "" }, refr);
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
			json.post("scoring_ops.php", { 'id': systemId, 'delete_rule': matter, 'category': category }, refr);
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
			json.post("rules_ops.php", { 'id': rulesId, 'club': clubId, 'delete': '' }, refr);
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
			json.post("game_ops.php", { 'delete_game': gameId }, onSuccess);
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
		
		json.post("game_ops.php", { 'edit_game': gameId  }, gotoGame, function(errorMessage, data) 
		{
			gotoGame(data);
		});
	}
	
	this.setGameVideo = function(gameId)
	{
		dlg.form("game_video_edit.php?game=" + gameId, refr, 600);
	}
	
	this.removeGameVideo = function(gameId, confirmMessage)
	{
		dlg.yesNo(confirmMessage, null, null, function()
		{
			json.post("game_ops.php", { 'remove_video': gameId  }, refr);
		});
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
		var params = { 'ban': userId  };
		if (typeof clubId != "undefined")
		{
			params['club'] = clubId;
		}
		json.post("user_ops.php", params, refr);
	}
	
	this.unbanUser = function(userId, clubId)
	{
		var params = { 'unban': userId  };
		if (typeof clubId != "undefined")
		{
			params['club'] = clubId;
		}
		json.post("user_ops.php", params, refr);
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
	this.showComments = function(object_name, object_id, limit, show_all)
	{
		var url = "show_comments.php?" + object_name + "=" + object_id;
		if (typeof limit == "number")
		{
			url += "&limit=" + limit;
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
						json.post("comment_ops.php", { object: object_name, id: object_id, comment: $("#comment").val() }, function()
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
			json.post("video_ops.php", { 'remove': videoId  }, function() { refr(urlToGo); });
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
						$.getJSON("user_ops.php",
						{
							list: '',
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
		json.post("video_ops.php", { 'tag': videoId, 'user_id': userId }, function() 
		{ 
			mr.showVideoUsers(videoId);
		});
	}
	
	this.untagVideo = function(userId, videoId)
	{
		json.post("video_ops.php", { 'untag': videoId, 'user_id': userId }, function() 
		{ 
			mr.showVideoUsers(videoId);
		});
	}
}

var swfu = null;
