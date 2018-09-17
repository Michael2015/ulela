<?php
/**
 * @class Comment
 * @brief 评论模块
 * @note  后台
 */
class Comment extends IController implements adminAuthorization
{
	public $checkRight  = 'all';
    public $layout='admin';
	private $data = array();

	function init()
	{

	}

	function suggestion_list()
	{
		$search = IFilter::act(IReq::get('search'),'strict');
		$where = Util::search($search);
		$this->data['search'] = $search;

		$this->data['where'] = $where;
		$this->setRenderData($this->data);

		$this->redirect("suggestion_list");
	}

	/**
	 * @brief 显示建议信息
	 */
	function suggestion_edit()
	{
		$id = intval(IReq::get('id'));
		if(!$id)
		{
			$this->comment_list();
			return false;
		}
		$query = new IQuery("suggestion as a");
		$query->join = "left join user AS b ON a.user_id=b.id";
		$query->where = "a.id={$id}";
		$query->fields = "a.*,b.username";
		$items = $query->find();

		if(is_array($items) && $items)
		{
			$this->suggestion = $items[0];
			$this->redirect('suggestion_edit');
		}
		else
		{
			$this->suggestion_list();
		}
	}

	/**
	 * @brief 回复建议
	 */
	function suggestion_edit_act()
	{
		$id = intval(IReq::get('id','post'));
		$re_content = IFilter::act( IReq::get('re_content','post') ,'string');
		$tb = new IModel("suggestion");
		$data = array('admin_id'=>$this->admin['admin_id'],'re_content'=>$re_content,'re_time'=>ITime::getDateTime());
		$tb->setData($data);
		$tb->update("id={$id}");
		$this->redirect("/comment/suggestion_list");
	}


	/**
	 * @brief 删除要删除的建议
	 */
	function suggestion_del()
	{
		$suggestion_ids = IReq::get('check');
		$suggestion_ids = is_array($suggestion_ids) ? $suggestion_ids : array($suggestion_ids);
		if($suggestion_ids)
		{
			$suggestion_ids = IFilter::act($suggestion_ids,'int');

			$ids = implode(',',$suggestion_ids);
			if($ids)
			{
				$tb_suggestion = new IModel('suggestion');
				$where = "id in (".$ids.")";
				$tb_suggestion->del($where);
			}
		}
		$this->suggestion_list();
	}

	/**
	 * @brief 评论信息列表
	 */
	function comment_list()
	{
		$search = IFilter::act(IReq::get('search'),'strict');
		$where = Util::search($search);

		$this->data['where'] = 'c.status = 1 and '.$where;
		$this->data['search']= $search;
		$this->setRenderData($this->data);
		$this->redirect('comment_list');
	}

	/**
	 * @brief 显示评论信息
	 */
	function comment_edit()
	{
		$cid = IFilter::act(IReq::get('cid'),'int');

		if(!$cid)
		{
			$this->comment_list();
			return false;
		}
		$query = new IQuery("comment as c");
		$query->join = "left join goods as goods on c.goods_id = goods.id left join user as u on c.user_id = u.id";
		$query->fields = "c.*,u.username,goods.name,goods.seller_id";
		$query->where = "c.id=".$cid;
		$items = $query->find();

		if($items)
		{
			$this->comment = current($items);
			$this->redirect('comment_edit');
		}
		else
		{
			$this->comment_list();
			$msg = '没有找到相关记录！';
			Util::showMessage($msg);
		}
	}

	/**
	 * @brief 删除要删除的评论
	 */
	function comment_del()
	{
		$comment_ids = IReq::get('check');
		$comment_ids = is_array($comment_ids) ? $comment_ids : array($comment_ids);
		if($comment_ids)
		{
			$comment_ids = IFilter::act($comment_ids,'int');
			$tb_comment  = new IModel('comment');
			foreach($comment_ids as $key => $cid)
			{
				$commentRow = $tb_comment->getObj('id = '.$cid);

				//同步更新goods表,comments,grade
				$goodsDB = new IModel('goods');
				$goodsDB->setData(array(
					'comments' => 'comments - 1',
					'grade'    => 'grade - '.$commentRow['point'],
				));
				$goodsDB->update('id = '.$commentRow['goods_id'],array('grade','comments'));

				//同步更新seller表,comments,grade
				$sellerDB = new IModel('seller');
				$sellerDB->setData(array(
					'comments' => 'comments - 1',
					'grade'    => 'grade - '.$commentRow['point'],
				));
				$sellerDB->update('id = '.$commentRow['seller_id'],array('grade','comments'));

				//删除评论
				$tb_comment->del("id = ".$cid);
			}
		}
		$this->comment_list();
	}

	/**
	 * @brief 回复评论
	 */
	function comment_update()
	{
		$id = IFilter::act(IReq::get('id'),'int');
		$recontent = IFilter::act(IReq::get('recontents'));
		if($id)
		{
			$updateData = array(
				'recontents' => $recontent,
				'recomment_time' => ITime::getDateTime(),
			);
			$commentDB = new IModel('comment');
			$commentDB->setData($updateData);
			$commentDB->update('id = '.$id);
		}
		$this->redirect('comment_list');
	}

	/**
	 * @brief 讨论信息列表
	 */
	function discussion_list()
	{
		$search = IFilter::act(IReq::get('search'),'strict');
		$where = Util::search($search);
		$this->data['search'] = $search;
		$this->data['where'] = $where;
		$this->setRenderData($this->data);
		$this->redirect('discussion_list');
	}

	/**
	 * @brief 显示讨论信息
	 */
	function discussion_edit()
	{
		$did = intval(IReq::get('did'));
		if(!$did)
		{
			$this->discussion_list();
			return false;
		}
		$query = new IQuery("discussion as d");
		$query->join = "right join goods as goods on d.goods_id = goods.id left join user as u on d.user_id = u.id";
		$query->fields = "d.id,d.time,d.contents,u.id as userid,u.username,goods.id as goods_id,goods.name as goods_name";
		$query->where = "d.id=".$did;
		$items = $query->find();

		if($items)
		{
			$this->discussion = $items[0];
			$this->redirect('discussion_edit');
		}
		else
		{
			$this->discussion_list();
			$msg = '没有找到相关记录！';
			Util::showMessage($msg);
		}
	}

	/**
	 * @brief 删除讨论信息
	 */
	function discussion_del()
	{
		$discussion_ids = IReq::get('check');
		$discussion_ids = is_array($discussion_ids) ? $discussion_ids : array($discussion_ids);
		if($discussion_ids)
		{
			$ids = implode(',',$discussion_ids);
			if($ids)
			{
				$tb_discussion = new IModel('discussion');
				$where = "id in (".$ids.")";
				$tb_discussion->del($where);
			}
		}
		$this->discussion_list();
	}

	/**
	 * @brief 咨询信息列表
	 */
	function refer_list()
	{
		$search   = IFilter::act(IReq::get('search'),'strict');
		$where = Util::search($search);
		$this->data['search'] = $search;
		$this->data['where'] = $where;
		$this->setRenderData($this->data);
		$this->redirect('refer_list');
	}

	/**
	 * @brief 删除咨询信息
	 */
	function refer_del()
	{
		$refer_ids = IReq::get('check');
		$refer_ids = is_array($refer_ids) ? $refer_ids : array($refer_ids);
		if($refer_ids)
		{
			$refer_ids = IFilter::act($refer_ids,'int');
			$ids = implode(',',$refer_ids);
			if($ids)
			{
				$tb_refer = new IModel('refer');
				$where = "id in (".$ids.")";
				$tb_refer->del($where);
			}
		}
		$this->refer_list();
	}
	/**
	 * @brief 回复咨询信息
	 */
	function refer_reply()
	{
		$rid     = IFilter::act(IReq::get('refer_id'),'int');
		$content = IFilter::act(IReq::get('content'),'text');

		if($rid && $content)
		{
			$tb_refer = new IModel('refer');
			$admin_id = $this->admin['admin_id'];//管理员id
			$data     = array(
				'answer' => $content,
				'reply_time' => ITime::getDateTime(),
				'admin_id' => $admin_id,
				'status' => 1
			);
			$tb_refer->setData($data);
			$tb_refer->update("id=".$rid);
		}
		$this->refer_list();
	}

	/**
	 * @brief 会员消息列表
	 */
	function message_list()
	{
		$tb_user_group = new IModel('user_group');
		$data_group = $tb_user_group->query();
		$data_group = is_array($data_group) ? $data_group : array();
		$group      = array();
		foreach($data_group as $value)
		{
			$group[$value['id']] = $value['group_name'];
		}
		$this->data['group'] = $group;

		$this->setRenderData($this->data);
		$this->redirect('message_list');
	}

	/**
	 * @brief 删除会员消息
	 */
	function message_del()
	{
		$refer_ids = IFilter::act(IReq::get('check'));
		$refer_ids = is_array($refer_ids) ? $refer_ids : array($refer_ids);
		if($refer_ids)
		{
			$ids = implode(',',$refer_ids);
			if($ids)
			{
				$tb_refer = new IModel('message');
				$where = "id in (".$ids.")";
				$tb_refer->del($where);
			}
		}
		$this->message_list();
	}

	/**
	 * 发送会员消息
	 */
	function message_send()
	{
		$this->layout = '';
		$this->redirect('message_send');
	}

	/**
	 * @brief 发送信件
	 */
	function start_message()
	{
		$toUser  = IFilter::act(IReq::get('toUser'));
		$title   = IFilter::act(IReq::get('title'));
		$content = IFilter::act(IReq::get('content'),'text');

		if(!$title || !$content)
		{
			die('<script type="text/javascript">parent.startMessageCallback(0);</script>');
		}

		Mess::sendToUser($toUser,array('title' => $title,'content' => $content));
		die('<script type="text/javascript">parent.startMessageCallback(1);</script>');
	}

	/**
	 * @brief 商户消息列表
	 */
	function seller_message_list()
	{
		$this->redirect('seller_message_list');
	}

	/**
	 * @brief 发送商户消息页面
	 */
	function seller_message_send()
	{
		$this->layout = '';
		$this->redirect('seller_message_send');
	}

	/**
	 * @brief 发送商户消息
	 */
	function start_seller_message()
	{
		$toSeller  = IFilter::act(IReq::get('toSeller'));
		$title   = IFilter::act(IReq::get('title'));
		$content = IFilter::act(IReq::get('content'),'text');

		if(!$title || !$content)
		{
			die('<script type="text/javascript">parent.startMessageCallback(0);</script>');
		}
		seller_mess::send($toSeller, array('title' => $title, 'content' => $content));
		die('<script type="text/javascript">parent.startMessageCallback(1);</script>');
	}

	/**
	 * @brief 删除商户消息
	 */
	function seller_message_del()
	{
		$refer_ids = IReq::get('check');
		$refer_ids = is_array($refer_ids) ? $refer_ids : array($refer_ids);
		if($refer_ids)
		{
			$ids = implode(',',$refer_ids);
			if($ids)
			{
				$tb_refer = new IModel('seller_message');
				$where = "id in (".$ids.")";
				$tb_refer->del($where);
			}
		}
		$this->seller_message_list();
	}
}