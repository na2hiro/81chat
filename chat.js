var disip="";

function HighChat(){
	document.f2.n.disabled = false;		//フォーム名前
	document.f2.n.style.backgroundColor = "#ffffff";
	document.f2.login.disabled = false;	//ボタン入退室
	document.f.c.disabled = true;		//フォームメッセージ
	document.f.c.style.backgroundColor = "#dddddd";
	//document.f.hatsugen.disabled = true;	//ボタン発言
	document.f2.n.value=this.GetCookie("name_chat");
	var vol=this.GetCookie("volume_chat");
	document.getElementById("audiocontrols").value=vol;

	this.volume(vol);
}
HighChat.prototype={
	note: function(){
		alert("■仕様■\n\
・名前の字数制限\n\
・更新は10秒毎\n\
・更新と発言が重なると2行表示（他人には1行に見えるので気にしない）\n\
・ページを去って約一分で、入室者一覧から名前が消えます。\n\
・IEではデザインが崩れるなどの問題が起こるのでログインしないように\n\
・この仕様を開いている間はチャットが更新されない\n\
・入室者一覧にオンマウスするとIPを表示\n\
・GyazoのURLが含まれていたら自動でリンクを張ります\n\
・[s]文字[/s] で抹消線([/s]は省略可)\n\
・#0123などで0123番の正男へのリンク\n\
・URLは自動リンク\n\
・[small]以降が小さい文字([/small]なし)");
	},
	sound: (function(){
		var audio;
		var soundSource=[
			["./sound.ogg", "audio/ogg"],
			["./sound.mp3", "audio/mp3"],
			["./sound.wav", "audio/wav"]
		];
		try{
			audio = new Audio("");
			audio.removeAttribute("src");
			for(var i=0,l=soundSource.length; i<l; i++){
				var thissource = soundSource[i];
				var source = document.createElement("source");
				source.src=thissource[0];
				source.type=thissource[1];
				audio.appendChild(source);
			}
		}catch(e){
			audio={
				play: function(){}
			};
		}
		return audio;
	})(),
	volume: function(value){
		this.SetCookie("volume_chat", value);
		this.sound.volume=value*0.01;
	},
        startid: null,  //最も古いログのid
	lastid: null,
	userdata: {},
	userlist: [],
	loginid: null,
	check: function(func){
		this.gid("status").innerHTML = "<b>更新中</b>";
		this.submit("check", func);
	},

	writeuserlist: function(userlist){
		var node = this.gid("disp");
		var output="<ul id='inlist'>";
		var cntrom="";
		var cnt=0;
		for(var i=0, l=userlist.length; i<l; i++){
			var thisid=userlist[i];
			var thisdata=this.userdata[thisid];
//			if(tmp[0]=="(ROM)"){
//				cntrom++;
//			}else{
				output+="<li title='"+thisdata.ip.join(".")+" / "+thisdata.ua+"'><span>"+thisdata.name+"</span></li>";
				cnt++;
//			}
		}
    	node.innerHTML ="<span style='background-color:#fff'>入室"+cnt+(cntrom?"(ROM"+cntrom+")":"")+"</span>"+output+"</ul>";
	},

	post: function(message, func){
//		alert(message+"を送信");
		if(message=="") return;
		this.submit("comment="+encodeURIComponent(message)+(this.lastid==null?"":"&last="+this.lastid), func);
		document.f.c.value = "";
	},

	standardResponse: function(responseText){
		try{
			var obj=JSON.parse(responseText);
		}catch(e){
			alert("パースエラー: "+responseText);
			return;
		}
		if(obj.error!=false){
			this.gid("status").innerHTML="エラー! "+obj.errormessage;
		}
		this.write(obj.newcomments);
                if(!this.startid || (obj.startid && this.startid>obj.startid))this.startid=obj.startid;
                this.lastid=obj.lastid;
                this.userlist=obj.userlist;
		if(obj.myid==null){
			if(this.loginid!=null){//自分のidが入っててnullが与えられた場合はログアウト
				this.loginid=null;
//				this.logout();
				this.inout(document.f2.n.value);
				return;
			}
		}else{
			if(this.loginid==null){//自分のidが空っぽでidが与えられた場合ログイン(自動継続ログイン)
				this.loginid=obj.myid;
				this.login();
			}
		}

		for(var i=0, l=obj.newusers.length; i<l; i++){
			var thisuser=obj.newusers[i];
			this.userdata[thisuser.id]={
				name: thisuser.name,
				last: thisuser.last,
				ip: this.getIpByNum(thisuser.ip),
				ua: thisuser.ua
			}
		}
		this.writeuserlist(obj.userlist);
		this.gid("status").innerHTML = "更新済";
		this.lastObj=obj;
	},

	submit: function(send, func,to){    //to:送り先
		var http = this.LetsHTTP();
		http.parent=this;
		if(!http)return;
                if(!to)to="chat.php";

		http.onreadystatechange = func || function(){
			if(this.readyState == 4 && this.status == 200){
				this.parent.standardResponse(this.responseText);
			}
		};
		http.parent=this;
		http.open("POST", to, true);
		http.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		var send2=send+"&userlist="+JSON.stringify(this.userlist)+"&last="+this.lastid;
//		alert(send2)
		http.send(send2);
	},

	getColorByIp: function(ip){
		return "rgb("+Math.floor(parseInt(ip[0])*0.75)+", "+
				Math.floor(parseInt(ip[1])*0.75)+", "+
				Math.floor(parseInt(ip[2])*0.75)+")";
	},

        //mode:falseなら従来通り（上に付け足し）　trueならもっと読むモード（下に付け足し）
	write: function(comments,mode){
		if(comments.length==0)return;
                if(mode)comments=comments.reverse();
                
		for(var i=0, l=comments.length;i<l;i++){
			var line=comments[i];
			var ip=this.getIpByNum(line.ip);
			var colorna2=this.getColorByIp(ip);

			ip=ip.join(".");
			if(ip==disip) continue;
			var D = new Date(line.date*1000).formatTime();
			if(/NaN/.test(D)){continue}

			var newline1 = document.createElement("dt");
			newline1.style.color=colorna2;
			newline1.className="name";
			newline1.innerHTML=line.name;

			var newline2 = document.createElement("dd");
			newline2.style.color=colorna2;
			newline2.innerHTML=[
				line.comment
					.replace(/(http:\/\/gyazo.com\/[\x21\x23-\x3b\x3d-\x7e]+?\.png)/ig, "<a href=\"$1\" target='_blank'>[Gyazo]</a>")
					.replace(/([^">])http(s?):\/\/([^\s\[<　]+)/gi, "$1<a href=\"http$2://$3\" target='_blank'>http$2://$3</a>")
					.replace(/^http(s?):\/\/([^\s\[<　]+)/gi, "<a href=\"http$1://$2\" target='_blank'>http$1://$2</a>")
					.replace(/\[small\](.+?)$/ig, "<small>$1</small>")
					.replace(/\[math\]([ -~]+?)\[\/math\]/ig, "<img src=\"http://81.la/cgi-bin/mimetex.cgi?$1\" title=\"$1\" alt=\"$1\">")
					.replace(/\[s\](.+?)\[\/s\]/ig, "<s>$1</s>")
					.replace(/\[s\](.+?)$/ig, "<s>$1</s>")
					.replace(/#0(\d)(\d\d)/ig, "<a href='/m/$1/$2.php' target='_blank'>#0$1$2</a>")
					.replace(/#([1-9]\d)(\d\d)/ig, "<a href='/m/$1/$2.php' target='_blank'>#$1$2</a>"),
				" <span class='small'>(",D,", ",ip,")</span>"
			].join("");

                        if(!mode){
                            //上に付け足し
                            this.gid("log").insertBefore(newline2,this.gid("log").firstChild);
                            this.gid("log").insertBefore(newline1,this.gid("log").firstChild);
                        }else{
                            //下に付け足し
                            this.gid("log").appendChild(newline1);
                            this.gid("log").appendChild(newline2);
                        }
		}
		this.sound.play();
	},

	inout: function(name) {
		if(document.f2.n.value == "" || document.f2.n.value.length>25){return}

		if(this.loginid==null) {
			this.submit("login="+encodeURIComponent(name), function(){
				if(this.readyState == 4 && this.status == 200){
					try{
						var obj=JSON.parse(this.responseText);
					}catch(e){
						alert(e+": "+this.responseText);
						return;
					}
					if(obj.error!=false){
						this.parent.gid("status").innerHTML="エラー! "+obj.errormessage;
						return;
					}
					this.parent.login();
					this.parent.SetCookie("name_chat", document.f2.n.value);
					this.parent.loginid=obj.id;
					this.parent.standardResponse(this.responseText);
					this.parent.gid("status").innerHTML = "入室しました: "+obj.id;
				}
			});


		} else {
			this.submit("logout=1", function(){
				if(this.readyState == 4 && this.status == 200){
					try{
						var obj=JSON.parse(this.responseText);
					}catch(e){
						alert(e+": "+this.responseText);
						return;
					}
					if(obj.error!=false){
						this.parent.gid("status").innerHTML="エラー! "+obj.errormessage;
						return;
					}
					this.parent.logout();
					this.parent.loginid=null;
					this.parent.standardResponse(this.responseText);
				}
			});
		}
	},
        //もっと読む
        motto: function(){
            var th=this;
            if(!this.startid)return;
            this.submit("motto="+this.startid, function(){
                if(this.readyState == 4 && this.status == 200){
                        this.parent.mottoResponse(this.responseText);
                }
            },"chalog.php");
            
           
        },
        //もっと読む用レスポンスを返す
        mottoResponse:function(responseText){
		try{
			var obj=JSON.parse(responseText);
		}catch(e){
			alert("パースエラー: "+responseText);
			return;
		}
		if(obj.error!=false){
			this.gid("status").innerHTML="エラー! "+obj.errormessage;
		}
		this.write(obj.newcomments,true);
                if(!this.startid || this.startid>obj.startid)this.startid=obj.startid;
	},
	login: function(name){
		if(name!=null) document.f2.n.value=name;
		document.f2.n.disabled = true;		//フォーム名前
		document.f2.n.style.backgroundColor = "#dddddd";
		document.f2.login.disabled = false;	//ボタン入退室
		document.f.c.disabled = false;		//フォームメッセージ
		document.f.c.style.backgroundColor = "#ffffff";
		document.f2.login.value = "退室";

		document.f.c.value = "";
		document.f.c.focus();

	},
	logout: function(){
		document.f2.n.disabled = false;		//フォーム名前
		document.f2.n.style.backgroundColor = "#ffffff";
		document.f2.login.disabled = false;	//ボタン入退室
		document.f.c.disabled = true;		//フォームメッセージ
		document.f.c.style.backgroundColor = "#dddddd";
		document.f2.login.value = "入室";

		document.f.c.value = "";
		document.f2.n.focus();
	},

	getIpByNum: function(num){
		var ret=[];
		for(var i=3; i>=0; i--){
			ret[i]=num%256;
			num=parseInt(num/256);
		}
		return ret;
	},

	zeroFill: function(num,keta){
		num=String(num);
		while(1){
			if(num.length>=keta) break;
			num="0"+num;
		}
		return num;
	},

	//クッキー関連開始 GetCookie("名前");で値を取得、SetCookie("名前", 値);
	GetCookie: function(key){
		var tmp = document.cookie + ";";
		var index1 = tmp.indexOf(key, 0);
		if(index1 != -1){
			tmp = tmp.substring(index1,tmp.length);
			var index2 = tmp.indexOf("=",0) + 1;
			var index3 = tmp.indexOf(";",index2);
			return(unescape(tmp.substring(index2,index3)));
		}
		return("");
	},

	SetCookie: function(key, val){
		document.cookie = key + "=" + escape(val) + ";expires=Fri, 31-Dec-2030 23:59:59;";
	},

	LetsHTTP: function()	//Let's HTTP!!
	{
		var http = null;
		try{
			http = new XMLHttpRequest();
		}catch(e){
			try{
				http = new ActiveXObject("Msxml2.XMLHTTP");
			}catch(e){
				try{
					http = new ActiveXObject("Microsoft.XMLHTTP");
				}catch(e){
					return null;
				}
			}
		}
		return http;
	},
	gid: function(id){
		return document.getElementById(id);
	}
}

window.document.onkeydown = function (ev){
	var e = ev||event;
	if (e.keyCode == 116){
		e.keyCode = null;
		return false;
	}
}

String.prototype.mReplace = function(pat,flag){
	var temp = this;
	if(!flag){flag=""}
	for(var i in pat){
		var re = new RegExp(i,flag);
		temp = temp.replace(re,pat[i])
	}
	return temp;
};
//日付の書式
Date.prototype.format = "yyyy-mm-dd HH:MM:SS";
Date.prototype.formatTime = function(format){
	var yy;
	var o = {
		yyyy : ((yy = this.getYear()) < 2000)? yy+1900 : yy,
		mm   : this.getMonth() + 1,
		dd   : this.getDate(),
		HH   : this.getHours(),
		MM   : this.getMinutes(),
		SS   : this.getSeconds()
	}
	for(var i in o){
		if (o[i] < 10) o[i] = "0" + o[i];
	}
	return (format) ? format.mReplace(o) : this.format.mReplace(o);
}