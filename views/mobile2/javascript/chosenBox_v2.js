(function($){

	var ChosenBox = (function(){

		//私有变量
		var $buyBtn = $('#buy-btn'),
            $chosenBox = $('#chosenBox'),
            $chosenBoxCloseBtn = $('.chosenBox-closeBtn'),
            $mask = $('#mask'),
            $addNumBtn = $('.add-num'),
            $reduceNumBtn = $('.reduce-num'),
            $leavenum = $('.leave-num');
        var goodnum = $('.good-num').text();
        var leavenum = $leavenum.text();
        
        var $chosenBoxItemCopy = $('<a href="javascript:void(0)" class="chosenBox-item"></a>');

		//私有静态方法
		var renderUI = function(data){
            $('.good-pic').attr('src',data["good-detail"]["good-album"][0]);
            $('.good-title').text(data["good-detail"]["title"]);
            $('.good-price').text(data["good-detail"]["price"]);
            $('#level-list').empty();
            $('#suite-list').empty();
            $('#package-list').empty();
            $('#level-list').append($('<p class="type">级别：</p>'));
            for(var i=0;i<data["good-detail"]["level"].length;i++){
                var levelItem = $chosenBoxItemCopy.clone();
                levelItem.text(data["good-detail"]["level"][i]);
                //$('#level-list').append(levelItem);
                levelItem.appendTo($('#level-list')).bind('click',onItemClick);
            }
            $('#suite-list').append($('<p class="type">套装：</p>'));
            for(var i=0;i<data["good-detail"]["suite"].length;i++){
                var suiteItem = $chosenBoxItemCopy.clone();
                suiteItem.text(data["good-detail"]["suite"][i]);
                //$('#suite-list').append(suiteItem);
                suiteItem.appendTo($('#suite-list')).bind('click',onItemClick);
            }
            $('#package-list').append($('<p class="type">包装类型：</p>'));
            for(var i=0;i<data["good-detail"]["package"].length;i++){
                var packageItem = $chosenBoxItemCopy.clone();
                packageItem.text(data["good-detail"]["package"][i]);
                //$('#package-list').append(packageItem);
                packageItem.appendTo($('#package-list')).bind('click',onItemClick);
            }

        };

        var onItemClick = function(){
            $(this).siblings('.chosenBox-item').removeClass('choose');
            $(this).addClass('choose');
        };
        
        var getGoodById = function(goodId){
            //var good;
            $.getJSON('{url:/simple/getProducts}', {id:goodId}, function(data) {
                /*for(var i=0;i<data.length;i++){

                    if(Number(data[i]["good-id"])==Number(goodId)){
                        good=data[i];
                        renderUI(good);
                    }
                }*/
                renderUI(good);
                console.log(data);
        	});
        };

        var bindUI = function(instance){

        	instance.on('buyBtnClick',function(data){
        		getGoodById(data.goodId);
        		$mask.show();
        		$chosenBox.addClass('on');
        	});

        	$('#buy-btn').on('click', function(){
				var goodid = $(this).attr('data-goodid');
				instance.fire('buyBtnClick',{goodId: goodid});
        	});
            $('#test').on('click', function(){
                var goodid = $(this).attr('data-goodid');
                instance.fire('buyBtnClick',{goodId: goodid});
            });
        	$('#buy-btn2').on('click', function(){
				var goodid = $(this).attr('data-goodid');
				instance.fire('buyBtnClick',{goodId: goodid});
        	});
        	$('.chosenBox-closeBtn').on('click', function(){
        		$mask.hide();
            	$chosenBox.removeClass('on');
        	});
        	$addNumBtn.on('click', function(){
	            $leavenum.text(Number(leavenum) - Number(goodnum));
	            goodnum++;
	            $('.good-num').text(goodnum);
	        });
	        $reduceNumBtn.on('click', function(){
	            $leavenum.text(Number(leavenum) - Number(goodnum));
	            if(goodnum >=1){
	                goodnum--;
	                $('.good-num').text(goodnum);                
	            }
	        });
        };

		//初始化方法及构造函数
		var init = function(instance){
			bindUI(instance);
		};

		//真正的构造函数
		return function(){
			this.handlers=[];

			init(this);

		}
	})();

	ChosenBox.prototype = {
		on: function(type, handler){
            if(typeof this.handlers[type] === 'undefined'){
                this.handlers[type] =[];
            }
            this.handlers[type].push(handler);
        },
        fire: function(type, data){
            if(this.handlers[type] instanceof Array){
                var handlers = this.handlers[type];
                for(var i=0;i<handlers.length;i++){
                    handlers[i](data);
                }
            }
        }
	};

	window.ChosenBox = ChosenBox;

})(jQuery);