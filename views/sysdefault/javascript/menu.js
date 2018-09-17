function initMenu(data,current)
{
	for(i in data)
	{
		if(data[i]['current'])
		{
			$('#menu ul').append('<li class="selected"><a href="#">'+data[i]['title']+'</a></li>');
            var list = data[i]['list'];
            var item = '';
            for(j in list)
            {
                item = '<li><span>'+j+'</span><ul name="menu">';
                for(k in list[j])
                {
                    if( current.indexOf(k) != -1) item +='<li class="selected"><a href="'+k+'">'+list[j][k].name+'</a></li>';
                    else item +='<li><a href="'+k+'">'+list[j][k].name+'</a></li>';
                }
                $('.submenu').append(item+'</ul></li>');
            }
		}
		else
		{
			$('#menu ul').append('<li><a href="'+data[i]['link']+'" hidefocus = "true">'+data[i]['title']+'</a></li>');
		}
	}
}
