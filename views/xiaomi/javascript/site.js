//商品移除购物车
function removeCart(goods_id,type)
{
	var goods_id = parseInt(goods_id);
	$.getJSON(creatUrl("simple/removeCart"),{goods_id:goods_id,type:type},function(content){
		if(content.isError == false)
		{
			$('[name="mycart_count"]').html(content.data['count']);
			$('[name="mycart_sum"]').html(content.data['sum']);
		}
		else
		{
			alert(content.message);
		}
	});
}

//添加收藏夹
function favorite_add_ajax(goods_id,obj)
{
	$.getJSON(creatUrl("simple/favorite_add"),{"goods_id":goods_id,"random":Math.random()},function(content){
		tips(content.message);
	});
}

//购物车展示
function showCart()
{
	if($('#div_mycart').is(':hidden'))
	{
		$('#div_mycart').slideDown('fast');
		//loading
		$('#div_mycart').html('<div class="goods-list text-center"><img src="http://7xkljz.com1.z0.glb.clouddn.com/loading.gif"></div>');
	
		$.getJSON(creatUrl("simple/showCart"),{sign:Math.random()},function(content)
		{
			var cartTemplate = template.render('cartTemplete',{'goodsData':content.data,'goodsCount':content.count,'goodsSum':content.sum});
			$('#div_mycart').html(cartTemplate);
		});
	}
}

//[ajax]加入购物车
function joinCart_ajax(id,type)
{
	$.getJSON(creatUrl("simple/joinCart"),{"goods_id":id,"type":type,"random":Math.random()},function(content){
		if(content.isError == false)
		{
			var count = parseInt($('[name="mycart_count"]').html()) + 1;
			$('[name="mycart_count"]').html(count);
			alert(content.message);
		}
		else
		{
			alert(content.message);
		}
	});
}

//列表页加入购物车统一接口
function joinCart_list(id)
{
	$.getJSON(creatUrl("/simple/getProducts"),{"id":id},function(content)
	{
		if(!content || content.length == 0)
		{
			joinCart_ajax(id,'goods');
		}
		else
		{
			artDialog.open(creatUrl("/block/goods_list/goods_id/"+id+"/type/radio/is_products/1"),{
				id:'selectProduct',
				title:'选择货品到购物车',
				okVal:'加入购物车',
				ok:function()
				{
					var iframeWin = document.getElementById("iframeWin").contentDocument||document.frames["iframeWin"].document;
					var goodsList = $(iframeWin).find('input[name="id[]"]:checked');
					//添加选中的商品
					if(goodsList.length == 0)
					{
						alert('请选择要加入购物车的商品');
						return false;
					}
					var temp = $.parseJSON(goodsList.attr('data'));
					//执行处理回调
					joinCart_ajax(temp.product_id,'product');
					return true;
				}
			})
		}
	});
}

//重置自定义单选选择的点击事件
function init_checkbox_radio()
{
	$('.checkbox-item-radio').next('label').click(function(e){
		if($(this).parent().hasClass('disabled')) {
			return;
		}
		var radio_name = $(this).prev('.checkbox-item-radio').attr('name');
		$(".checkbox-item-radio[name='"+radio_name+"']").next('label').removeClass('active');
		$(this).addClass('active');
		
		//兼容ie的点击问题
		e.preventDefault(); 
		$("#"+$(this).attr("for")).click().change(); 
	});

	//默认已选择
	$('.checkbox-item-radio:checked').next('label').trigger('click');
}

//在线客服加载
function open_new_window(url)
{
	window.open(url, 'newwindow', 'height=550, width=450, top=100, left=200, toolbar=no, menubar=no, scrollbars=no, resizable=no,location=no, status=no') 
}


// 返回顶部
function back2top()
{
	$('body').animate({ scrollTop: 0 }, 300);
}

function createQrcodeUrl(url)
{
	return "<img src='http://s.jiathis.com/qrcode.php?url="+encodeURIComponent(url)+"' style='height:100px;width:100px;'>";
}

//dom载入成功后开始操作
$(document).ready(function(){
	//购物车数量加载
	if($('[name=mycart_count]').length > 0)
	{
		$.getJSON(creatUrl("simple/showCart"),{sign:Math.random()},function(content)
		{
			$('[name=mycart_count]').text(content.count);
		});

		//购物车div层显示和隐藏切换
		$('[name="mycart"]').hover(
			function(){
				$(this).addClass("active");
				showCart();
			},
			function(){
				$(this).removeClass("active");
				$('#div_mycart').slideUp('fast');
			}
		);
	}

	//搜索框选中后颜色
	$('[name="word"]').focus(function(){
		$('.hot-keyword').hide();
		$('.search-div').addClass('active');
	});
	$('[name="word"]').blur(function(){
		$('.hot-keyword').show();
		$('.search-div').removeClass('active');
	});

	//自定义单选选择
	if($('.checkbox-item-radio').length > 0)
	{
		init_checkbox_radio();
	}

});