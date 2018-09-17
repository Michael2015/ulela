/**
 * 订单对象
 * address:收货地址; delivery:配送方式; payment:支付方式;
 */
function orderFormClass()
{
    _self = this;

    //商家信息
    this.seller = null;

    //默认数据
    this.deliveryId   = 0;
    //免运费的商家ID
    this.freeFreight  = [];

    //订单各项数据
    this.orderAmount  = 0;//订单金额
    this.goodsSum     = 0;//商品金额
    this.deliveryPrice= 0;//运费金额
    this.paymentPrice = 0;//支付金额
    this.taxPrice     = 0;//税金
    this.protectPrice = 0;//保价
    this.ticketPrice  = 0;//代金券

    /**
     * 算账
     */
    this.doAccount = function()
    {
        //税金
        this.taxPrice = $('input:checkbox[name="taxes"]:checked').length > 0 ? $('input:checkbox[name="taxes"]:checked').val() : 0;
        //最终金额
        this.orderAmount = parseFloat(this.goodsSum) - parseFloat(this.ticketPrice) + parseFloat(this.deliveryPrice) + parseFloat(this.paymentPrice) + parseFloat(this.taxPrice) + parseFloat(this.protectPrice);

        this.orderAmount = this.orderAmount <=0 ? 0 : this.orderAmount.toFixed(2);

        //刷新DOM数据
        $('#final_sum').html(this.orderAmount);
        $('[name="ticket_value"]').html(this.ticketPrice);
        $('#delivery_fee_show').html(this.deliveryPrice);
        $('#protect_price_value').html(this.protectPrice);
        $('#payment_value').html(this.paymentPrice);
        $('#tax_fee').html(this.taxPrice);
    }

    //地址修改
    //dyg_lzq 20161024
    //dyg_jzw 20161203 修改为layer组件
    this.addressEdit = function(addressId, is_cbe) //dyg_jzw 20160613 添加跨境电商地址
    {
        layer.open({
            type: 2, //iframe层
            content: creatUrl("block/address/id/"+addressId + "?is_cbe=" + is_cbe), //dyg_jzw 20160613 添加跨境电商地址
            id: "addressWindow",
            title: "修改收货地址",
            area: ['auto', '460px'],
            btn: ['修改', '取消'],
            yes: function(index, layero){
                var formObject = layer.getChildFrame('form', index)[0];
                if(formObject.onsubmit() === false)
                {
                    layer.msg("请正确填写各项信息");
                    return false;
                }
                $.getJSON(formObject.action,$(formObject).serialize(),function(content){
                    if(content.result == false)
                    {
                        layer.msg(content.msg);
                        return;
                    }
                    addressId ? $('#addressItem'+addressId).remove() : $('#addressItem:first').remove();

                    //修改后的节点增加
                    var addressLiHtml = template.render('addressLiTemplate',{"item":content.data});
                    $('.addr-list').prepend(addressLiHtml);
                    init_checkbox_radio(); //重置可点击
                    $('input:radio[name="radio_address"]:first').next('label').trigger('click');

                    layer.msg("修改成功");
                    layer.close(index);
                });

                return true;
            }

        });
    };
    //地址删除
    this.addressDel  = function(addressId)
    {
        $('#addressItem'+addressId).remove();
        $.get(creatUrl("ucenter/address_del"),{"id":addressId});
        layer.msg("删除成功");
    }

    //地址增加
    //dyg_lzq 20161021
    this.addressAdd  = function(is_cbe) //dyg_jzw 20160613 添加跨境电商地址
    {
        layer.open({
            type: 2, //iframe层
            content: creatUrl("block/address" + "?is_cbe=" + is_cbe), //dyg_jzw 20160613 添加跨境电商地址
            id: "addressWindow",
            title: "添加收货地址",
            area: ['auto', '460px'],
            btn: ['添加', '取消'],
            yes: function(index, layero){
                var formObject = layer.getChildFrame('form', index)[0];

                if(formObject.onsubmit() === false)
                {
                    layer.msg("请正确填写各项信息");
                    return false;
                }
                $.getJSON(formObject.action,$(formObject).serialize(),function(content){
                    if(content.result == false)
                    {
                        alert(content.msg);
                        return;
                    }
                    var addressLiHtml = template.render('addressLiTemplate',{"item":content.data});
                    $('.addr-list').prepend(addressLiHtml);
                    init_checkbox_radio(); //重置可点击
                    $('input:radio[name="radio_address"]:first').next('label').trigger('click');

                    layer.msg("添加成功");
                    layer.close(index);
                });

                return true;
            }

        });
    }

    //根据省份地区ajax获取配送方式和运费
    this.getDelivery = function(province)
    {
        //整合当前的商品信息
        var goodsId   = [];
        var productId = [];
        var num       = [];
        $('[id^="deliveryFeeBox_"]').each(function(i)
        {
            var idValue = $(this).attr('id');
            var dataArray = idValue.split("_");

            goodsId.push(dataArray[1]);
            productId.push(dataArray[2]);
            num.push(dataArray[3]);
        });

        //获取配送信息和运费
        $.getJSON(creatUrl("block/order_delivery"),{"province":province,"goodsId":goodsId,"productId":productId,"num":num,"random":Math.random()},function(json){
            for(indexVal in json)
            {
                var content = json[indexVal];
                //正常可以送达
                if(content.if_delivery == 0)
                {
                    for(var tIndex in _self.freeFreight)
                    {
                        var sellerId  = _self.freeFreight[tIndex];
                        content.price = parseFloat(content.price) - parseFloat(content.seller_price[sellerId]);
                    }
                    $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').data("protectPrice",parseFloat(content.protect_price));
                    $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').data("deliveryPrice",parseFloat(content.price));
                    var deliveryHtml = template.render("deliveryTemplate",{"item":content});
                    $("#deliveryShow"+content.id).html(deliveryHtml);
                    $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("disabled",false);
                    $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').parent().removeClass('disabled');
                }
                //配送方式不能配送
                else
                {
                    $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("disabled",true);
                    $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("checked",false);
                    $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').parent().addClass('disabled');
                    $('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').next().removeClass('active');
                    $("#deliveryShow"+content.id).html("<span class='text-danger'>您选择地区部分商品无法送达</span>");
                }
            }
            var checkVal = $('input[type="radio"][name="delivery_id"]:checked');
            if(checkVal.length > 0)
            {
                _self.deliverySelected(checkVal.val());
            }
            else if(_self.deliveryId > 0 && $('input[type="radio"][name="delivery_id"][value="'+_self.deliveryId+'"]').prop('disabled') != "disabled")
            {
                $('input[type="radio"][name="delivery_id"][value="'+_self.deliveryId+'"]').trigger('click');
            }
        });
    }

    /**
     * address初始化
     */
    this.addressInit = function()
    {
        var addressList = $('input:radio[name="radio_address"]');
        if(addressList.length > 0)
        {
            addressList.first().next('label').trigger('click');
        }
    }

    /**
     * delivery初始化
     */
    this.deliveryInit = function(defaultDeliveryId)
    {
        this.deliveryId = defaultDeliveryId;

        if(defaultDeliveryId > 0)
        {
            // 点击label行
            $('input:radio[name="delivery_id"][value="'+defaultDeliveryId+'"]').next('label').trigger('click');
        }
    }

    /**
     * delivery选中
     * @param int deliveryId 配送方式ID
     */
    this.deliverySelected = function(deliveryId)
    {
        var deliveryObj = $('input[type="radio"][name="delivery_id"][value="'+deliveryId+'"]');
        this.protectPrice  = deliveryObj.data("protectPrice") > 0 ? deliveryObj.data("protectPrice") : 0;
        this.deliveryPrice = deliveryObj.data("deliveryPrice")> 0 ? deliveryObj.data("deliveryPrice"): 0;

        //先发货后付款
        if(deliveryObj.attr('paytype') == '1')
        {
            $('input[type="radio"][name="payment"]').prop('checked',false);
            $('input[type="radio"][name="payment"]').prop('disabled',true);
            $('#paymentBox').hide("slow");

            //支付手续费清空
            this.paymentPrice = 0;
        }
        else
        {
            $('input[type="radio"][name="payment"]').prop('disabled',false);
            $('#paymentBox').show("slow");
        }
        _self.doAccount();
    }

    /**
     * payment初始化
     */
    this.paymentInit = function(defaultPaymentId)
    {
        if(defaultPaymentId > 0)
        {
            // $('input:radio[name="payment"][value="'+defaultPaymentId+'"]').trigger('click');
            // 点击label行
            $('input:radio[name="payment"][value="'+defaultPaymentId+'"]').next('label').trigger('click');
        }
    }

    /**
     * payment选择
     */
    this.paymentSelected = function(paymentId)
    {
        var paymentObj = $('input[type="radio"][name="payment"][value="'+paymentId+'"]');
        this.paymentPrice = paymentObj.attr("alt");
        this.doAccount();
    }

    /**
     * 检查表单是否可以提交
     */
    this.isSubmit = function()
    {
        var addressObj  = $('input[type="radio"][name="radio_address"]:checked');
        var deliveryObj = $('input[type="radio"][name="delivery_id"]:checked');
        var paymentObj  = $('input[type="radio"][name="payment"]:checked');

        if(addressObj.length == 0)
        {
            alert("请选择收件人地址");
            return false;
        }

        if(deliveryObj.length == 0)
        {
            alert("请选择配送方式");
            return false;
        }

        if(deliveryObj.attr('paytype') == 2 && $('input[name="takeself"]').length == 0)
        {
            alert("请选择配送方式中的自提点");
            return false;
        }

        //dyg_jzw 20170119 不校验支付方式
		/*if(paymentObj.length == 0 && deliveryObj.attr('paytype') != "1")
		 {
		 alert("请选择支付方式");
		 return false;
		 }*/
        window.loadding();
        return true;
    }

    /**
     * 点击选择自提点
     */
    this.selectTakeself = function(deliveryId)
    {
        layer.open({
            type: 2, //iframe层
            content: creatUrl("block/takeself"),
            id: "selectTakeself",
            title: "选择自提点",
            area: ['350px', '440px'],
            btn: ['选择', '取消'],
            yes: function(index, layero){
                var takeselfJson = layer.getChildFrame('[name="takeselfItem"]:checked', index).val();

                if(!takeselfJson)
                {
                    layer.msg('请选择自提点');
                    return false;
                }
                var json = $.parseJSON(takeselfJson);
                $('#takeself'+deliveryId).empty();
                $('#takeself'+deliveryId).html(template.render('takeselfTemplate',{"item":json}));

                //动态生成节点
                _self.getForm().find("input[name='takeself']").remove();

                //动态插入节点
                _self.getForm().prepend("<input type='hidden' name='takeself' value='"+json.id+"' />");

                layer.close(index);
                return true;
            }

        });
    }

    /**
     * 代金券显示
     */
    this.ticketShow = function()
    {
        var sellerArray = [];
        for(var seller_id in this.seller)
        {
            sellerArray.push(seller_id);
        }

        //dyg_jzw 20170105查询代金券是否可用
        var direct_gid = $("input[name='direct_gid']").val();
        direct_gid = direct_gid ? direct_gid : 0;
        var direct_type = $("input[name='direct_type']").val();
        direct_type = direct_type ? direct_type : 0;
        var direct_num = $("input[name='direct_num']").val();
        var direct_promo = $("input[name='direct_promo']").val();
        direct_promo = direct_promo ? direct_promo : 0;
        var direct_active_id = $("input[name='direct_active_id']").val();
        direct_active_id = direct_active_id ? direct_active_id : 0;

        layer.open({
            type: 2, //iframe层
            content: creatUrl("block/ticket/sellerString/"+sellerArray.join("_") + "/direct_gid/"+direct_gid+"/direct_type/"+direct_type+"/direct_num/"+direct_num+"/direct_active_id/"+direct_active_id+"/direct_promo/"+direct_promo),  //dyg_jzw 20170105 代金券是否可用判断
            id: "ticketShow",
            title: "选择代金券",
            area: ['350px', '450px'],
            btn: ['使用', '取消'],
            yes: function(index, layero){

                //动态创建代金券节点
                _self.getForm().find("input[name='ticket_id[]']").remove();

                var formObject   =  layer.getChildFrame('form[name="ticketForm"]', index)[0];
                var resultTicket = 0;
                $(formObject).find("[name='ticket_id']:checked").each(function()
                {
                    var sid    = $(this).attr('seller');
                    var tprice = parseFloat($(this).attr('price'));

                    //专用代金券
                    if(_self.seller[sid] > 0)
                    {
                        resultTicket += (tprice >= _self.seller[sid]) ? _self.seller[sid] : tprice;
                    }
                    //通用代金券
                    else if(sid == '0')
                    {
                        var maxPrice = 0;
                        for(var sellerId in _self.seller)
                        {
                            if(_self.seller[sellerId] > maxPrice)
                            {
                                maxPrice = _self.seller[sellerId];
                            }
                        }
                        resultTicket += (tprice >= maxPrice) ? maxPrice : tprice;
                    }
                    //动态插入节点
                    _self.getForm().prepend("<input type='hidden' name='ticket_id[]' value='"+$(this).val()+"' />");

                    layer.msg("已成功选择使用代金券");
                });
                _self.ticketPrice = resultTicket;
                _self.doAccount();

                layer.close(index);
                return true;
            }

        });
    }

    /**
     * dyg_jzw 代金券显示 步骤1
     */
    this.ticketShow1 = function(ticket_code)
    {
        var sellerArray = [];
        for(var seller_id in this.seller)
        {
            sellerArray.push(seller_id);
        }

        //dyg_jzw 20170105查询代金券是否可用
        var direct_gid = $("input[name='direct_gid']").val();
        direct_gid = direct_gid ? direct_gid : 0;
        var direct_type = $("input[name='direct_type']").val();
        direct_type = direct_type ? direct_type : 0;
        var direct_num = $("input[name='direct_num']").val();
        var direct_promo = $("input[name='direct_promo']").val();
        direct_promo = direct_promo ? direct_promo : 0;
        var direct_active_id = $("input[name='direct_active_id']").val();
        direct_active_id = direct_active_id ? direct_active_id : 0;
        var selectItem = null;
        //判断是否通过输入劵码兑劵
        //获取代金券信息
        $.getJSON(creatUrl("block/ticket_json/"),{"sellerString":sellerArray.join("_"),"ticket_code":ticket_code,"direct_gid":direct_gid, "direct_type":direct_type, "direct_num":direct_num, "direct_active_id":direct_active_id , "direct_promo":direct_promo, "random":Math.random()}, function(json){
            if (json.success) {
                selectItem = json.tickets;
                //创建select
                var html = '<option value="0">不使用</option>';
                for(var _tmp = 0; _tmp < selectItem.length; _tmp++)
                {
                    var selected = selectItem[_tmp].card_name == ticket_code?'selected':'';
                    if (selectItem[_tmp].can_use) {
                        html += '<option value="'+selectItem[_tmp].id+'"  '+selected+'  data-code="'+selectItem[_tmp].card_name+'" seller="'+selectItem[_tmp].seller_id+'" price="'+selectItem[_tmp].value+'">减'+ selectItem[_tmp].value + '元：' + selectItem[_tmp].name+'</option>';
                    } else {
                        html += '<option value="'+selectItem[_tmp].id+'"  '+selected+' data-code="'+selectItem[_tmp].card_name+'"  seller="'+selectItem[_tmp].seller_id+'" price="'+selectItem[_tmp].value+'" disabled="disabled">(不可用)'+ selectItem[_tmp].name+'</option>';
                    }
                }
                $('#ticket_select').html(html);
                if(typeof(ticket_code) != 'undefined')
                {
                    _self.ticketShow2($("#ticket_select").find("option:selected"));
                }
            }
            else
            {
                layer.msg("找不到该代金券或被已使用");
            }
        });

    }

    /**
     * dyg_jzw 代金券显示 步骤2
     */
    this.ticketShow2 = function(obj)
    {
        //动态创建代金券节点
        _self.getForm().find("input[name='ticket_id[]']").remove();

        var tid = $(obj).attr('value');

        var resultTicket = 0;

        if (tid > 0) {

            var sid    = $(obj).attr('seller');
            var tprice = parseFloat($(obj).attr('price'));
            var tname = $(obj).text();

            //专用代金券
            if(_self.seller[sid] > 0)
            {
                resultTicket += (tprice >= _self.seller[sid]) ? _self.seller[sid] : tprice;
            }
            //通用代金券
            else if(sid == '0')
            {
                var maxPrice = 0;
                for(var sellerId in _self.seller)
                {
                    if(_self.seller[sellerId] > maxPrice)
                    {
                        maxPrice = _self.seller[sellerId];
                    }
                }
                resultTicket += (tprice >= maxPrice) ? maxPrice : tprice;
            }
            //动态插入节点
            _self.getForm().prepend("<input type='hidden' name='ticket_id[]' value='" + tid + "' />");

            //layer.msg("已选择代金券："+tname);
        } else {
            layer.msg("取消使用代金券");
            $("#ticket_select").find("option:selected").prop("selected", false);
        }
        _self.ticketPrice = resultTicket;
        _self.doAccount();
    }

    //获取form表单
    this.getForm = function()
    {
        return $('form[name="order_form"]').length == 1 ? $('form[name="order_form"]') : $('form:first');
    }
}