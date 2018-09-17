(function autoValidate()
{
	function addClass(node, str) {
		if(!new RegExp('(^|\\s+)'+str).test(node.className)) {
			node.className = node.className + ' ' + str;
		}
	}
	function removeClass(node, str) {
		node.className = node.className.replace(new RegExp('(^|\\s+)'+str), '');
	}
	function removeElement(_element){
		var _parentElement = _element.parentNode;
		if(_parentElement){
			_parentElement.removeChild(_element);
		}
	}
	function getRealNode(node){
		while(node && node.nodeType==3){
			node=node.nextSibling;
		}
		return node;
	}
	addEvent(window,'load',init);
	function addEvent(obj, type, fn)
	{
		if (obj.attachEvent)
		{
			obj['e'+type+fn] = fn;
			obj[type+fn] = function(){obj['e'+type+fn]( window.event );}
			obj.attachEvent('on'+type, obj[type+fn]);
		}
		else
			obj.addEventListener(type, fn, false);
	}
	function FireEvent(elem, eventName)
	{
		if (document.all)
		{
			elem.fireEvent(eventName);
		}
		else
		{
			var evt = document.createEvent('HTMLEvents');
			evt.initEvent('change',true,true);
			elem.dispatchEvent(evt);
		}
	}
	function removeEvent(obj, type, fn)
	{
		if (obj.detachEvent)
		{
			obj.detachEvent('on'+type, obj[type+fn]);
			obj[type+fn] = null;
		}
		else
		{
			obj.removeEventListener(type, fn, false);
		}
	}
	function init()
	{
		for(var i = 0; i < document.forms.length; i++)
		{
			var f = document.forms[i];
			var needsValidation = false;
			for(j = 0; j < f.elements.length; j++)
			{
				var e = f.elements[j];
				if(e.type != "text" && e.type!="password" && e.type!='select-one' && e.type!='textarea') continue;
				var pattern = e.getAttribute("pattern");
				var required = e.getAttribute("required") != null;
				if(required && !pattern)
				{
					pattern = "\\S";
					e.setAttribute("pattern", pattern);
				}
				if(pattern)
				{
					addEvent(e,'change',validateOnChange);
					needsValidation = true;
				}
			}
			if(needsValidation)
			{
				f.onsubmit = validateOnSubmit;f.setAttribute('novalidate','true');
			}
		}
	}
	/*改变输入框外框状态*/
	function changeInputOutline(node, flag){
		if(flag == true){
			removeClass(node ,"invalid-outline");
			addClass(node ,"valid-outline");
		}
		else if(flag == false){
			removeClass(node ,"valid-outline");
			addClass(node ,"invalid-outline");
		}
	}

	/*改变提示状态*/
	function changeTip(node, flag){
		if(flag == true){
			removeClass(node,"invalid-tip");
			removeClass(node,"icon-31guanbi");
			addClass(node,"valid-tip");
			addClass(node,"icon-31xuanze");
		}
		else if(flag == false){
			removeClass(node,"valid-tip");
			removeClass(node,"icon-31xuanze");
			addClass(node,"invalid-tip");
			addClass(node,"icon-31guanbi");
		}
	}
	function validateOnChange()
	{
		var textfield = this;
		var pattern = textfield.getAttribute("pattern");
		switch(pattern)
		{
			case 'required': pattern = /\S+/i;break;
			case 'email': pattern = /^\w+([-+.]\w+)*@\w+([-.]\w+)+$/i;break;
			case 'qq':  pattern = /^[1-9][0-9]{4,}$/i;break;
			case 'id': pattern = /^\d{15}(\d{2}[0-9x])?$/i;break;
			case 'ip': pattern = /^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/i;break;
			case 'zip': pattern = /^\d{6}$/i;break;
			case 'mobi': pattern = /^1[3|4|5|7|8][0-9]\d{4,8}$/;break;
			case 'phone': pattern = /^((\d{3,4})|\d{3,4}-)?\d{3,8}(-\d+)*$/i;break;
			case 'url': pattern = /^[a-zA-z]+:\/\/(\w+(-\w+)*)(\.(\w+(-\w+)*))+(\/?\S*)?$/i;break;
			case 'date': pattern = /^(?:(?!0000)[0-9]{4}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-8])|(?:0[13-9]|1[0-2])-(?:29|30)|(?:0[13578]|1[02])-31)|(?:[0-9]{2}(?:0[48]|[2468][048]|[13579][26])|(?:0[48]|[2468][048]|[13579][26])00)-02-29)$/i;break;
			case 'datetime': pattern = /^(?:(?!0000)[0-9]{4}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-8])|(?:0[13-9]|1[0-2])-(?:29|30)|(?:0[13578]|1[02])-31)|(?:[0-9]{2}(?:0[48]|[2468][048]|[13579][26])|(?:0[48]|[2468][048]|[13579][26])00)-02-29) (?:(?:[0-1][0-9])|(?:2[0-3])):(?:[0-5][0-9]):(?:[0-5][0-9])$/i;break;
			case 'int':	pattern = /^\d+$/i;break;
			case 'float': pattern = /^\d+\.?\d*$/i;break;
		}
		var value = this.value;
		var alt = textfield.getAttribute("alt");
		var empty = textfield.getAttribute("empty");

		//输入信息错误
		if((empty==null && value=='') || (value!='' && value.search(pattern) == -1))
		{
			changeInputOutline(textfield.parentNode,false); //改变外框为输入错误时的状态

			tip = getRealNode(textfield.nextSibling);
			msg= getRealNode(textfield.parentNode.nextSibling);

			if(tip && (tip.tagName=='LABEL' || tip.tagName=='SPAN') && !msg && alt!=null){ //存在右边的提示且不存在下方提示信息
				changeTip(tip, false); //改变右边的提示为错误时的状态

				var new_msg=document.createElement("label"); //创建新的下方提示信息
				new_msg.className = "invalid-msg";
				new_msg.innerHTML=alt;
				textfield.parentNode.parentNode.insertBefore(new_msg,null); //插入下方提示信息
			}
			else if(!tip && !msg && alt!=null) //右边提示和下方提示信息均不存在 //
			{
				var new_msg=document.createElement("label");
				var new_tip = document.createElement("label");

				addClass(new_tip,"iconfont");
				addClass(new_tip,"invalid-tip");
				addClass(new_tip,"icon-31guanbi");

				new_msg.className = "invalid-msg";
				new_msg.innerHTML=alt;

				textfield.parentNode.parentNode.insertBefore(new_msg,null);
				textfield.parentNode.insertBefore(new_tip,null);
			}
		}
		else  //输入信息正确
		{
			changeInputOutline(textfield.parentNode,true);


			tip = getRealNode(textfield.nextSibling);
			msg= getRealNode(textfield.parentNode.nextSibling);

			if(msg && (msg.tagName=='LABEL' || msg.tagName=='SPAN')){ //删除下方提示信息
				removeElement(msg);
			}

			if(empty!=null && value==''){
				removeClass(textfield.parentNode, "valid-outline"); //允许为空时，恢复外边框颜色
				if(tip){
					removeElement(tip); //允许为空时，删除右边的提示
				}

			}

			if(tip && (tip.tagName=='LABEL' || tip.tagName=='SPAN')){
				changeTip(tip, true); //右边的提示存在时，改变提示的状态为正确
			}
			else
			{
				var new_tip = document.createElement("label");//创建右边提示
				addClass(new_tip,"iconfont");
				addClass(new_tip,"valid-tip");
				addClass(new_tip,"icon-31xuanze");

				textfield.parentNode.insertBefore(new_tip,null); //插入右边提示
				//tip = new_tip;
			}


			if(this.type == 'password')
			{
				var bind = textfield.getAttribute("bind");
				var bind_flag = true;
				var bind_arr = document.getElementsByName(bind);
				var bind_arr_len = bind_arr.length;
				for(var i=0; i<bind_arr_len; i++)
				{
					if(bind_arr[i].name == bind && bind_arr[i].value != this.value && bind_arr[i].value != '')
					{
						bind_flag = false;
					}
				}
				if(!bind_flag)
				{
					if(!msg){
						var new_msg=document.createElement("LABEL");
						new_msg.className = "invalid-msg";
						msg = new_msg;
					}else{
						msg.className = "invalid-msg";
					}

					changeInputOutline(textfield.parentNode,false);//改变外框状态
					changeTip(getRealNode(textfield.nextSibling),false); //改变右边提示状态

					msg.innerHTML = '两次输入密码不一致';
					textfield.parentNode.parentNode.insertBefore(msg,null);
				}
				else
				{
					for(var i=0; i<bind_arr_len; i++)
					{
						if(bind_arr[i].name == bind && bind_arr[i].value == this.value && bind_arr[i].value != '')
						{
							var p_msg = bind_arr[i].parentNode.nextSibling;
							while(p_msg && p_msg.nodeType==3)p_msg=p_msg.nextSibling;
							if(p_msg && (p_msg.tagName=='LABEL' || p_msg.tagName=='SPAN')){
								removeElement(p_msg);
							}

							changeInputOutline(bind_arr[i].parentNode, true);
							changeTip(getRealNode(bind_arr[i].nextSibling), true);
						}
					}


					changeInputOutline(textfield.parentNode, true);
					changeTip(getRealNode(textfield.nextSibling), true);

				}
			}
		}
	}
	function validateOnSubmit()
	{
		var invalid = false;
		for(var i = 0; i < this.elements.length; i++)
		{
			var e = this.elements[i];

			if((e.type == "text" || e.type == "password" || e.type == "select-one" || e.type == "textarea") && e.getAttribute("pattern") && e.style.display!='none' && e.offsetWidth > 0)
			{
				addEvent(e,'change',validateOnChange);

				if (e.parentNode.className.indexOf(" invalid-outline")!=-1)
				{
					invalid = true;
					if(e.offsetHeight > 0 || e.client > 0)
					{
						e.focus();
					}
					break;
				}
				else
				{
					FireEvent(e,'onchange');
					if (e.parentNode.className.indexOf(" invalid-outline")!=-1)
					{
						invalid = true;
						if(e.offsetHeight > 0 || e.client > 0)
						{
							e.focus();
						}
						break;
					}
				}
			}
		}
		var callback = this.getAttribute('callback');
		var result = true;
		if(callback !=null) {result = eval(callback);}
		result = !(result==undefined?true:result);
		if (invalid || result)
		{
			return false;

		}
	}
})();

