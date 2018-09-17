/**
 * @brief 商品类库
 * @param int goods_id 商品ID参数
 * @param int user_id 用户ID参数
 * @param string promo 活动类型参数
 * @param int active_id 活动ID参数
 */
function productClass(goods_id,user_id,promo,active_id)
{
	_self         = this;
	this.goods_id = goods_id; //商品ID
	this.user_id  = user_id;  //用户ID
	this.province_id;         //收货地址省份ID
	this.province_name;       //收货地址省份名字

	this.promo    = promo;    //活动类型
	this.active_id= active_id;//活动ID

	/**
	 * 获取评论数据
	 * @page 分页数
	 */
	this.comment_ajax = function(page)
	{
		if(!page && $.trim($('#commentBox').text()))
		{
			return;
		}

		page = page ? page : 1;
		$.getJSON(creatUrl("site/comment_ajax/page/"+page+"/goods_id/"+this.goods_id),function(json)
		{
			//清空评论数据
			$('#commentBox').empty();

			for(var item in json.data)
			{
				var commentHtml = template.render('commentRowTemplate',json.data[item]);
				$('#commentBox').append(commentHtml);
			}
			$('#commentBox').append(json.pageHtml);
		});
	}

	/**
	 * 获取购买记录数据
	 * @page 分页数
	 */
	this.history_ajax = function(page)
	{
		if(!page && $.trim($('#historyBox').text()))
		{
			return;
		}
		page = page ? page : 1;
		$.getJSON(creatUrl("site/history_ajax/page/"+page+"/goods_id/"+this.goods_id),function(json)
		{
			//清空购买历史记录
			$('#historyBox').empty();
			$('#historyBox').parent().parent().find('.pages_bar').remove();

			for(var item in json.data)
			{
				var historyHtml = template.render('historyRowTemplate',json.data[item]);
				$('#historyBox').append(historyHtml);
			}
			$('#historyBox').parent().after(json.pageHtml);
		});
	}

	/**
	 * 获取购买记录数据
	 * @page 分页数
	 */
	this.refer_ajax = function(page)
	{
		if(!page && $.trim($('#referBox').text()))
		{
			return;
		}
		page = page ? page : 1;
		$.getJSON(creatUrl("site/refer_ajax/page/"+page+"/goods_id/"+this.goods_id),function(json)
		{
			//清空评论数据
			$('#referBox').empty();

			for(var item in json.data)
			{
				var commentHtml = template.render('referRowTemplate',json.data[item]);
				$('#referBox').append(commentHtml);
			}
			$('#referBox').append(json.pageHtml);
		});
	}

	/**
	 * 获取购买记录数据
	 * @page 分页数
	 */
	this.discuss_ajax = function(page)
	{
		if(!page && $.trim($('#discussBox').text()))
		{
			return;
		}
		page = page ? page : 1;
		$.getJSON(creatUrl("site/discuss_ajax/page/"+page+"/goods_id/"+this.goods_id),function(json)
		{
			//清空购买历史记录
			$('#discussBox').empty();
			$('#discussBox').parent().parent().find('.pages_bar').remove();

			for(var item in json.data)
			{
				var historyHtml = template.render('discussRowTemplate',json.data[item]);
				$('#discussBox').append(historyHtml);
			}
			$('#discussBox').parent().after(json.pageHtml);
		});
	}

	/**
	 * @brief 计算各种物流的运费
	 * @param int provinceId 省份ID
	 * @param string provinceName 省份名称
	 */
	this.delivery = function(provinceId,provinceName)
	{
		$('[name="localArea"]').text(provinceName);

		var buyNums   = $('#buyNums').val();
		var productId = $('#product_id').val();
		var goodsId   = _self.goods_id;

		//物流显示模板
		var deliveryTemplate = '<%if(if_delivery == 0){%><%=name%>：<b style="color:#fe6c00">￥<%=price%></b>（<%=description%>）&nbsp;&nbsp;';
			deliveryTemplate+= '<%}else{%>';
			deliveryTemplate+= '<%=name%>：<b style="color:red">该地区无法送达</b>&nbsp;&nbsp;<%}%>';

		//通过省份id查询出配送方式，并且传送总重量计算出运费,然后显示配送方式
		$.getJSON(creatUrl("block/order_delivery"),{'province':provinceId,'goodsId':goodsId,'productId':productId,'num':buyNums,'random':Math.random},function(json)
		{
			//清空配送信息
			$('#deliveInfo').empty();

			for(var item in json)
			{
				var deliveRowHtml = template.compile(deliveryTemplate)(json[item]);
				$('#deliveInfo').append(deliveRowHtml);
			}
		});

		//保存所选择的数据
		_self.province_id   = provinceId;
		_self.province_name = provinceName;
	}

	/**
	 * @brief 根据新浪地区接口获取当前所在地的运费
	 */
	this.initLocalArea = function()
	{
		//根据IP查询所在地
		$.getScript('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=js',function(){
			var ipAddress = remote_ip_info['province'];

			//根据接口返回的数据查找与iWebShop系统匹配的地区
			$.getJSON(creatUrl("block/searchProvince"),{'province':ipAddress,'random':Math.random},function(json)
			{
				if(json.flag == 'success')
				{
					//计算各个配送方式的运费
					_self.delivery(json.area_id,ipAddress);
				}
			});
		});

		//绑定地区选择按钮事件
		$('[name="areaSelectButton"]').bind("click",function(){
			var provinceId   = $(this).attr('value');
			var provinceName = $(this).text();
			_self.delivery(provinceId,provinceName);
		});
	}

	//发表讨论
	this.sendDiscuss = function()
	{
		var userId = _self.user_id;
		if(userId)
		{
			$('#discussTable').show('normal');
			$('#discussContent').focus();
		}
		else
		{
			alert('请登陆后再发表讨论!');
		}
	}

	//发布讨论数据
	this.sendDiscussData = function()
	{
		var content = $('#discussContent').val();
		var captcha = $('[name="captcha"]').val();

		if($.trim(content)=='')
		{
			alert('请输入讨论内容!');
			$('#discussContent').focus();
			return false;
		}
		if($.trim(captcha)=='')
		{
			alert('请输入验证码!');
			$('[name="captcha"]').focus();
			return false;
		}

		$.getJSON(creatUrl("site/discussUpdate"),{'content':content,'captcha':captcha,'random':Math.random,'id':_self.goods_id},function(json)
		{
			if(json.isError == false)
			{
				var discussHtml = template.render('discussRowTemplate',json);
				$('#discussBox').prepend(discussHtml);

				//清除数据
				$('#discussContent').val('');
				$('[name="captcha"]').val('');
				$('#discussTable').hide('normal');
				changeCaptcha();
			}
			else
			{
				alert(json.message);
			}
		});
	}

	//检查选择规格是否完全
	this.checkSpecSelected = function()
	{
		if(_self.specCount === $('[specId].current').length)
		{
			return true;
		}
		return false;
	}

	//初始化规格数据
	this.initSpec = function()
	{
		//根据specId查询规格种类数量
		_self.specCount = 0;
		var tmpSpecId   = "";
		//specid顺序
		var specIds = new Array();

		$('[specId]').each(function()
		{
			if(tmpSpecId != $(this).attr('specId'))
			{
				_self.specCount++;
				tmpSpecId = $(this).attr('specId');
				specIds.push(tmpSpecId);
			}
		});

		//绑定商品规格函数
		//$('[specId][specData]').bind('click',function()
		$('[specId]').bind('click',function()
		{
			if($(this).attr('disabled'))
			{
				return false;
			}

			//设置选中状态
			$("[specId='"+$(this).attr('specId')+"']").removeClass('current');
			$(this).addClass('current');

			//拼接选中的规格数据
			var currentSpecArray = [];
			$('[specId].current').each(function()
			{
				var specData = $(this).data('specData');
				if(!specData)
				{
					alert("规格数据没有绑定");
					return;
				}

				/*currentSpecArray[$(this).attr('specId')] = {
														"id":$(this).attr('specId'),
														"type":$(this).attr('specData').indexOf("/") === -1 ? 1 : 2,
														"value":$(this).attr('specData'),
														"name":$(this).attr('specName')
													};*/
				currentSpecArray[$(this).attr('specId')] = {
														"id":specData.id,
														"type":specData.type,
														"value":specData.value,
														"name":specData.name,
														"tip":specData.tip,
														"index": 0
													};
				if ((specData.tip + "").length > 0)
				{
					currentSpecArray[$(this).attr('specId')].index = goods_spec_val_array[specData.id + "@" + specData.tip];
				}
				else
				{
					currentSpecArray[$(this).attr('specId')].index = goods_spec_val_array[specData.id + "@" + specData.value];
				}
			});

			/*
			 * dyg_jzw 20150803 选择某个规格后, 根据库存进行隐藏其他规格
			 */

			//与其他规格组合sku, 是否无库存
			for(var _spec_index in goods_spec_array)
			{
				if (parseInt(_spec_index) > 0)
				{
					//非当前点击所在行的规格
					if (parseInt(_spec_index) != parseInt($(this).attr('specId')))				
					{
						//规格值列表
						var _spec_val_arr = goods_spec_array[_spec_index].value;
						
						var _tmp_spec_arr = new Array();

						for (var i = 0; i < _spec_val_arr.length; i++) 
						{
							//当前已选择的规格
							_tmp_spec_arr = currentSpecArray.concat();

							//替换当前行的规格为本行其他规格
							$.each(_spec_val_arr[i], function(tip, spec_item){
								_tmp_spec_arr[_spec_index] = { 
															"id": goods_spec_array[_spec_index].id,
										 					"type": goods_spec_array[_spec_index].type,
										 					"value": spec_item,
										 					"tip": tip,
										 					"name": goods_spec_array[_spec_index].name,
										 					"index": 0 
										 				};
								if (tip.length > 0)
								{
									_tmp_spec_arr[_spec_index].index = goods_spec_val_array[goods_spec_array[_spec_index].id + "@" + tip];
								}
								else
								{
									_tmp_spec_arr[_spec_index].index = goods_spec_val_array[goods_spec_array[_spec_index].id + "@" + spec_item];
								}
							});
							
							//组合成key
							var _map_key = '';
							for (var _i = 0; _i < specIds.length; _i++)
							{
								if (typeof(_tmp_spec_arr[specIds[_i]]) != 'undefined' && _tmp_spec_arr[specIds[_i]] != null)
								{
									//获取规格值/规格提示对应的规格index序号
									_map_key += ',' + _tmp_spec_arr[specIds[_i]].id + '_' + _tmp_spec_arr[specIds[_i]].index;
								}
							}

							//判断是否无库存
							//隐藏此按钮
							var button_id = goods_spec_array[_spec_index].id + "_" + _tmp_spec_arr[_spec_index].index;

							if(typeof(spec_store_map[_map_key.substr(1)]) == 'undefined')
							{
								$("#" + button_id).removeClass('current').addClass('disabled_spec').attr('disabled', true);
							}
							else
							{
								$("#" + button_id).removeClass('disabled_spec').removeAttr('disabled');
							}
						}
						
					}
				}
			}

			//检查是否选择完成
			if(_self.checkSpecSelected() == true)
			{
				var _tmp_key = '';
				for (var _spec_index2 in currentSpecArray) 
				{
					if (parseInt(_spec_index2)> 0)
					{
						_tmp_key += ',' + currentSpecArray[_spec_index2].id + '_' + (currentSpecArray[_spec_index2].index);
					}
				}

				var spec_obj = spec_store_map[_tmp_key.substr(1)];

				//货品数据渲染
				$('#data_goodsNo').text(spec_obj.products_no);
				$('#data_storeNums').text(spec_obj.store_nums);
				$('#data_storeNums').trigger('change');
				if (typeof(spec_obj.group_price) == 'undefined') {
					$('#data_groupPrice').text(spec_obj.sell_price);
				} else {
					$('#data_groupPrice').text(spec_obj.group_price);
				}
				if (typeof(spec_obj.group_real_price) == 'undefined') {
					$('#data_groupRealPrice').hide();
				} else {
					$('#data_groupRealPrice').show();
					$('#data_groupRealPrice i').text(spec_obj.group_real_price);
				}
				$('#data_sellPrice').text(spec_obj.sell_price);
				$('#data_marketPrice').text(spec_obj.market_price);
				$('#data_weight').text(spec_obj.weight);
				$('#product_id').val(spec_obj.product_id);
			}
		});


		//dyg_jzw 20170113默认点击每个规格类型的第一个可见值
		var _tmp_specid = 0;
		$('.chosenBox-list li:not(:hidden) [specId]:not(:disabled)').each(function() {
			if($(this).attr('specId') != _tmp_specid) {
				_tmp_specid = $(this).attr('specId');

				if ($(this).attr("disabled") == "disabled"){
					_tmp_specid = 0;
				} else {
					$(this).trigger("click");
				}
			}
		});
	}


	//检查购买数量是否合法
	this.checkBuyNums = function()
	{
		var minNums   = parseInt($('#buyNums').attr('minNums'));
		    minNums   = minNums ? minNums : 1;
		var maxNums   = parseInt($('#buyNums').attr('maxNums'));
			maxNums   = maxNums ? maxNums : parseInt($.trim($('#data_storeNums').text()));

		var buyNums   = parseInt($.trim($('#buyNums').val()));

		//购买数量小于0
		if(isNaN(buyNums) || buyNums < minNums)
		{
			$('#buyNums').val(minNums);
			alert("此商品购买数量不得少于"+minNums);
		}

		//购买数量大于库存
		if(buyNums > maxNums)
		{
			$('#buyNums').val(maxNums);
			alert("此商品购买数量不得超过"+maxNums);
		}
	}

	/**
	 * 购物车数量的加减
	 * @param code 增加或者减少购买的商品数量
	 */
	this.modified = function(code)
	{
		var buyNums = parseInt($.trim($('#buyNums').val()));
		switch(code)
		{
			case 1:
			{
				buyNums++;
			}
			break;

			case -1:
			{
				buyNums--;
			}
			break;
		}
		$('#buyNums').val(buyNums);
		$('#buyNums').trigger('change');
	}

	//商品加入购物车
	this.joinCart = function()
	{
		if(_self.checkSpecSelected() == false)
		{
			tips('请先选择商品的规格');
			return;
		}

		//dyg_jzw 20160727 mobile专用购物车选择
		//type: 1立即购买 2加入购物车
		var buy_type = parseInt($('#joinCarButton').attr('data-type'));

		if (buy_type == 1)  //立即购买
		{
			this.buyNow();
		}
		else //加入购物车
		{
			var buyNums   = parseInt($.trim($('#buyNums').val()));
			var price     = parseFloat($.trim($('#real_price').text()));
			var productId = $('#product_id').val();
			var type      = productId ? 'product' : 'goods';
			var goods_id  = (type == 'product') ? productId : _self.goods_id;

			$.getJSON(creatUrl("simple/joinCart"),{"goods_id":goods_id,"type":type,"goods_num":buyNums,"random":Math.random},function(content)
			{
				if(content.isError == false)
				{
					//获取购物车信息
					$.getJSON(creatUrl("simple/showCart"),{"random":Math.random},function(json)
					{
						$('[name="mycart_count"]').text(json.count);
						$('[name="mycart_sum"]').text(json.sum);

						//直接进入购物车页面
						window.location.href = creatUrl('simple/cart');
						//tips("目前选购商品共"+json.count+"件，合计：￥"+json.sum);
					});
				}
				else
				{
					alert(content.message);
				}
			});
		}
	}

	//立即购买按钮
	this.buyNow = function()
	{
		//对规格的检查
		if(_self.checkSpecSelected() == false)
		{
			tips('请选择商品的规格');
			return;
		}

		//设置必要参数
		var buyNums = parseInt($.trim($('#buyNums').val()));
		var id      = _self.goods_id;
		var type    = 'goods';

		if($('#product_id').val())
		{
			id   = $('#product_id').val();
			type = 'product';
		}

		//普通购买
		var url = "/simple/cart2/id/"+id+"/num/"+buyNums+"/type/"+type;

		//有促销活动（团购，抢购）
		if(_self.promo && _self.active_id)
		{
			url += "/promo/"+_self.promo+"/active_id/"+_self.active_id;
		}

		//页面跳转
		window.location.href = creatUrl(url);
	}

	//构造函数
	!(function()
	{
		//根据IP地址获取所在地区的物流运费
		//_self.initLocalArea();

		//商品规格数据初始化
		_self.initSpec();

		//插入货品ID隐藏域
		$("<input type='hidden' id='product_id' alt='货品ID' value='' />").appendTo("body");

		//绑定商品图片
		$('[thumbimg]').bind('click',function()
		{
			$('#picShow').prop('src',$(this).attr('thumbimg'));
			$('#picShow').attr('rel',$(this).attr('sourceimg'));
			$(this).addClass('current');
		});

		//绑定讨论圈按钮
		$('[name="discussButton"]').bind("click",function(){_self.sendDiscuss();});
		$('[name="sendDiscussButton"]').bind("click",function(){_self.sendDiscussData();});

		//绑定商品数量调控按钮
		$('#buyAddButton').bind("click",function(){_self.modified(1);});
		$('#buyReduceButton').bind("click",function(){_self.modified(-1);});
		$('#buyNums').bind("change",function()
		{
			//检查购买数量是否合法
			_self.checkBuyNums();

			//运费查询 dyg_jzw20160727 屏蔽运费查询
			//_self.delivery(_self.province_id,_self.province_name);
		});

		//立即购买按钮
		$('#buyNowButton').bind('click',function(){_self.buyNow();});

		//加入购物车按钮
		$('#joinCarButton').bind('click',function(){_self.joinCart();});

		//库存域绑定事件,如果库存不足无法购买和加入购物车
		$('#data_storeNums').bind('change',function()
		{
			var storeNum = parseInt($(this).text());
			if(storeNum <= 0)
			{
				alert("当前货品库存不足无法购买");

				//按钮锁定
				$('#buyNowButton,#joinCarButton').prop('disabled',true);
				$('#buyNowButton,#joinCarButton').addClass('disabled');
			}
			else
			{
				//按钮解锁
				$('#buyNowButton,#joinCarButton').prop('disabled',false);
				$('#buyNowButton,#joinCarButton').removeClass('disabled');
			}
		});

		//促销活动隐藏购物车按钮
		//dyg_jzw 20161215手机版购物逻辑有更改,不需隐藏此按钮
		// if(_self.promo && _self.active_id)
		// {
		// 	$('#joinCarButton').hide();
		// }
	}())
}