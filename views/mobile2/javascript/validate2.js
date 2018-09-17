var autoValidate =
{
	//icon提示class
	correct_icon_classname : 'iconfont valid-tip icon-31xuanze',
	error_icon_classname : 'iconfont invalid-tip icon-31guanbi',

	//文字提示class
	correct_text_classname : '',
	error_text_classname : 'invalid-msg',

	//输入框外框class
	correct_input_classname : 'valid-outline',
	error_input_classname : 'invalid-outline',

	addEvent : function(obj, type, fn)
	{
		if (obj.attachEvent)
		{
			obj['e'+type+fn] = fn;
			obj[type+fn] = function(){obj['e'+type+fn]( window.event );}
			obj.attachEvent('on'+type, obj[type+fn]);
		}
		else
			obj.addEventListener(type, fn, false);
	},

	FireEvent : function(elem, eventName)
	{
		if (document.all)
		{
			elem.fireEvent(eventName);
		}
		else
		{
			 var evt = document.createEvent('HTMLEvents');
			 evt.initEvent('blur',true,true);
			 elem.dispatchEvent(evt);
		}
	},

	removeEvent : function(obj, type, fn)
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
	},

	addClass : function(node, str) 
	{
		if(!new RegExp('(^|\\s+)'+str).test(node.className)) 
		{
			node.className = node.className + ' ' + str;
		}
	},

	removeClass : function(node, str) 
	{
		node.className = node.className.replace(new RegExp('(^|\\s+)'+str), '');
	},

	removeElement : function(_element)
	{
		var _parentElement = _element.parentNode;
		if(_parentElement){
			_parentElement.removeChild(_element);
		}
	},

	reset : function(obj)
	{
		//删除外框状态
		autoValidate.removeClass(obj.parentNode, autoValidate.error_input_classname);
		autoValidate.removeClass(obj.parentNode, autoValidate.correct_input_classname);

		//删除icon提示
		var icon_tips = obj.nextSibling;
		while(icon_tips && icon_tips.nodeType==3)
		{
			icon_tips= icon_tips.nextSibling;
		}

		if (icon_tips && icon_tips.tagName == 'LABEL')
		{
			autoValidate.removeElement(icon_tips);
		}

		//删除文字提示
		var text_tips = obj.parentNode.nextSibling; //是否存在文字提示信息
		while(text_tips && text_tips.nodeType==3)
		{
			text_tips= text_tips.nextSibling;
		}

		if (text_tips && text_tips.tagName == 'LABEL')
		{
			autoValidate.removeElement(text_tips);
		}

	},

	tips : function(is_correct, tips_str, obj)
	{
		//改变外框状态
		if (is_correct)
		{
			autoValidate.removeClass(obj.parentNode , autoValidate.error_input_classname);
			autoValidate.addClass(obj.parentNode , autoValidate.correct_input_classname);
			
		}
		else
		{
			autoValidate.removeClass(obj.parentNode , autoValidate.correct_input_classname);
			autoValidate.addClass(obj.parentNode , autoValidate.error_input_classname);
		}

		/*
		 * 提示打勾或交叉
		 */
		var icon_tips = obj.nextSibling;
		while(icon_tips && icon_tips.nodeType==3)
		{
			icon_tips= icon_tips.nextSibling;
		}

		var is_icon_tips = false; //obj下一元素是否存在

		if (icon_tips && icon_tips.tagName=='LABEL') //已存在icon提示
		{
			is_icon_tips = true;
		}
		else //不存在icon提示，创建tag
		{
			icon_tips = document.createElement("LABEL");
		}

		//icon tips 根据正确与否设置class
		icon_tips.className = is_correct ? autoValidate.correct_icon_classname : autoValidate.error_icon_classname;
		if (!is_icon_tips) //icon tips 不存在, 创建
		{
			obj.parentNode.appendChild(icon_tips);
		}


		/*
		 * 提示文字信息
		 */
		var text_tips = obj.parentNode.nextSibling; //是否存在文字提示信息
		while(text_tips && text_tips.nodeType==3)
		{
			text_tips= text_tips.nextSibling;
		}

		var is_text_tips = false;
		if (text_tips && text_tips.tagName == 'LABEL') //已存在文字提示
		{
			is_text_tips = true;
		}
		else //不存在文字提示，创建tag
		{
			text_tips = document.createElement("LABEL");
		}

		text_tips.className = is_correct ? autoValidate.correct_text_classname : autoValidate.error_text_classname;
		
		text_tips.innerHTML = is_correct ? '' : '<i class="iconfont icon-infofill"></i> ';

		if (text_tips.className != '')
		{
			text_tips.innerHTML += tips_str;
		}
		else if(is_text_tips) //空classname的话 删除已创建的节点
		{
			autoValidate.removeElement(text_tips);
		}

		if (!is_text_tips && text_tips.className != '') //文字提示 不存在, 创建
		{
			obj.parentNode.parentNode.appendChild(text_tips);
		}

	},

	init : function()
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
					autoValidate.addEvent(e,'blur', autoValidate.validateOnChange);
                    needsValidation = true;
                }
            }
            if(needsValidation)
            {
            	f.onsubmit = autoValidate.validateOnSubmit;f.setAttribute('novalidate','true');
            }
        }
    },

    validateOnChange : function()
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
			case 'mobi': pattern = /^1[3|4|5|7|8]\d{9}$/;break;
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

        if((empty==null && value=='') || (value!='' && value.search(pattern) == -1))
        {
        	//校验不符
        	autoValidate.tips(false, alt, textfield);
        }
        else
        {
        	//校验正确 
        	autoValidate.tips(true, alt, textfield);

			if(this.type == 'password') //密码校验是否输入相同
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
			    	//密码不一致
			    	autoValidate.tips(false, '两次输入密码不一致', textfield);
			    }
			    else
			    {
			    	for(var i=0; i<bind_arr_len; i++)
				    {
			    		if(bind_arr[i].name == bind && bind_arr[i].value == this.value && bind_arr[i].value != '')
			    		{
			    			//密码一致
			    			autoValidate.tips(true, '', bind_arr[i]);
			    		}
				    }
				    //密码一致
				    autoValidate.tips(true, '', textfield);
			    }
			}
        }
    },

    validateOnSubmit : function()
    {
        var invalid = false;
        for(var i = 0; i < this.elements.length; i++)
        {
            var e = this.elements[i];
            var outline = e.parentNode;

            if((e.type == "text" || e.type == "password" || e.type == "select-one" || e.type == "textarea") && e.getAttribute("pattern") && e.style.display!='none' && e.offsetWidth > 0)
            {
				autoValidate.addEvent(e,'blue', autoValidate.validateOnChange);

				if (outline.className.indexOf(" " + autoValidate.error_input_classname)!=-1)
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
					autoValidate.FireEvent(e,'onblue');
					if (outline.className.indexOf(" " + autoValidate.error_input_classname)!=-1)
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

};

autoValidate.addEvent(window,'load',autoValidate.init);