<?php
header("Access-Control-Allow-Origin: *");

require("pass.php");
define("LOG_MAX",10000);
define("CHALOG_PAGE",5000); //chalogで1ページあたりの件数
define("DB_LOG_TABLE", "testchat");

$conn = mysql_connect('localhost', DB_USER, DB_PASS) or die(mysql_error());
mysql_select_db('chat') or die(mysql_error());
mysql_query("SET NAMES utf8");

$retarray=array('error'=>false);
$retarray['newcomments']=array();

if(isset($_REQUEST['chalog'])){
    //件数指定
    $start= ((int)$_REQUEST['chalog'] -1)*CHALOG_PAGE;
    $sql = "SELECT * FROM ".DB_LOG_TABLE." ORDER BY id LIMIT {$start},".CHALOG_PAGE;
    $res=mysql_query($sql);
    while ($row = mysql_fetch_assoc($res)) {
            $retarray['newcomments'][]=array(
                    'id'=>$row['id'],
                    'name'=>$row['name'],
                    'comment'=>$row['comment'],
                    'date'=>(int)$row['date'],
                    'ip'=>(int)$row['ip']
            );
    }
    
    //件数数える
    $sql="SELECT COUNT(*) as hoge FROM ".DB_LOG_TABLE;
    $res=mysql_query($sql);
    if($res==false) die(mysql_error());
    $row=mysql_fetch_assoc($res);

    $retarray['maxpage']=ceil((int)$row['hoge'] / CHALOG_PAGE);
}else if(isset($_REQUEST['starttime']) || isset($_REQUEST['endtime'])){
    //日時指定
    $starttime=0;$endtime=time();
    if(isset($_REQUEST['starttime'])){
        $starttime=(int)$_REQUEST['starttime'];
    }
    if(isset($_REQUEST['endtime'])){
        $starttime=(int)$_REQUEST['endtime'];
    }
    
    $page=1;
    if(isset($_REQUEST['page'])){
        $page=((int)$_REQUEST['page']-1)*CHALOG_PAGE;
    }
    
    $sql = "SELECT * FROM ".DB_LOG_TABLE." WHERE {$starttime} <= date AND date <= {$endtime} ORDER BY id LIMIT {$page},".LOG_MAX;
    $res=mysql_query($sql);
    while ($row = mysql_fetch_assoc($res)) {
            $retarray['newcomments'][]=array(
                    'id'=>$row['id'],
                    'name'=>$row['name'],
                    'comment'=>$row['comment'],
                    'date'=>(int)$row['date'],
                    'ip'=>(int)$row['ip']
            );
    }
    
    //件数数える
    $sql="SELECT COUNT(*) as hoge FROM ".DB_LOG_TABLE;
    $res=mysql_query($sql);
    if($res==false) die(mysql_error());
    $row=mysql_fetch_assoc($res);

    $retarray['maxpage']=ceil((int)$row['hoge'] / CHALOG_PAGE);
    
}else if(isset($_REQUEST['lastid'])){
    //最後のIDを取得
    $sql = "SELECT id FROM ".DB_LOG_TABLE." ORDER BY id DESC LIMIT 1";
    $res=mysql_query($sql);
    $row=mysql_fetch_assoc($res);
    $lastid=(int)$row['id'];
    
    $retarray['lastid']=$lastid;
}else{
    $retarray['error']=true;
}
$echo=json_encode($retarray);
if($_REQUEST['callback']){
	echo $_REQUEST['callback']."(".$echo.")";
}else{
	echo $echo;
}
?>
