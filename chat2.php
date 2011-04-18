<?php
$ip=$_SERVER["REMOTE_ADDR"];
if($_GET['mode']=="list"){
    printCntNyushitsu();
    exit;
}else if($_POST['ichiran']){
    refreshIchiran(
    	cutBreak($_POST['n']), 
    	$ip, 
    	$_POST['ichiran'],
    	$_SERVER['HTTP_USER_AGENT']
    );
}else if($_POST['n']){
    kakikomi(
    	cutBreak($_POST['n']), 
    	cutBreak($_POST['c']), 
    	$_POST['mes'], 
    	$ip, 
    	$_SERVER['HTTP_REFERER']
    );
}else{
	$filename=1;
	while($filename<=1000){
		if(!file_exists("chalogsouko/chalog".$filename.".txt")) break;
		$filename++;
	}
	
	$array=file("chalog.txt");
	print count($array)."/";
	print "chalog".$filename."/";
	//count($array)."(<5100)行しかないので".
	/*
	$gyou=count($array);
	if($gyou<1000){
		$mess="全然達してません";
	}else if($gyou<2000){
		$mess="まだまだです";
	}else if($gyou<3000){
		$mess="半分くらい";
	}else if($gyou<4000){
		$mess="半分すぎ";
	}else{
		$mess="もうすぐかな";
	}
	*/
	if(!isset($_GET['test']) && count($array)<5100) die("まだカットしません。".$mess);
	$array=array_chunk($array,5000);
	
	for($i=0;$i<count($array)-1;$i++){
		$data=implode("",$array[$i]);
		$fh=fopen("chalogsouko/chalog".($filename+$i).".txt","w");
		fwrite($fh,$data);
		fclose($fh);
	}
	if($i>1){
		$num=$filename."~".($filename+$i-1);
	}else{
		$num=$filename;
	}
	if(isset($_GET['test'])) exit;
	$data=implode("",$array[(count($array)-1)]);
	$fh=fopen("chalog.txt","w");
	fwrite($fh,$data.time()."<><font color=blue>■chalogカット通知</font><>「chalog".$num.".txt」を切り出しました。<>127.0.0.1<>\n");
	fclose($fh);
	
	
?>
chalogカットをしました（ぇ

<?php
}

function cutBreak($text){
	$text=eregi_replace("\n"," ",$text);
	$text=eregi_replace("\r"," ",$text);
	return $text;
}

function printCntNyushitsu(){
    $handle=fopen('./chatichiran.txt', 'r');

    $cnt=0;
    while(!feof($handle)){
        $array=array();
        $array= explode("<>", chop(fgets($handle)));
        if(time()-$array[1]<60 && $array[0]!="(ROM)"){
            $cnt++;
        }
    }
    fclose($handle);

    echo "document.write(\"".$cnt."\");";
}

function refreshIchiran($name, $ip, $mode, $browser, $last=""){
	if(mb_strlen($name)>25) return;
	if($last=="") $name=htmlspecialchars($name);
	$browser=htmlspecialchars($browser);
    $handle=fopen('./chatichiran.txt', 'r+');
    flock($handle, LOCK_EX);

    $flag=0;

    while(!feof($handle)){
        $array=array();
        $array= explode("<>", chop(fgets($handle)));
        //名前が空っぽの行だったら無視
        if($array[0]==""){continue;}
        
        //名前が同じ、IPが同じ、入室中だったら時間を更新
        if($name==$array[0] && $ip==$array[2] && $mode==1){
            $array[1]=time();
            $array[3]=$browser;
            if($last!="") $array[4]=$last;
            $flag=1;
        }
        //名前がROM、IPが同じ、退室中だったら時間を更新
        if($array[0]=="(ROM)" && $ip==$array[2] && $mode==2){
            $array[1]=time();
            $flag=1;
        }
        //自分じゃない人は、60秒以内にだったらそのまま保持
        if(time()-$array[1]<60){
            $output.=$array[0]."<>".$array[1]."<>".$array[2]."<>".$array[3]."<>".$array[4]."\n";
        }
    }
    if($flag!=1 && $mode==1){
        $output.=$name."<>".time()."<>".$ip."<>".$browser."<>".$last."\n";
    }
    if($flag!=1 && $mode==2){
        $output.="(ROM)<>".time()."<>".$ip."<>".$browser."<>".$last."\n";
    }

    fseek($handle, 0);
    ftruncate($handle,0);
    fputs($handle, $output);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function kakikomi($name, $comment, $mes, $ip, $referer){
	if($comment=="") return;
	if(mb_strlen($name)>25) return;
	

    $name=htmlspecialchars($name);
    if(!$name){exit;}
    
    $handle=fopen("./chalog.txt", 'a+');
    flock($handle, LOCK_EX);
    $time=time();
    $comment=htmlspecialchars($comment);

    
    if($mes=="1"){
        $comment="「".$name."」さんが入室";
        $name="<font color=blue>■入室通知</font>";
    }else if($mes=="2"){
        $comment="「".$name."」さんが退室";
        $name="<font color=blue>■退室通知</font>";
    }
    
    
    $minute=date("i",$time);
    $second=date("s",$time);
    if((int)$minute==0 && (int)$second<=30){
		$comment=shabetter($comment);
		$name=shabetter($name);
	}
	
    if($ip=="220.10.20.12"){
    	$zan=mktime(9,30,0,2,25,2011)-time();
    	$comment.=" <span style='color:#cccccc'>(国立前期試験まで残り</span><big>".dechex((int)($zan/3600/24))."</big><span style='color:#cccccc'>日)</span>";
    }
    if($ip=="218.42.33.220"){
    	$zan=mktime(9,0,0,2,10,2011)-time();
    	$comment.=" <span style='color:#cccccc'>(第4志望校入学試験まで残り".(int)($zan/3600)."時間".(int)($zan%(3600)/60)."分)</span>";
    }
	fputs($handle, $time."<>".$name."<>".$comment."<>".$ip."<>"."\n");
	
    flock($handle, LOCK_UN);
    fclose($handle);
    
    refreshIchiran(
    	$name, 
    	$ip, 
    	1,
    	$_SERVER['HTTP_USER_AGENT'].($mes==1?" true":" false"),
    	$time
    );
    
}//おやおや

function shabetter($value){
	$value=mb_convert_kana($value,"kh");//半角カタカナ化
	return preg_replace_callback("/[ｱ-ﾝﾞﾟｬｭｮｧｨｩｪｫ]+/u","matubiboinmatch",$value);
}
function matubiboinmatch($matches){
	return matubiboin($matches[0]);
}
function matubiboin($text){
	$arr=array(
		array("ｧ","ｱ","ｶ","ｻ","ﾀ","ﾅ","ﾊ","ﾏ","ﾔ","ﾗ","ﾜ","ｬ"),
		array("ｨ","ｲ","ｷ","ｼ","ﾁ","ﾆ","ﾋ","ﾐ",    "ﾘ","ｷﾞ"),
		array("ｩ","ｳ","ｸ","ｽ","ﾂ","ﾇ","ﾌ","ﾑ","ﾕ","ﾙ",    "ｭ"),
		array("ｪ","ｴ","ｹ","ｾ","ﾃ","ﾈ","ﾍ","ﾒ",    "ﾚ",),
		array("ｫ","ｵ","ｺ","ｿ","ﾄ","ﾉ","ﾎ","ﾓ","ﾖ","ﾛ","ｦ","ｮ"),
	);
	
	$last=mb_substr($text,mb_strlen($text)-1,1);
	if($last=="ﾞ" || $last=="ﾟ") $last=mb_substr($text,mb_strlen($text)-2,1);
	for($i=0;$i<5;$i++){
		foreach($arr[$i] as $value){
			if($value==$last){
				for($j=0;$j<5;$j++){
					$text.=$arr[$i][0];
				}
				return $text;
			}
		}
	}
	return $text;
}

function itti($string){
	$max=strlen($string);
	for($i=1;$i<$max;$i++){
		if($string[0]!=$string[$i]) return false;
	}
	return true;
}

function senkaku($string){
	$search=array("0","1","2","3","4","5","6","7","8","9");
	$replace=array("まる","ひと","ふた","さん","よん","GO!","ろく","なな","はち","きゅう");
	return str_replace($search,$replace,$string);
}

//コメントの濁点を消す
function delete_dakuten($comment){
	$kana=array(
		array("k","K"),
		array("h","H")
	);
	
	foreach($kana as $value){
		$comment=mb_convert_kana($comment,$value[0]);
		
		$max=mb_strlen($comment);
		//$newname="";//mb_substr($name,0,1);
		//for($i=0;$i<$max;$i++){
		//	$newname.=mb_substr($tmp,$i,1)."ﾞ";
		//}
		//$tmp=$newname;
		$comment=str_replace("ﾞ","",$comment);
		$comment=str_replace("ﾟ","",$comment);
		
		$comment=mb_convert_kana($comment,$value[1]."V");
		//$tmp=str_replace("゛","",$tmp);
		//$tmp=str_replace("゜","",$tmp);
	}
	return $comment;
}

function shuffle_text($text){
	$max=mb_strlen($text);
	for($i=0;$i<$max;$i++){
		$tmparr[$i]=mb_substr($text, $i, 1);
	}
	shuffle($tmparr);
	return implode("",$tmparr);
}

function cambridge_shuffle_text($text){

	//半角
	$tmp=explode(" ",$text);
	foreach($tmp as $key => $value){
		if(mb_strlen($value)>3){
			$max=mb_strlen($value);
			$middle=mb_substr($value, 1, $max-2);
			
			if(!mb_ereg("^[a-zA-Z0-9\-']+$", $middle)) continue;
			
			unset($tmparr);
			for($i=0;$i<$max-2;$i++){
				$tmparr[$i-1]=mb_substr($middle, $i, 1);
			}
			shuffle($tmparr);
			$tmp[$key]=mb_substr($value, 0, 1).implode("",$tmparr).mb_substr($value, $max-1, 1);
		}
	}
	$tmp=implode(" ",$tmp);	
	
	//全角
	$tmp=explode("　",$tmp);
	foreach($tmp as $key => $value){
		if(mb_ereg("^[ぁ-んァ-ヶー]+$", $value) && mb_strlen($value)>3){
			$max=mb_strlen($value);
			unset($tmparr);
			for($i=1;$i<$max-1;$i++){
				$tmparr[$i-1]=mb_substr($value, $i, 1);
			}
			shuffle($tmparr);
			$tmp[$key]=mb_substr($value, 0, 1).implode("",$tmparr).mb_substr($value, $max-1, 1);
		}
	}
	return implode("　",$tmp);
}

function close_bracket($text){
	$max1=substr_count($text, "(")-substr_count($text, ")");
    $max2=substr_count($text, "（")-substr_count($text, "）");
    $kakko=($max1>$max2?")":"）");
    for($i=0;$i<$max1+$max2;$i++) $text.=$kakko;
    return $text;
}
?>
