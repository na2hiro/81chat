var disip="";

function HighChat(){
	document.f2.n.disabled = false;		//フォーム名前
	document.f2.n.style.backgroundColor = "#ffffff";
	document.f2.login.disabled = false;	//ボタン入退室
	document.f.c.disabled = true;		//フォームメッセージ
	document.f.c.style.backgroundColor = "#dddddd";
	//document.f.hatsugen.disabled = true;	//ボタン発言
	document.f2.n.value=this.GetCookie("name_chat");
}
HighChat.prototype={
	lastid: null,
	userdata: {},
	userlist: [],
	loginid: null,
	sessionid: null,
	check: function(){
		this.gid("status").innerHTML = "<b>更新中</b>";
		this.submit("check");
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
	
	post: function(message){
//		alert(message+"を送信");
		if(message=="") return;
		this.submit("comment="+encodeURIComponent(message)+(this.lastid==null?"":"&last="+this.lastid));
		document.f.c.value = "";
	},
	
	submit: function(send, func){
		var http = this.LetsHTTP();
		http.parent=this;
		if(!http)return;

		http.onreadystatechange = func || function(){
			if(this.readyState == 4 && this.status == 200){
//				alert(this.responseText);
				try{
					var obj=JSON.parse(this.responseText);
				}catch(e){
					alert("パースエラー: "+this.responseText);
					return;
				}
				if(obj.error!=false){
					this.parent.gid("status").innerHTML="エラー! "+obj.errormessage;
				}
				this.parent.write(obj.newcomments);
				this.parent.lastid=obj.lastid;
				this.parent.sessionid=obj.sessionid;
				this.parent.userlist=obj.userlist;
				if(obj.myid==null){
					if(this.parent.loginid!=null){
						this.parent.loginid=null;
						this.parent.logout();
					}
				}else{
					if(this.parent.loginid==null){
						this.parent.loginid=obj.myid;
						this.parent.login();
					}
				}
				
				for(var i=0, l=obj.newusers.length; i<l; i++){
					var thisuser=obj.newusers[i];
					this.parent.userdata[thisuser.id]={
						name: thisuser.name,
						last: thisuser.last,
						ip: this.parent.getIpByNum(thisuser.ip),
						ua: thisuser.ua
					}
				}
				this.parent.writeuserlist(obj.userlist);
				this.parent.gid("status").innerHTML = "更新済";
				this.parent.lastObj=obj;
			}
		};
		http.parent=this;
		http.open("POST", "chat.php", true);
		http.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		var send2=send+"&userlist="+JSON.stringify(this.userlist)+"&last="+this.lastid+(this.sessionid!=null?"&sesisonid="+this.sessionid:"");
//		alert(send2)
		http.send(send2);
	},
	
	
	write: function(comments){
		for(var i=0, l=comments.length;i<l;i++){
			var line=comments[i];
			var newline = document.createElement("div");
			var ip=this.getIpByNum(line.ip);
	
			var colorna2=this.zeroFill(Math.floor(parseInt(ip[0])/1.33).toString(16), 2)+
						this.zeroFill(Math.floor(parseInt(ip[1])/1.33).toString(16), 2)+
						this.zeroFill(Math.floor(parseInt(ip[2])/1.33).toString(16), 2);
	
			ip=ip.join(".");
	
			if(ip==disip) continue;
	
			var D = new Date(line.date*1000).formatTime();
			if(/NaN/.test(D)){continue}
			newline.innerHTML = [
				"<span style='color:#",colorna2,"'><span class='name'>",
				line.name,
				"</span>&gt; ",
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
				"</span> ","<small><font color=#cccccc>(",D,", ",ip,")</font></small>"
			].join("");
			this.gid("log").insertBefore(newline,this.gid("log").firstChild);
		}
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
					this.parent.gid("status").innerHTML = "入室しました: "+obj.id;
					this.parent.loginid=obj.id;
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
				}
			});
		}
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