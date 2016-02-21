<?php
date_default_timezone_set("UTC");

if (!file_exists("config.php")) copy("config.default.php", "config.php");

require_once("config.php");
require_once("blacklist-func.php");

if($usemysql) {
	require_once("mysql-functions.php");
	
	global $db,$mysqldata;
	$md=$mysqldata;
	$db=new SQLuser($md["host"],$md["db"],$md["user"],$md["pass"],$md["table"]);
}

// а здесь пара костылей для совместимости, чтобы люди обновились

if (!isset($rss_cache_directory)) $rss_cache_directory="./feeds";
if (!isset($rss_msgtext_limit)) $rss_msgtext_limit=$msgtextlimit-400;

// если будете регулярно обновляться, то я вот это 个 уберу

function checkHash($s) {
	if(!b64d($s)) {
		return false;
	} else return true;
}

function checkEcho($echo) {
	$filter='/^[a-z0-9_!-.]{1,60}.[a-z0-9_!-]{1,60}$/';
	if(!preg_match($filter,$echo) or strpos($echo, ".")===false) return false;
	else return true;
}

function checkUser($authstr) {
	global $parr;
	for($i=0;$i<count($parr);$i++) {
		if($parr[$i][0]==$authstr) {
			$authname=$parr[$i][1];
			$addr=$i+1;
			break;
		}
	}
	if(isset($authname) and !empty($authname)) return [$authname, $addr];
	else return false;
}

function getmsg($t) {
	global $usemysql;
	$t = preg_replace("/[^a-zA-Z0-9]+/", "", $t); 
	if(!isBlackListed($t)) {
		if($usemysql) {
			$list=getMessages([$t]);
			if(!empty($list)) {
				return $list[$t];
			} else {
				return "";
			}
		}
		else {
			if(file_exists("msg/$t")) {
				return file_get_contents ("msg/$t");
			} else {
				return "";
			}
		}
	} else return "";
}

function getecho($t) { 
	$t = preg_replace("/[^a-z0-9!_.-]+/", "", $t); 
	return applyBlackList(@file_get_contents ("echo/$t"));
}

function b64c($s) {
	return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64d($s) {
	return base64_decode(str_pad(strtr($s, '-_', '+/'), strlen($s) % 4, '=', STR_PAD_RIGHT),true);
}

function hsh($s) {
	$s1 = b64c(hash("sha256",$s,true));
	$s1=str_replace("-","A",$s1);
	$s1=str_replace("_","z",$s1);
	return substr($s1,0,20);
}

function pointSend($msg,$authname,$addr) {
	$goodmsg=explode("\n",b64d($msg));
	
	$echo=$goodmsg[0];
	if(!checkEcho($echo)) die("error: wrong echo");
	
	$receiver=$goodmsg[1];
	$subj=$goodmsg[2];
	$rep=$goodmsg[4];
	$time=time();
	$norep=0;

	$othermsg="";

	for($i=5;$i<count($goodmsg);$i++) {
		if($i==(count($goodmsg)-1)) {
			$othermsg.=$goodmsg[$i];
		} else {
			$othermsg.=$goodmsg[$i]."\n";
		}
	}

	if(substr($rep,0,7)=="@repto:") {
		$repto=substr($rep,7);
	} else {
		$repto="";
	}
	
	if(!$repto) {
		$norep=1;
	}

	if($norep) {
		if(!empty($othermsg)) {
			$othermsg=$rep."\n".$othermsg;
		} else {
			$othermsg=$rep;
		}
	}

	if(empty($subj) or empty($othermsg)) {
		die("error: empty message or subject!");
	}

	$sent=msg_to_ii($echo,$othermsg,$authname,$addr,$time,$receiver,$subj,$repto);
	if($sent) {
		echo "msg ok:".$sent;
	}
}

function msg_to_ii($echo,$msg,$username,$addr,$time,$receiver,$subj,$repto) {
	if(!checkEcho($echo)) die("wrong echo");
	if($repto) {
		$repto="/repto/".$repto;
	}

	$msgwrite="ii/ok$repto
$echo
$time
$username
$addr
$receiver
$subj\n\n$msg";
	$msgid=hsh($msgwrite);

	return savemsg($msgid, $echo, $msgwrite);
}

function validatemsg($m) {
	$msgparts = explode("\n", $m);
	if (count($msgparts) < 9) return false;

	$mesg = implode("\n", array_slice($msgparts, 8));
	
	for($i=0;$i<7;$i++) {
		if(strlen($msgparts[$i])==0) {
			return false;
		}
	}
	if(strlen($mesg)==0) return false;
	return true;
}

function savemsg($h,$e,$t) {
	global $savemsgOverride, $usemysql, $msgtextlimit;
	if (!validatemsg($t)) {
		echo "invalid message: ".$h."\n";
		return 0;
	}
	if(!checkEcho($e)) {
		echo "error: wrong echo ".$e."\n"; 
		return 0;
	}
	if(strlen($t)>$msgtextlimit) {
		echo "error: msg big\n";
		return 0;
	}
	if(isBlackListed($h)) {
		echo "error: msgid is blacklisted: ".$h."\n";
		return 0;
	}
	if(checkHash($h)) {
		if(!file_exists('msg/'.$h) or $savemsgOverride==true) {
			if($usemysql) {
				global $db;
				if($db) {
					$message=$db->prepareForInsert($t,$h);
					$db->insertData($message);
				}
			} else {
				$fp = fopen('msg/'.$h, 'wb'); fwrite($fp, $t); fclose($fp);
			}
			$fp = fopen('echo/'.$e, 'ab'); fwrite($fp, $h."\n"); fclose($fp);
			return $h;
		} else {
			echo "error: '".$h."' this message exists\n";
			return 0;
		}
	} else {
		echo "error: incorrect msgid\n";
		return 0;
	}
}

function displayEchoList($echos=null, $counter=false, $descriptions=false) {
	global $echolist;

	$public_echolist_assoc=[];

	foreach ($echolist as $line) {
		$public_echolist_assoc[$line[0]]=$line[1];
	}

	if ($echos===null) {
		$echos=array_keys($public_echolist_assoc);
	}

	foreach ($echos as $echo) {
		if(checkEcho($echo)) {
			echo $echo;
		}

		if ($counter) {
			echo ":".(count(explode("\n",getecho($echo)))-1);
		}

		if ($descriptions) {
			echo ":";
			if (isset($public_echolist_assoc[$echo])) {
				echo $public_echolist_assoc[$echo];
			}
		}

		echo "\n";
	}
}

?>
