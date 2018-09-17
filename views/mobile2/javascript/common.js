//复写自带方法
window.loadding = function(mess){
	var mess = mess ? mess : '正在执行，请稍后...';
	layer.msg(mess, {time: 0, shade: 0.3});
}
window.unloadding = function(){
	layer.closeAll('dialog');
}
window.tips = function(mess){
	layer.msg(mess, {time: 3000, shade: false});
}
window.realAlert = window.alert;
window.alert = function(mess){
	layer.msg(mess, {time:0, btn:['确定']}, function(index){
		layer.close(index);
	});
}
window.realConfirm = window.confirm;
window.confirm = function(mess,fnYes,fnNo)
{
	if(typeof(fnYes) == "undefined" && typeof(fnNo) == "undefined")
	{
		return eval("window.realConfirm(mess)");
	}
	else
	{
		layer.confirm(mess, {icon: 0, title:'提示'}, function(index){
			//确定操作
			fnYes();
			layer.close(index);
		}, function(){
			//取消操作
			if(typeof(fnNo) != "undefined")
			{
				fnNo();
				layer.close(index);
			}
		});
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
			var ok = $('form[name="'+formName+'"]').submit();
		}
		else if(conf.link)
		{
			var ok = function(){window.location.href=conf.link;};
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