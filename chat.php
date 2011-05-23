<?php
/*
 * 81chat (HighChat) by na2hiro http://81.la
 * https://github.com/na2hiro/81chat
 */
header("Access-Control-Allow-Origin: *");
if($_SERVER["REMOTE_ADDR"]=="61.27.73.10") printError("damn!");

require("pass.php");
define("DELETE_SPAN",60);
define("LOG_MAX",1000);
define("LOG_DEFAULT",30);
define("DB_USER_TABLE", "testchatuser");
define("DB_LOG_TABLE", "testchat");
$now=time();

$conn = mysql_connect('localhost', DB_USER, DB_PASS) or die(mysql_error());
mysql_select_db('chat') or die(mysql_error());
mysql_query("SET NAMES utf8");
if(isset($_POST['sessionid']) && $_POST['sessionid']!=""){
	session_id($_POST['sessionid']);
}

session_start();
$myid=isset($_SESSION['userid'])?$_SESSION['userid']:NULL;
$retarray=array('error'=>false);//レスポンスJSON用配列

if($myid!=NULL){
	//myidの正当性チェック
	$sql="SELECT COUNT(*) as hoge FROM ".DB_USER_TABLE." WHERE id = {$myid}";
	$res=mysql_query($sql);
	if($res==false) printError(mysql_error());
	$row=mysql_fetch_assoc($res);
	if($row['hoge']==0){
		//入室していなかったらnullにする
		$myid=NULL;
	}
}

if(isset($_POST['login'])){
	//入室する
	checkreferer();
	$name=utf84byte(htmlspecialchars($_POST['login']));
	if($name=="") printError("name must not be empty");
	$ua=mysql_real_escape_string($_SERVER['HTTP_USER_AGENT']);
	$ips=explode(".", $_SERVER["REMOTE_ADDR"]);
	$ip=(($ips[0]*256+$ips[1])*256+$ips[2])*256+$ips[3];
	$sql="INSERT INTO ".DB_USER_TABLE." VALUES(NULL, '".mysql_real_escape_string($name)."', {$now}, {$ip}, '{$ua}')";
	mysql_query($sql) or printError("sql error: 1");
	$myid=(int)mysql_insert_id();
	$retarray['id']=$myid;
	$_SESSION['userid']=$myid;
	comment("<span class='in'>■入室通知</span>", "「".$name."」さんが入室", $ip);

}else if(isset($_POST['logout']) && $myid!=NULL){
	//退室する
	checkreferer();
	$sql="SELECT ip, name FROM ".DB_USER_TABLE." WHERE id = {$myid}";
	$res=mysql_query($sql);
	$row = mysql_fetch_assoc($res);
	$ip=$row['ip'];
	$name=$row['name'];

	$sql="DELETE FROM ".DB_USER_TABLE." WHERE id = {$myid}";
	mysql_query($sql);
	unset($_SESSION['userid']);
	$myid=NULL;
	comment("<span class='out'>■退室通知</span>", "「".$name."」さんが退室", $ip);

}else if(isset($_POST['comment'])){
	//発言する
	checkreferer();
	if($myid==NULL) printError("please log in");
	if(!is_numeric($myid)) printError("userid is wrong");
	$sql="SELECT name, ip FROM ".DB_USER_TABLE." WHERE id = {$myid}";
	$res=mysql_query($sql);
	$row = mysql_fetch_assoc($res);
	if($row==false) printError("please log in");

	comment($row['name'], utf84byte(htmlspecialchars($_POST['comment'])), $row['ip']);
}
if($myid!=NULL){
	//入室している場合は生存確認のため日時更新
	$sql="UPDATE ".DB_USER_TABLE." SET last = {$now} WHERE id = {$myid}";
	mysql_query($sql) or printError("sql error: 2");
}

$retarray['myid']=$myid;


$users=array();
//クライアントが持っているユーザーリストを解釈
try{
	$users=json_decode($_REQUEST['userlist']);
}catch(Exception $e){
	$users=array();
}

//DELETE_SPAN秒以上生存確認がない場合は失踪通知を出してデータベースから削除
$deletebefore=$now-DELETE_SPAN;
$sql="SELECT name, ip FROM ".DB_USER_TABLE." WHERE last < {$deletebefore}";
$res=mysql_query($sql);
while ($row = mysql_fetch_assoc($res)) {
	comment("<span class='out'>■失踪通知</span>", "「".$row['name']."」さんいない", $row['ip']);
}
$sql="DELETE FROM ".DB_USER_TABLE." WHERE last < {$deletebefore}";
mysql_query($sql);

//ユーザーリスト更新
$retarray['newusers']=array();
$sql="SELECT * FROM ".DB_USER_TABLE." ORDER BY id";
$res=mysql_query($sql);
$retarray['userlist']=array();

while ($row = mysql_fetch_assoc($res)) {
	if(in_array($row['id'], $users)===false){
		//クライアントが知らない人は，newusersに追加
		$retarray['newusers'][]=array(
			'id'=> (int)$row['id'],
			'name'=> $row['name'],
			'ip'=> (int)$row['ip'],
			'ua'=> $row['ua']
		);
	}
	//ユーザーリストにidを追加
	$retarray['userlist'][]=(int)$row['id'];
}

//クライアントが持っている最後の発言id
$id=$_REQUEST['last'];
if(!is_numeric($_REQUEST['last'])){
	//なかったら
	$sql="SELECT COUNT(*) as hoge FROM ".DB_LOG_TABLE;
	$res=mysql_query($sql);
	if($res==false) printError(mysql_error());
	$row=mysql_fetch_assoc($res);
	if(is_numeric($_REQUEST['recent'])){
		//LOG_MAXを超えないrecent件数を得る
		$logs=max(0, min($_REQUEST['recent'], LOG_MAX));
	}else{
		//指定がない場合LOG_DEFAULT件数を得る
		$logs=LOG_DEFAULT;
	}
	//ログ件数から取得済みのidを計算
	$id=max(0, $row['hoge']-$logs);
}

//発言取得
$sql="SELECT * FROM ".DB_LOG_TABLE." WHERE id > {$id} ORDER BY id LIMIT ".LOG_MAX;
$res=mysql_query($sql);
$retarray['newcomments']=array();
$sid="";
while ($row = mysql_fetch_assoc($res)) {
	$retarray['newcomments'][]=array(
		'name'=>$row['name'],
		'comment'=>$row['comment'],
		'date'=>(int)$row['date'],
		'ip'=>(int)$row['ip']
	);
	$id=$row['id'];
	if($sid==""){
		$sid=$row['id'];
	}
}
$retarray['sessionid']=session_id();
$retarray['startid']=(int)$sid;
$retarray['lastid']=(int)$id;
$echo=json_encode($retarray);
if($_POST['callback']){
	echo $_POST['callback']."(".$echo.")";
}else{
	echo $echo;
}

//JSON形式でエラーを吐き出す
function printError($message){
	$echo=json_encode(Array('error'=>true, 'errormessage'=>$message));
	if($_POST['callback']){
		echo $_POST['callback']."(".$echo.")";
	}else{
		echo $echo;
	}
	exit;
}

//名前，発言，ipアドレスを指定して発言する
function comment($name, $comment, $ip){
	global $conn, $now;
	$name=mysql_real_escape_string($name);
	$comment=mysql_real_escape_string($comment);
	$sql="INSERT INTO ".DB_LOG_TABLE." VALUES(NULL, {$now}, '{$name}', '{$comment}', {$ip})";
	mysql_query($sql) or printError("sql error: 3");
}
//多バイトUTFを受け取る
function utf84byte($str){
    $ret= preg_replace_callback("/([\\xF0-\\xF7])([\\x80-\\xBF])([\\x80-\\xBF])([\\x80-\\xBF])/",function ($match){
	    $unicode = ((ord($match[1])&7)<<18)|((ord($match[2])&63)<<12)|((ord($match[3])&63)<<6)|(ord($match[4])&63);
	    return "&#x".dechex($unicode).";";
	},$str);
    return $ret;
}
//CSRF対策のリファラチェック
function checkreferer(){
	
//	if(!preg_match('!^'.REFERER_MUST_START.'!', $_SERVER['HTTP_REFERER'])
//			&& !preg_match('!^http://mamesoft.jp/81chat/!', $_SERVER['HTTP_REFERER'])) printError("referer must be ".REFERER_MUST_START.", but got ".$_SERVER['HTTP_REFERER']);
}
