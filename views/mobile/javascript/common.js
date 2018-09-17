var btDialog = {
	alert : function(message){
		$('#btdialog-alert').off('hidden.bs.modal');
		$('#btdialog-alert-title').text('提示：');
		$('#btdialog-alert-content').text(message);
		$('#btdialog-alert').modal();
	},
	tips: function(message){
		$('#btdialog-tips-content').text(message);
		$('#btdialog-tips').modal('show');
		window.setTimeout("$('#btdialog-tips').modal('hide');", 5000);
	},
	//dyg_lzq 20160318 增加loadding模块
	loadding: function(message){
		var message = message ? message : '正在执行，请稍后...';
		$('#btdialog-loading-content').text(message);
		$('#btdialog-loading').modal('show');
	},
	open: function(iframSrc, option){
		var $ifram = $('<iframe id="iframeWin" width="100%" height="300px" frameborder="0"></iframe>');
		$('#btdialog-alert-content').html($ifram);
		$ifram.attr("src", iframSrc);
		$('#btdialog-alert-title').text(option.title);
		$('#btdialog-alert-btn').text(option.okVal);
		$('#btdialog-alert').modal('show');
		if (option.ok) {
			$('#btdialog-alert-btn').on('click', option.ok);
		}
		$('#btdialog-alert').on('hidden.bs.modal', {val:option.val}, option.callback);
	},

	confirm: function(message, yesHandler, noHandler){
		$('#btdialog-confirm-content').text(message);
		$('#btdialog-confirm').modal('show');
		if (typeof(noHandler) != "undefined")
		{
			$('#btdialog-confirm').on('hidden.bs.modal', function (e) {
				eval(noHandler);
			});
		}
		if (typeof(yesHandler) != "undefined")
		{
			$('#confirm-btn').click(function(){
				eval(yesHandler);
				$('#btdialog-confirm').modal('hide');
			});
		}		
	}


};

var art = {
	dialog : {
		tips : function (msg) {
			btDialog.tips(msg);
		}
	}
};

//全选
function selectAll(nameVal)
{
	//获取复选框的form对象
	var formObj = $("form:has(:checkbox[name='"+nameVal+"'])");

	//根据form缓存数据判断批量全选方式
	if(formObj.data('selectType')=='' || formObj.data('selectType')==undefined)
	{
		$("input:checkbox[name='"+nameVal+"']:not(:checked)").prop('checked',true);
		formObj.data('selectType','all');
	}
	else
	{
		$("input:checkbox[name='"+nameVal+"']").prop('checked',false);
		formObj.data('selectType','');
	}
}
/**
 * @brief 获取控件元素值的数组形式
 * @param string nameVal 控件元素的name值
 * @param string sort    控件元素的类型值:checkbox,radio,text,textarea,select
 * @return array
 */
function getArray(nameVal,sort)
{
	//要ajax的json数据
	var jsonData = new Array;

	switch(sort)
	{
		case "checkbox":
		$('input:checkbox[name="'+nameVal+'"]:checked').each(
			function(i)
			{
				jsonData[i] = $(this).val();
			}
		);
		break;
	}
	return jsonData;
}
window.loadding = function(mess){btDialog.loadding(mess);}
window.unloadding = function(){art.dialog({"id":"loadding"}).close();}
window.tips = function(mess){btDialog.tips(mess);}
window.realAlert = window.alert;
window.alert = function(mess){btDialog.alert(mess);}


window.realConfirm = window.confirm;
window.confirm = function(mess,bnYes,bnNo)
{
	if(bnYes == undefined && bnNo == undefined)
	{
		return eval("window.realConfirm(mess)");
	}
	else
	{
		btDialog.confirm(mess,bnYes,bnNo);
	}
}
/**
 * @brief 删除操作
 * @param object conf
	   msg :提示信息;
	   form:要提交的表单名称;
	   link:要跳转的链接地址;
 */
function delModel(conf)
{
	var ok = null;            //执行操作
	var msg   = '确定要删除么？';//提示信息

	if(conf)
	{
		if(conf.form)
		{
			var ok = 'formSubmit("'+conf.form+'")';
		}
		else if(conf.link)
		{
			var ok = 'window.location.href="'+conf.link+'"';
		}

		if(conf.msg)
		{
			var msg   = conf.msg;
		}
		if(conf.name && checkboxCheck(conf.name,"请选择要操作项") == false)
		{
			return '';
		}
	}
	if(ok==null && document.forms.length >= 1)
		var ok = 'document.forms[0].submit();';

	if(ok!=null)
	{
		window.confirm(msg,ok);
	}
	else
	{
		alert('删除操作缺少参数');
	}
}

//根据表单的name值提交
function formSubmit(formName)
{
	$('form[name="'+formName+'"]').submit();
}

//根据checkbox的name值检测checkbox是否选中
function checkboxCheck(boxName,errMsg)
{
	if($('input[name="'+boxName+'"]:checked').length < 1)
	{
		alert(errMsg);
		return false;
	}
	return true;
}

//倒计时
var countdown=function()
{
	var _self=this;
	this.handle={};
	this.parent={'second':'minute','minute':'hour','hour':""};
	this.add=function(id)
	{
		_self.handle.id=setInterval(function(){_self.work(id,'second');},1000);
	};
	this.work=function(id,type)
	{
		if(type=="")
		{
			return false;
		}

		var e = document.getElementById("cd_"+type+"_"+id);
		var value=parseInt(e.innerHTML);
		if( value == 0 && _self.work( id,_self.parent[type] )==false )
		{
			clearInterval(_self.handle.id);
			return false;
		}
		else
		{
			e.innerHTML = (value==0?59:(value-1));
			return true;
		}
	};
};





/*实现事件页面的连接*/
function event_link(url)
{
	window.location.href = url;
}

//延迟执行
function lateCall(t,func)
{
	var _self = this;
	this.handle = null;
	this.func = func;
	this.t=t;

	this.execute = function()
	{
		_self.func();
		_self.stop();
	}

	this.stop=function()
	{
		clearInterval(_self.handle);
	}

	this.start=function()
	{
		_self.handle = setInterval(_self.execute,_self.t);
	}
}

/**
 * 进行商品筛选
 * @param url string 执行的URL
 * @param callback function 筛选成功后执行的回调函数
 */
function searchGoods(url,callback)
{
	var step = 0;
	art.dialog.open(url,
	{
		"id":"searchGoods",
		"title":"商品筛选",
		"okVal":"执行",
		"button":
		[{
			"name":"后退",
			"callback":function(iframeWin,topWin)
			{
				if(step > 0)
				{
					iframeWin.window.history.go(-1);
					this.size(1,1);
					step--;
				}
				return false;
			}
		}],
		"ok":function(iframeWin,topWin)
		{
			if(step == 0)
			{
				iframeWin.document.forms[0].submit();
				step++;
				return false;
			}
			else if(step == 1)
			{
				var goodsList = $(iframeWin.document).find('input[name="id[]"]:checked');

				//添加选中的商品
				if(goodsList.length == 0)
				{
					alert('请选择要添加的商品');
					return false;
				}
				//执行处理回调
				callback(goodsList);
				return true;
			}
		}
	});
}