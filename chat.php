<?php
/*
 * 81chat (HighChat) by na2hiro http://81.la
 * https://github.com/na2hiro/81chat
 */
header("Access-Control-Allow-Origin: *");

require("pass.php");
define("DELETE_SPAN",60);
define("LOG_MAX",1000);
define("LOG_DEFAULT",30);
define("DB_USER_TABLE", "testchatuser");
define("DB_LOG_TABLE", "testchat");
define("DB_ROM_TABLE", "testchatrom");

$chat = new HighChat(DB_USER, DB_PASS);
try{
	$chat->run();
}catch(Exception $e){
	$chat->printError($e->getMessage());
}


class HighChat{
	protected $myid;			//自分の入室id，入室していなかったらnull
	protected $retarray=array('error'=>false);		//レスポンス用JSON
	protected $lastCommentId;	//持っている最終発言id
	protected $ip;			//自分のipアドレス(数値)
	protected $ua;			//自分のUserAgent
	protected $now;			//現在時刻
	protected $jsonp=null; //JSONPで出力(nullならJSONで出力)

	function __construct($dbuser, $dbpass){
		$this->now=time();//起動時刻
		$conn = mysql_connect('localhost', $dbuser, $dbpass) or die(mysql_error());
		mysql_select_db('chat') or die(mysql_error());
		mysql_query("SET NAMES utf8");
		
		if(isset($_POST['sessionid']) && $_POST['sessionid']!=""){
			session_id($_POST['sessionid']);
		}
		session_start();
		
		$this->jsonp=$_POST['callback'];
		$this->myid=isset($_SESSION['userid'])?$_SESSION['userid']:NULL;
		$this->ua=mysql_real_escape_string($_SERVER['HTTP_USER_AGENT']);
		$this->ip=$this->ip2num($_SERVER["REMOTE_ADDR"]);
	}
	
	function ip2num($ipstring){
		$ips=explode(".", $ipstring);
		return (($ips[0]*256+$ips[1])*256+$ips[2])*256+$ips[3];
	}

	function run(){
		//ROMのJS表示
		if($_GET['romjs']){
			$this->printRomJS();
			exit;
		}
		
		//入室しているか否か
		if($this->myid!=NULL){
			if(!$this->idExists()){
				//入室していなかったらnullにする
				$this->myid=NULL;
			}
		}
		
		if(isset($_POST['login'])){
			//入室処理
			$this->checkreferer();
			$this->login($_POST['login']);
		}else if(isset($_POST['logout']) && $this->myid!=NULL){
			//退室処理
			$this->checkreferer();
			$this->logout();
		}else if(isset($_POST['comment'])){
			//発言処理
			$this->checkreferer();
			$this->addComment($_POST['comment']);
		}
		
		//生存確認
		$this->notifAlive();
		
		//死人に失踪通知を出す
		$this->deleteDeads();
		
		//ROM一覧を得る
		$this->retarray['romlist']=$this->getRomList();
		
		//知らない人一覧を得る
		$userlist=$this->getUserList($_REQUEST['userlist']);
		$this->retarray['newusers']=$userlist['newusers'];
		$this->retarray['userlist']=$userlist['userlist'];

		//クライアントが持っている最後の発言id
		if(is_numeric($_REQUEST['last'])){
			$lastid=$_REQUEST['last'];
		}else{
			$lastid=$this->getLastCommentId($_REQUEST['recent']);
		}
		
		
		//発言取得
		$comments=$this->getComments($lastid);
		$this->retarray['startid']=$comments['startid'];
		$this->retarray['lastid']=$comments['lastid'];
		$this->retarray['newcomments']=$comments['newcomments'];
		
		
		$this->retarray['sessionid']=session_id();
		$this->retarray['myid']=$this->myid;
		$json=json_encode($this->retarray);
		$this->printJSON($json);
	}

	//入室数をJSで吐き出す
	function printRomJS(){
		$res=mysql_query("SELECT COUNT(*) as cnt FROM ".DB_USER_TABLE);
		$row = mysql_fetch_assoc($res);
?>
document.write(<?php echo $row['cnt'];?>);
<?php
	}

	//そのユーザidは入室しているか否か
	function idExists($id=null){
		if($id==null){
			$id=$this->myid;
		}
		//myidの正当性チェック
		$res=mysql_query("SELECT COUNT(*) as cnt FROM ".DB_USER_TABLE." WHERE id = {$id}");
		if($res==false) throw new Exception(mysql_error());
		$row=mysql_fetch_assoc($res);
		if($row['cnt']==0){
			return false;
		}else{
			return true;
		}
	}

	//入室処理
	function login($name){
		$name=$this->utf84byte(htmlspecialchars($name));
		if($name=="") throw new Exception("name must not be empty");
		if(!mysql_query("INSERT INTO ".DB_USER_TABLE." VALUES(NULL, '".mysql_real_escape_string($name)."', {$this->now}, {$this->ip}, '{$this->ua}')")) throw new Exception("sql error: 1");
		$_SESSION['userid']=$this->retarray['id']=$this->myid=(int)mysql_insert_id();
		$this->comment("<span class='in'>■入室通知</span>", "「".$name."」さんが入室", $this->ip);
	}
	
	//退室処理
	function logout(){
		$res=mysql_query("SELECT ip, name FROM ".DB_USER_TABLE." WHERE id = {$this->myid}")or die("変");
		$row = mysql_fetch_assoc($res);
		$ip=$row['ip'];
		$name=$row['name'];

		mysql_query("DELETE FROM ".DB_USER_TABLE." WHERE id = {$this->myid}");
		unset($_SESSION['userid']);
		$this->myid=NULL;
		$this->comment("<span class='out'>■退室通知</span>", "「".$name."」さんが退室", $ip);
	}
	
	//入室していれば発言する．
	function addComment($comment){
		if($this->myid==NULL) throw new Exception("please log in");
		if(!is_numeric($this->myid)) throw new Exception("userid is wrong");
		
		$res=mysql_query("SELECT name, ip FROM ".DB_USER_TABLE." WHERE id = {$this->myid}");
		$row = mysql_fetch_assoc($res);
		if($row==false) throw new Exception("please log in");
		
		$this->comment($row['name'], $this->utf84byte(htmlspecialchars($comment)), $row['ip']);
	}
	
	//生存確認のため日時更新
	function notifAlive(){
		if($this->myid!=NULL){
			//ログイン中
			if(!mysql_query("UPDATE ".DB_USER_TABLE." SET last = {$this->now} WHERE id = {$this->myid}")) throw new Exception("sql error: 2");
		}else{
			//ROM中
			$res=mysql_query("SELECT COUNT(*) as cnt FROM ".DB_ROM_TABLE." WHERE ip = {$this->ip}");
			$row = mysql_fetch_assoc($res);
			if($row['cnt']==0){
				//はじめて
				$res=mysql_query("INSERT INTO ".DB_ROM_TABLE." (ip, last, ua) VALUES ({$this->ip}, {$this->now}, '{$this->ua}')");
			}else{
				//既に通知済み
				$res=mysql_query("UPDATE ".DB_ROM_TABLE." SET date = {$this->now} WHERE ip = {$this->ip}");		
			}
		}
	}
	
	//失踪者を消す
	function deleteDeads(){
		//DELETE_SPAN秒以上生存確認がない場合は失踪通知を出してデータベースから削除
		$deletebefore=$this->now-DELETE_SPAN;
		$sql="SELECT name, ip FROM ".DB_USER_TABLE." WHERE last < {$deletebefore}";
		$res=mysql_query($sql);
		while ($row = mysql_fetch_assoc($res)) {
			$this->comment("<span class='out'>■失踪通知</span>", "「".$row['name']."」さんいない", $row['ip']);
		}
		
		mysql_query("DELETE FROM ".DB_USER_TABLE." WHERE last < {$deletebefore}");
		mysql_query("DELETE FROM ".DB_ROM_TABLE." WHERE last < {$deletebefore}");
	}
	
	//ROMユーザ情報を得る
	function getRomList(){
		$romlist=array();
		$res=mysql_query("SELECT * FROM ".DB_ROM_TABLE) or die("SELECT * FROM ".DB_ROM_TABLE);
		while ($row = mysql_fetch_assoc($res)) {
			$romlist[]=array('ip'=>(int)$row['ip'], 'ua'=>$row['ua']);
		}
		return $romlist;
	}
	
	//現在のリストを元に，ユーザid一覧と差分ユーザ情報を得る
	function getUserList($knownusers="[]"){
		//クライアントが持っているユーザーリストを解釈
		try{
			$knownusers=json_decode($knownusers);
		}catch(Exception $e){
			$knownusers=array();
		}
		
		//ユーザーリスト更新
		$ret['newusers']=array();
		$ret['userlist']=array();
		
		$res=mysql_query("SELECT * FROM ".DB_USER_TABLE." ORDER BY id");
		
		while ($row = mysql_fetch_assoc($res)) {
			if(in_array($row['id'], $knownusers)===false){
				//クライアントが知らない人は，newusersに追加
				$ret['newusers'][]=array(
				  'id'=> (int)$row['id'],
				  'name'=> $row['name'],
				  'ip'=> (int)$row['ip'],
				  'ua'=> $row['ua']
				);
			}
			//ユーザーリストにidを追加
			$ret['userlist'][]=(int)$row['id'];
		}
		return $ret;
	}
	
	//lastidを自動的に計算する．recent指定すればその件数の最新のものを得る．
	function getLastCommentId($recent=NULL){
		$res=mysql_query("SELECT COUNT(*) as cnt FROM ".DB_LOG_TABLE);
		if($res==false) throw new Exception(mysql_error());
		
		$row=mysql_fetch_assoc($res);
		if(is_numeric($recent)){
			//LOG_MAXを超えないrecent件数を得る
			$logs=max(0, min($recent, LOG_MAX));
		}else{
			//指定がない場合LOG_DEFAULT件数を得る
			$logs=LOG_DEFAULT;
		}
		//ログ件数から取得済みのidを計算
		return max(0, $row['cnt']-$logs);
	}
	
	//lastid以降のコメントを得る
	function getComments($lastid){
		$res=mysql_query("SELECT * FROM ".DB_LOG_TABLE." WHERE id > {$lastid} ORDER BY id LIMIT ".LOG_MAX);
		$ret['newcomments']=array();
		$startid=null;
		while ($row = mysql_fetch_assoc($res)) {
			$ret['newcomments'][]=array(
				'name'=>$row['name'],
				'comment'=>$row['comment'],
				'date'=>(int)$row['date'],
				'ip'=>(int)$row['ip']
			);
			$lastid=$row['id'];
			if($startid===null){
				$startid=(int)$row['id'];
			}
		}
		$ret['startid']=$startid;
		$ret['lastid']=(int)$lastid;
		return $ret;
	}


	//JSON形式でエラーを吐き出す
	function printError($message){
		$this->printJSON(json_encode(Array('error'=>true, 'errormessage'=>$message)));
		exit;
	}
	
	//JSONテキストを場合によりJSONPにして出力
	function printJSON($json){
		if($this->jsonp){
			echo $this->jsonp."(".$json.")";
		}else{
			echo $json;
		}
	}

	//名前，発言，ipアドレスを指定して発言する
	function comment($name, $comment, $ip){
		global $conn;
		$name=mysql_real_escape_string($name);
		$comment=mysql_real_escape_string($comment);

		if($_SERVER['HTTP_X_FORWARDED_FOR']) $comment.=" ".$_SERVER['HTTP_X_FORWARDED_FOR'];

		$sql="INSERT INTO ".DB_LOG_TABLE." VALUES(NULL, {$this->now}, '{$name}', '{$comment}', {$ip})";
		if(!mysql_query($sql)) throw new Exception("sql error: 3");
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
		if(!preg_match('!^'.REFERER_MUST_START.'!', $_SERVER['HTTP_REFERER']) && !preg_match('!^http://81c.mamesoft.jp/m/!', $_SERVER['HTTP_REFERER']))
			throw new Exception("referer must be ".REFERER_MUST_START.", but got ".$_SERVER['HTTP_REFERER']);
	}
}
