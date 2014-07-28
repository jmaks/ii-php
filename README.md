ii-php
---------------
ii - это русская фидообразная сеть для обмена сообщениями, подробно узнать о ней вы можете на сайте [http://iinet.sexy](http://iinet.sexy). 
В этом репозитории находится простенькая php реализация "серверной" части ii. Для начала работы рядом со скриптами надо создать каталоги *echo* и *msg*. В файле *ii-functions.php* есть функция *msg_to_ii*, вызывая которую на своём сайте, вы можете писать сообщения в ii. Имеется поддержка экономии трафика через /x/t/.

Выглядеть это будет примерно так:  
```php
require("ii-functions.php");
msg_to_ii($topicid."2014",$message,$usersent,"myforum, 1",time(),$userget,$subject,"");
//$usersent - пользователь, отправивший сообщение, а $userget - его получивший.
```
Синхронизация
----------------
Данная php нода поддерживает push-синхронизацию (когда другой узел закачивает сообщения на данный), а также fetch-синхронизацию (когда данный узел скачивает сообщения у другого) через webfetch.php, для использования которого надо создать рядом php скрипт примерно такого содержания
```php
<?php
require("webfetch.php");
$fetchconfig=[
	"http://your-ii-node.ru/ii-point.php?q=/",
	"echoarea1.10",
	"echoarea2.14",
	"myecho.2015"
];
fetch_messages($fetchconfig);
?>
```
и настроить его запуск в cron.
RSS
----------------
Также здесь есть скрипт rss граббера, который может экспортировать ленты в ii. Для его использования достаточно создать рядом php скрипт, написать туда
```php
<?php
require("ii-rss.php");

ii_rss("feedname", "http://your-feed/adress", "echoname.2014");

?>
```
и поместить его вызов в cron.
Чёрный список
---------------
Есть возможность игнорировать неправильные или плохие сообщения при синхронизации с другими станциями или с клиентами. Для этого есть файл blacklist.txt, в который можно записывать msgid таких сообщений. Образец указан в самом файле, можно пользоваться разделителями, файл заканчивать переносом строки (но при этом не ставить пустых строк среди id сообщений).
Список эхоконференций
---------------
Он задаётся в конфиге config.php, вместе с описаниями. Служит для автоматического доступа клиента к ресурсам сети.
