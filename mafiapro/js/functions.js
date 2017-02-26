function open_window_scroll_menu ( width, height, path )
{
//	history.go(0);
	contact = open(path, "popup_window", "location=0,directories=0,status=0,menubar=1,scrollbars=1,resizable=0,width="+width+",height="+height+",top=20,left=80");
	contact.focus();
}

function open_window_scroll ( width, height, path )
{
//	history.go(0);
	contact = open(path, "popup_window", "location=0,directories=0,status=0,menubar=0,scrollbars=1,resizable=0,width="+width+",height="+height+",top=20,left=80");
	contact.focus();
}

function open_window_guest ( width, height, path )
{
//	history.go(0);
	contact = open(path, "popup_window", "location=0,directories=0,status=0,menubar=0,scrollbars=1,resizable=0,width="+width+",height="+height+",top=20,left=80");
	contact.focus();
//	self.close ();
}

function open_window ( width, height, path )
{
//	history.go(0);
	contact = open(path, "popup_window", "location=0,directories=0,status=0,menubar=0,scrollbars=1,resizable=0,width="+width+",height="+height+",top=20,left=80");
	contact.focus();
}

function open_window_wsb ( width, height, path )
{
	contact = open(path, "popup_window", "location=0,directories=0,status=0,menubar=1,scrollbars=1,resizable=1,width="+width+",height="+height+",top=20,left=80");
	contact.focus();
}

function repl (name)
{
	var findDig = new RegExp ("[^0-9]","g");
	var str = name.value;
	str = str.replace (findDig,"");
	name.value = str;
}

function add2cart (gid, pcs)
	{
		if (pcs < 1)
			{
				errmsg = pcs+" - недопустимое значение колличества товаров!";
				alert (errmsg);
				return;
			}
		else
			{			
 				//w = window.open("buy_item.php?gid="+gid+"&pcs="+pcs,"add2cart","height=200,width=300,status=no,scrollbars=no,toolbar=no,resizable=no,menubar=no");
 				w = window.open("/?page=cart&gid="+gid+"&pcs="+pcs, "_parent");
				w.focus();
 				return;
			}
	}