<?php

return array(

	array('Приглашение', '[club_name]', "Здравствуйте [user_name],\r\n<br>\r\n<br>приглашаем Вас на <b>[event_name]</b> в <a href=\"[address_url]\" target=\"_blank\">[address]</a> [event_date] [event_time].\r\n<br>\r\n<br>[notes]\r\n<br>\r\n<br>Если Вы придете не один, то это только приветствуется.\r\n<br>Пожалуйста дайте нам знать, придете ли Вы:\r\n<br>[accept_btn=Да, я приду] [decline_btn=Нет, не в этот раз]\r\n<br>\r\n<br>Ждем Вас с нетерпением.<hr>[unsub]Нажмите здесь[/unsub] если Вы хотите отписаться от рассылки.", 1),
	array('Отмена', '[club_name]', "Здравствуйте [user_name],\r\n<br>\r\n<br>мы отменили <b>[event_name]</b> [event_date] [event_time].\r\n<br>\r\n<br>Извините за неудобства.<hr>[unsub]Нажмите здесь[/unsub] если Вы хотите отписаться от рассылки.", 2),
	array('Изменение адреса', '[club_name]', "Здравствуйте [user_name],\r\n<br>\r\n<br>мы изменили место проведения <b>[event_name]</b> [event_date] [event_time].\r\n<br>\r\n<br>Новый адрес: <a href=\"[address_url]\" target=\"_blank\">[address]</a>.\r\n<br>\r\n<br>Извините за неудобства.<hr>[unsub]Нажмите здесь[/unsub] если Вы хотите отписаться от рассылки.", 3),
	array('Изменение времени', '[club_name]', "Здравствуйте [user_name],\r\n<br>\r\n<br>мы изменили время проведения [event_name] на <b>[event_date] [event_time]</b>.\r\n<br>\r\n<br>Пожалуйста дайте нам знать, придете ли вы не смотря на изменение времени:\r\n<br>[accept_btn=Да я приду] [decline_btn=Нет]<hr>[unsub]Нажмите здесь[/unsub] если Вы хотите отписаться от рассылки.", 4)
);

?>