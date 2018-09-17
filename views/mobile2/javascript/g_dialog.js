

var G_Dialog = function(config){

    var _this = this;

    //默认配置参数
    this.config = {
        //对话框宽高
        width:'auto',
        height:'auto',
        title: null,
        //message
        message: null,
        //对话框类型
        type: 'loading',
        //延时关闭时间
        delay: null,
        //按钮配置
        button: null,
        //ifram
        iframSrc: null,
        //点击遮罩层是否关闭
        maskClose: null
    };

    //默认参数扩展
    if(config && $.isPlainObject(config)){
        $.extend(this.config, config);
    }else{
        this.isNotConfig = true;
    }

    //创建基本的DOM
    this.body = $('body');
    //创建遮罩层
    this.mask = $('<div class="g-dialog-container">');
    this.win = $('<div class="dialog-window">');
    this.winHeader = $('<div class="dialog-header">');
    this.winContent = $('<div class="dialog-content">');
    this.winFooter = $('<div class="dialog-footer">');

    //渲染DOM
    this.rendUI();

};

G_Dialog.prototype = {

    //创建弹出框
    rendUI: function(){
        var _this = this,
            config = this.config,
            mask = this.mask,
            win = this.win,
            header = this.winHeader,
            content = this.winContent,
            footer = this.winFooter,
            body  =this.body;

        //如果没有传递任何配置参数，就弹出 loading模块
        if(this.isNotConfig){
            win.append(header.html("正在执行，请稍后..."));
            mask.append(win);
            body.append(mask);
        }else{
            //根据配置参数创建相应弹窗
            if(config.title){
                win.append(header.html(config.title));
            }
            if(config.type == "close") {
                $('.g-dialog-container').remove();
                return true;
            }else if(config.type == "ifram" && config.iframSrc != null){
                config.id = config.id? config.id : "iframeWin";
                var ifram = $('<iframe id="'+config.id+'" width="100%" height="400px" frameborder="0"></iframe>');
                ifram.attr("src", config.iframSrc);
                win.append(content.html(ifram));
            }else{
                //如果传了信息文本
                if(config.message){
                    win.append(content.html(config.message));
                }
            }
            //按钮组
            if(config.button){
                //创建按钮
                this.createButton(footer, config.button);
                win.append(footer);
            }

            //插入到页面
            mask.append(win);
            body.append(mask);

            //设置对话框宽高
            if(config.width!="auto"){
                win.width(config.width);
            }
            if(config.height!="auto"){
                win.height(config.height);
            }

            //设置关闭自动时间
            if(config.delay && config.delay != 0){
                window.setTimeout(function(){
                    _this.close();
                },config.delay)
            }

            if(config.maskClose){
                mask.click(function(){
                    _this.close();
                })
            }
        }
    },
    //根据配置参数的button创建按钮列表
    createButton: function(footer, buttons){
          var _this = this;
         $(buttons).each(function(){
             //获取按钮样式回调和文本
             var type = this.type?this.type:"";
             var btnText = this.text?this.text:"确定";
             var callback = this.callback?this.callback:null;

             var button = $('<a href="javascript:void(0);" class="btn">');
             button.addClass(" btn-"+ type).text(btnText);

             if(callback){
                 button.click(function(e){
                     e.stopPropagation();
                     var isClose = callback();

                     if(isClose != false){
                         _this.close();
                     }
                 });
             }else{
                 button.click(function(){
                    _this.close();
                 });
             }

             footer.append(button);
         })
    },
    close: function(){

				
        this.mask.remove();
    }
};

window.G_Dialog = G_Dialog;

$.g_dialog = function(config){
    return new G_Dialog(config);
}
