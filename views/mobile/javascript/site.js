//��Ʒ�Ƴ����ﳵ
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

//����ղؼ�
function favorite_add_ajax(goods_id,obj)
{
	$.getJSON(creatUrl("simple/favorite_add"),{"goods_id":goods_id,"random":Math.random()},function(content){
		alert(content.message);
	});
}

//���ﳵչʾ
function showCart()
{
	$.getJSON(creatUrl("simple/showCart"),{sign:Math.random()},function(content)
	{
		$('[name="mycart_count"]').html(content.count);
		/*var cartTemplate = template.render('cartTemplete',{'goodsData':content.data,'goodsCount':content.count,'goodsSum':content.sum});
		$('#div_mycart').html(cartTemplate);
		$('#div_mycart').show();*/
	});
}


//dom����ɹ���ʼ����
jQuery(function()
{
	//���ﳵ��������
	if($('[name="mycart_count"]').length > 0)
	{
		$.getJSON(creatUrl("simple/showCart"),{sign:Math.random()},function(content)
		{
			$('[name="mycart_count"]').html(content.count);
		});

		//���ﳵdiv����ʾ�������л�
		var mycartLateCall = new lateCall(200,function(){showCart();});
		$('[name="mycart"]').hover(
			function(){
				mycartLateCall.start();
			},
			function(){
				mycartLateCall.stop();
				$('#div_mycart').hide('slow');
			}
		);
	}
});

//[ajax]���빺�ﳵ
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

//�б�ҳ���빺�ﳵͳһ�ӿ�
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
				title:'ѡ���Ʒ�����ﳵ',
				okVal:'���빺�ﳵ',
				ok:function(iframeWin, topWin)
				{
					var goodsList = $(iframeWin.document).find('input[name="id[]"]:checked');

					//���ѡ�е���Ʒ
					if(goodsList.length == 0)
					{
						alert('��ѡ��Ҫ���빺�ﳵ����Ʒ');
						return false;
					}
					var temp = $.parseJSON(goodsList.attr('data'));

					//ִ�д���ص�
					joinCart_ajax(temp.product_id,'product');
					return true;
				}
			})
		}
	});
}