jQuery(function(){
	//高度自适应
	initLayout();
	$(window).resize(function()
	{
		initLayout();
	});
	function initLayout()
	{
		var h1 = document.documentElement.clientHeight - $("#header").outerHeight(true) - $("#info_bar").height();
		var h2 = h1 - $(".headbar").height() - $(".pages_bar").height() - 30;
		$('#admin_left').height(h1);
		$('#admin_right .content').height(h2);
	}

	//一级菜单切换
	$("#menu ul li:first-child").addClass("first");
	$("#menu ul li:last-child").addClass("last");
	$("[name='menu']>li").click(function(){
		$(this).siblings().removeClass("selected");
        $(this).addClass("selected");
	});

	//二级菜单展示效果
	$("ul.submenu>li>span").on(
		"click",
		function()
		{
			$(this).toggleClass('selected');
			$(this).next().toggle();
		}
	);
});