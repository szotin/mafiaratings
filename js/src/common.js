//------------------------------------------------------------------------------------------
// localization
//------------------------------------------------------------------------------------------
function l()
{
	if (arguments.length == 0)
	{
		return _l["UnknownError"];
	}
	
	var result = _l[arguments[0]];
	if (result === undefined)
	{
		return _l["UnknownError"];
	}
	
	for (var i = 1; i < arguments.length; ++i)
	{
		result = result.replace(new RegExp('\\{' + i + '\\}', 'g'), arguments[i]);
	}
	return result;
} // l()

function handleError(e)
{
	if (typeof e.stack != "undefined")
		console.log(e.stack);
	dlg.error(e);
} // handleError(e)

var dlg = new function()
{
	var _lastId = 0;
	
	this.custom = function(text, title, width, buttons, onClose)
	{
		var parentElem = $("#dlg");
		var id = 'dlg' + _lastId;
		++_lastId;
		
		if (typeof width != "number")
		{
			width = parseInt(width);
			if (isNaN(width) || width <= 0)
			{
				width = 500;
			}
		}
		
		parentElem.append('<div id="' + id + '" title="' + title + '">' + text + '</div>');
		var elem = $('#' + id);
		//elem.html(text);
		return elem.dialog(
		{
			modal: true,
			resizable: false,
			hide: {effect: 'fade', duration: 500},
			show: {effect: 'fade', duration: 500},
			'width': width,
			close: function()
			{
				if (typeof onClose != "undefined") onClose();
				elem.remove();
				--_lastId;
			},
			'buttons': buttons
		});
	}
	
	this.onScreen = function()
	{
		return _lastId > 0;
	}

	this.curId = function()
	{
		return _lastId - 1;
	}

	this.close = function(dlgId)
	{
		if (typeof dlgId == "undefined")
		{
			dlgId = _lastId - 1;
		}
		if (dlgId >= 0)
		{
			$("#dlg" + dlgId).dialog("close");
		}
	}

	this.error = function(text, title, width, onClose)
	{
		if (typeof title != "string")
		{
			title = l("Error");
		}
		return dlg.custom(text, title, width, 
		{
			ok: { id:"dlg-ok", text: l("Ok"), click: function() { $(this).dialog("close"); } }
		}, onClose);
	}

	this.info = function(text, title, width, onClose)
	{
		if (typeof title != "string")
		{
			title = l("Information");
		}
		return dlg.custom(text, title, width, 
		{
			ok: { id:"dlg-ok", text: l("Close"), click: function() { $(this).dialog("close"); } }
		}, onClose);
	}

	this.yesNo = function(text, title, width, onYes, onNo)
	{
		if (typeof title != "string")
		{
			title = l("Attention");
		}
		return dlg.custom(text, title, width, 
		{
			yes: { id:"dlg-yes", text: l("Yes"), click: function() { $(this).dialog("close"); if (typeof onYes != "undefined") onYes(); } },
			no: { id:"dlg-no", text: l("No"), click: function() { $(this).dialog("close"); if (typeof onNo != "undefined") onNo(); } }
		});
	}

	this.okCancel = function(text, title, width, onOk, onCancel)
	{
		if (typeof title != "string")
		{
			title = l("Attention");
		}
		return dlg.custom(text, title, width, 
		{
			ok: { id:"dlg-ok", text: l("Ok"), click: function() { $(this).dialog("close"); if (typeof onOk != "undefined") onOk(); } },
			cancel: { id:"dlg-cancel", text: l("Cancel"), click: function() { $(this).dialog("close"); if (typeof onCancel != "undefined") onCancel(); } }
		});
	}
	
	this.page = function(formPage, width)
	{
		var id = null;
		function formLoaded(text, title)
		{
			id = '#dlg' + _lastId;
			dlg.custom(text, title, width, 
			{
				ok: { id:"dlg-ok", text: l("Ok"), click: function() { $(this).dialog("close"); } }
			});
		}
		
		if (typeof width != "number")
		{
			width = parseInt(width);
			if (isNaN(width) || width <= 0)
			{
				width = 800;
			}
		}
		html.get(formPage, formLoaded);
	}
	
	this.form = function(formPage, onSuccess, width, onCancel)
	{
		var id = null;
		function formCommited(obj)
		{
			if (id != null)
			{
				$(id).dialog("close");
			}
			if (typeof onSuccess != "undefined")
			{
				onSuccess(obj);
			}
		}
		
		function formCanceled()
		{
			$(this).dialog("close"); 
			if (typeof onCancel != "undefined")
			{
				onCancel(); 
			}
		}
		
		function formLoaded(text, title)
		{
			id = '#dlg' + _lastId;
			dlg.custom(text, title, width, 
			{
				ok: { id:"dlg-ok", text: l("Ok"), click: function() { commit(formCommited); } },
				cancel: { id:"dlg-cancel", text: l("Cancel"), click: formCanceled }
			});
		}
		
		if (typeof width != "number")
		{
			width = parseInt(width);
			if (isNaN(width) || width <= 0)
			{
				width = 800;
			}
		}
		html.get(formPage, formLoaded);
	}
	
	
	this.infoForm = function(formPage, width)
	{
		var id = null;
		function formLoaded(text, title)
		{
			id = '#dlg' + _lastId;
			dlg.custom(text, title, width, 
			{
				ok: { id:"dlg-ok", text: l("Close"), click: function() { $(this).dialog("close"); } }
			});
		}
		
		if (typeof width != "number")
		{
			width = parseInt(width);
			if (isNaN(width) || width <= 0)
			{
				width = 800;
			}
		}
		html.get(formPage, formLoaded);
	}
	
} // dlg

var dialogWaiter = new function()
{
	var counter = 0;

	// returning false cancels the operation
	this.start = function()
	{
		++counter;
		setTimeout(function() { if (counter > 0) $("#loading").show(); }, 500);
		return true;
	}
	
	this.success = function()
	{
		if (--counter <= 0)
		{
			counter = 0;
			$("#loading").hide();
		}
	}
	
	this.error = function(message, onError, data)
	{
		if (--counter <= 0)
		{
			counter = 0;
			$("#loading").hide();
		}
		
		var noDialog = false;
		if (typeof onError === "function")
		{
			if (typeof data != "undefined")
			{
				try
				{
					noDialog = onError(message, jQuery.parseJSON(data));
				}
				catch (e)
				{
					noDialog = onError(message);
				}				
			}
			else
			{
				noDialog = onError(message);
			}
		}
		
		if (!noDialog)
			dlg.error(message);
	}
	
	this.info = function(message, title, onClose)
	{
		dlg.info(message, title, null, onClose);
	}
	
//	this.connected = function(c) { console.log((c?'connected to ':'disconnected from ') + http.host()); }
	this.connected = function(c) {}
} // dialogWaiter

var silentWaiter = new function()
{
	this.start = function() { return true; }
	this.success = function() {}
	this.error = function(message) { console.log(message); }
	this.info = function(message, title, onClose) { onClose(); }
	this.connected = function(c) {}
} // silentWaiter

var http = new function()
{
	var _waiter = dialogWaiter;
	var _host = '';
	var _connected = false;
	
	this.connected = function(c, w)
	{
		var _c = _connected;
		if (typeof c == "boolean" && _connected != c)
		{
			_connected = c;
			if (typeof w == "object")
				w.connected(c)
			else
				_waiter.connected(c);
		}
		return _c;
	}
	
	this.waiter = function(w)
	{
		var _w = _waiter;
		if (typeof w == "object")
		{
			_waiter = w;
		}
		return _w;
	}
	
	this.host = function(host)
	{
		var h = _host;
		if (typeof host == "string" && host != _host)
		{
			_host = host;
			http.connected(false);
		}
		return h;
	}
	
	this.errorMsg = function(response, page)
	{
		console.log(response);
		if (typeof response.responseText != "undefined" && response.responseText.length > 0)
		{
			return response.responseText;
		}
		if (typeof response.statusText != "undefined" && response.statusText.length > 0)
		{
			return response.statusText;
		}
		return l('URLNotFound', page);
	}

	this.post = function(page, params, onSuccess, onError)
	{
		var w = _waiter;
		page = _host + page;
		if (w.start())
		{
			//setTimeout(function() {
			$.post(page, params).success(function(data, textStatus, response)
			{
				http.connected(true, w);
				var error = onSuccess(response.responseText);
				if (typeof error == "string" && error.length > 0)
				{
					console.log(error);
					w.error(error, onError, response.responseText);
				}
				else
				{
					w.success();
				}
			}).error(function(response)
			{
				http.connected(false, w);
				var msg = http.errorMsg(response, page);
				console.log(msg);
				w.error(msg, onError, response.responseText);
			});
			//}, 3000);
		}
	}
	
	this.upload = function(page, params, maxSize, onSuccess, onError, onProgress)
	{
		var w = _waiter;
		page = _host + page;
		if (w.start())
		{
			let request = new XMLHttpRequest();
			let formData = new FormData();
			
			for (var param in params)
			{
				let value = params[param];
				if (maxSize && value.size && maxSize < value.size)
				{
					w.error(l('FileTooBig', value.name, maxSize), onError);
					return;
				}
				formData.append(param, value);
			}
			
			if (onProgress)
			{
				request.addEventListener('progress', onProgress, false);
				if (request.upload)
				{
					request.upload.onprogress = onProgress;
				}
			}
			
			request.onreadystatechange = function(e) 
			{
				if (this.readyState == 4) 
				{
					if (this.status >= 200 && this.status < 300)
					{
						if (onSuccess)
						{
							var error = onSuccess(request.response);
							if (typeof error == "string" && error.length > 0)
							{
								console.log(error);
								w.error(error, onError);
							}
							else
							{
								w.success();
							}
						}
					}
					else
					{
						w.error(request.response, onError);
					}
				}
			};
			
			request.open("POST", page);
			request.send(formData);
		}
	}
	
	this.get = function(page, onSuccess, onError)
	{
		var w = _waiter;
		page = _host + page;
		if (w.start())
		{
			//setTimeout(function() {
			$.get(page).success(function(data, textStatus, response)
			{
				http.connected(true, w);
				var error = onSuccess(response.responseText);
				if (typeof error == "string" && error.length > 0)
				{
					console.log(error);
					w.error(error, onError, response.responseText);
				}
				else
				{
					w.success();
				}
			}).error(function(response)
			{
				http.connected(false, w);
				var msg = http.errorMsg(response, page);
				console.log(msg);
				w.error(msg, onError, response.responseText);
			});
			//}, 3000);
		}
	}
} // http

var html = new function()
{
	function _success(text, onSuccess, onError)
	{
		var title = "";
		var pos;
		if (text.substring(0, 7) == "<title=")
		{
			pos = text.indexOf(">");
			if (pos > 0)
			{
				title = text.substring(7, pos);
				text = text.substring(pos + 1);
			}
		}
		
		if (text.indexOf("<ok>", text.length - 4) !== -1)
		{
			text = text.substring(0, text.length - 4);
			return onSuccess(text, title);
		}
		
		pos = text.lastIndexOf("<error=");
		if (pos >= 0)
		{
			pos += 7;
			var end = text.lastIndexOf(">");
			if (end > 0)
			{
				return text.substring(pos, end);
			}
			return text.substring(pos);
		}
		return text;
	}
	
	this.post = function(page, params, onSuccess, onError)
	{
		http.post(page, params, function(text) { return _success(text, onSuccess, onError); }, onError);
	}
	
	this.upload = function(page, params, maxSize, onSuccess, onError, onProgress)
	{
		http.upload(page, params, maxSize, function(text) { return _success(text, onSuccess, onError); }, onError, onProgress);
	}
	
	this.get = function(page, onSuccess, onError)
	{
		http.get(page, function(text) { return _success(text, onSuccess, onError); }, onError);
	}
} // html

function loginDialog(message, userName, onError, actionAfterLogin)
{
	if (typeof userName != "string")
	{
		userName = "";
	}
	
	var html = '<p>' + message + '</p>' +
		'<table class="dialog_form" width="100%">' +
		'<tr><td width="140">' + l('UserName') + ':</td><td><input id="lf-name" value="' + userName + '"></td>' +
		'<tr><td>' + l('Password') + ':</td><td><input type="password" id="lf-pwd"></td>' +
		'<tr><td colspan="2"><input type="checkbox" id="lf-rem" checked> ' + l('remember') +
		'</td></tr></table>';
/*	if (userName != '')
	{
		html += '<script>$(function(){$("#lf-pwd").focus();});</script>';
	}*/
	
	var d = dlg.okCancel(html, l('Login'), null, function()
	{
		login($('#lf-name').val(), $('#lf-pwd').val(), $('#lf-rem').attr('checked') ? 1 : 0, function()
		{
			if (typeof actionAfterLogin != "undefined")
			{
				actionAfterLogin();
			}
			refr();
		}, onError);
	}, onError);
}

var json = new function()
{
	function _success(text, onSuccess, onError, retry)
	{
		var result = null;
		try
		{
			var obj = jQuery.parseJSON(text);
			if (obj != null)
			{
				if (typeof obj.login != "undefined")
				{
					loginDialog('', obj.login, onError, retry);
				}
				else if (typeof obj.error == "string")
				{
					if (obj.error.length <= 0 && typeof obj.message == "string")
					{
						result = obj.message;
					}
					else
					{
						result = obj.error;
					}
				}
				else if (typeof obj.message == "string")
				{
					http.waiter().info(obj.message, obj.title, function() { if (typeof onSuccess != "undefined") onSuccess(obj); });
				}
				else if (typeof onSuccess != "undefined")
				{
					onSuccess(obj);
				}
			}
			else if (typeof onSuccess != "undefined")
			{
				onSuccess(obj);
			}
		}
		catch (err)
		{
			console.log(text);
			if (typeof err.stack != "undefined")
				console.log(err.stack);
			result = '' + err;
		}
		return result;
	}
	
	this.post = function(page, params, onSuccess, onError)
	{
		http.post(page, params, function(text)
		{
			return _success(text, onSuccess, onError, function() { json.post(page, params, onSuccess, onError); });
		}, onError);
	}
	
	this.upload = function(page, params, maxSize, onSuccess, onError, onProgress)
	{
		http.upload(page, params, maxSize, function(text)
		{
			return _success(text, onSuccess, onError, function() { json.upload(page, params, maxSize, onSuccess, onError, onProgress); });
		}, onError, onProgress);
	}
	
	this.get = function(page, onSuccess, onError)
	{
		http.get(page, function(text)
		{
			return _success(text, onSuccess, onError, function() { json.get(page, onSuccess, onError); });
		}, onError);
	}
} // json

function showMenuBar()
{
	var menubar = $("#menubar");
	if (menubar != null)
	{
//		setTimeout(function() {
		menubar.menubar({
			autoExpand: true,
			menuIcon: true,
			buttons: true,
			position: {
				within: $("#demo-frame").add(window).first()
			}
		});
		menubar.show();
//		}, 3000);
	}
} // showMenuBar()

function setUrlParam(url, key, value)
{
	var str = key;
	if (typeof value == "object")
		str += '=' + encodeURI(JSON.stringify(value));
	else if (typeof value != "undefined")
		str += '=' + encodeURI(value);
	
	var beg = url.indexOf('?') + 1;
	if (beg <= 0)
		return url + '?' + str;
	
	while (true)
	{
		var end = url.indexOf('&', beg);
		if (end < 0)
		{
			var s = url.substr(beg);
			var k = s;
			var epos = s.indexOf('=');
			if (epos >= 0)
				k = s.substr(0, epos);
			if (k == key)
				return url.substr(0, beg) + str;
			return url + '&' + str;
		}
		else
		{
			var s = url.substr(beg, end);
			var k = s;
			var epos = s.indexOf('=');
			if (epos >= 0)
				k = s.substr(0, epos);
			if (k == key)
				return url.substr(0, beg) + str + url.substr(end);
		}
		beg = end + 1;
	}
}

function setUrlParams(url, params)
{
	if (typeof params == "object")
		for (var key in params)
			url = setUrlParam(url, key, params[key]);
	return url;
}

function goTo(url, params)
{
	if (typeof url == "object")
	{
		params = url;
		url = document.URL;
	}
	else if (typeof url != "string")
		url = document.URL;
	
	url = setUrlParams(url, params);
	
	var p = url.indexOf('#');
	if (p >= 0)
		url = url.substr(0, p);
	window.location.replace(url);
}

function refr()
{
	window.location.reload();
}

function login(name, pwd, rem, onSuccess, onError)
{
	// json.post("api/ops/account.php",
	// {
		// op: "login"
		// , username: name
		// , password: pwd
		// , remember: rem
	// }, onSuccess, onError);
	
	json.post("api/ops/account.php", { op: "get_token" }, function(token_resp)
	{
		if (typeof rem == "undefined") rem = $('#header-remember').attr('checked') ? 1 : 0;
		if (typeof pwd == "undefined") pwd = $("#header-password").val();
		if (typeof name == "undefined") name = $("#header-username").val();
		if (typeof onSuccess == "undefined") onSuccess = refr;
		
		var token = token_resp.token;
		var rawProof = md5(pwd) + token + name;
		var secProof = md5(rawProof);
		json.post("api/ops/account.php",
		{
			op: "login"
			, username: name
			, proof: secProof
			, remember: rem
		}, onSuccess, onError);
	}, onError);
} // login(name, pwd, rem, onSuccess)

function logout()
{
	json.post("api/ops/account.php", { op: "logout" }, function() { window.location.replace("/"); });
} // logout()

function strToTimespan(str)
{
	var timespan = 0;
	var recordExpected = true;
	var number = 0;
	var lastUnit = 0;
	for (var pos = 0; pos < str.length; ++pos) 
	{
		var c = str.charAt(pos);
		if (recordExpected)
		{
			var n = parseInt(c);
			if (!isNaN(n))
			{
				number *= 10;
				number += n;
			}
			else
			{
				recordExpected = false;
				if (number <= 0)
				{
					return 0;
				}
				
				var currentUnit = 0;
				var multiplier = 1;
				switch (c)
				{
					case 'w':
						currentUnit = 1;
						multiplier = 60 * 60 * 24 * 7;
						break;
					case 'd':
						currentUnit = 2;
						multiplier = 60 * 60 * 24;
						break;
					case 'h':
						currentUnit = 3;
						multiplier = 60 * 60;
						break;
					case 'm':
						currentUnit = 4;
						multiplier = 60;
						break;
					case 's':
						currentUnit = 5;
						break;
				}
				
				if (lastUnit >= currentUnit)
				{
					return 0;
				}
				lastUnit = currentUnit;
				timespan += number * multiplier;
				number = 0;
			}
		}
		else if (c == ' ')
		{
			recordExpected = true;
			number = 0;
		}
		else
		{
			return 0;
		}
	}
	if (recordExpected)
	{
		return 0;
	}
	return timespan;
}

function timespanToStr(timespan)
{
	var items = [['w', 60 * 60 * 24 * 7], ['d', 60 * 60 * 24], ['h', 60 * 60], ['m', 60], ['s', 1]];
	var str = '';
	var separator = '';
	for (var i in items)
	{
		var item = items[i];
		if (timespan >= item[1])
		{
			var value = Math.floor(timespan / item[1]);
			str += separator + value + item[0];
			timespan -= value * item[1];
			separator = ' ';
		}
	}
	return str;
}

function strToDate(str)
{
	var date = new Date(str);
	date.setMinutes(date.getMinutes() + date.getTimezoneOffset());
	return date;
}

function dateToStr(date, withTime)
{
	function tz(val)
	{
		if (val < 10)
		{
			return "0" + val;
		}
		return val;
	}
	
	var result = 
		date.getFullYear() + "-" + 
		tz(date.getMonth() + 1) + "-" + 
		tz(date.getDate());
	if (typeof withTime != "undefined" && withTime)
	{
		result += " " + tz(date.getHours()) + ":" + tz(date.getMinutes());
	}
	return result;
}

// function printStackTrace() { console.log((new Error('stack trace')).stack); }
