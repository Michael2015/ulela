<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file my_export.php
 * @brief 导出团队交易数据
 * @date 2016/08/29
 * @version 1.0
 */

class team_export
{
	
	//构造函数
	public function __construct()
	{
		
	}


    public function export($cat_ids, $start, $end)
    {
    	$start = date("Y-m-d H:i:s", strtotime($start));
    	$end = date("Y-m-d 23:59:59", strtotime($end));
    	$cat_ids = implode(',', $cat_ids);

    	$userDB = new IModel('user as u, member as m');
    	$orderDB = new IModel('order');
		$orderGoodsDB = new IModel('order_goods');

		//需统计产品分类
		$catDB = new IModel('category_extend');
		$goods_ids = $catDB->query("category_id in ({$cat_ids})");

		//所有符合统计分类的产品id
		$all_goods_ids = array();
		foreach ($goods_ids as $_goods)
		{
			$all_goods_ids[$_goods['goods_id']] = $_goods['goods_id'];
		}
		$all_goods_ids = implode(',', $all_goods_ids);

    	//获取全部组队信息
    	$myteamDB = new IModel('myteam_tag');
		$myteam_list = $myteamDB->query();

		//所有团队业绩统计
		$all_team_count = array();
		$all_team_members = array();
		$all_orders = array();
		$order_to_user = array(); //用户与订单的对应关系
		$user_to_team = array(); //用户与团队的对应关系
		$all_order_goods = array();

		foreach ($myteam_list as $_team)
		{
			$all_team_count[$_team['id']] = array(
									'name' => $_team['teamname'], //队名
									'members' => array(),
									'refund' => 0, //上期退款
									'score' => 0 //总成绩

								);

			//获取队长下面的各位队员
			$all_team_members[$_team['id']] = $userDB->query("(u.inviter = '{$_team['username']}' OR u.username = '{$_team['username']}') and u.id = m.user_id", 'u.id, u.username, m.true_name');
		}



		$all_user_ids = array();
		foreach ($all_team_members as $team_id => $_team)
		{
			foreach ($_team as $_member)
			{
				$all_user_ids[] = $_member['id'];

				$user_to_team[$_member['id']] = $team_id;
				
				$all_team_count[$team_id]['members'][$_member['id']]= array(
																		'user_id' => $_member['id'],
																		'username' => $_member['username'],
																		'true_name' => $_member['true_name'],
																		'score' => 0,
																		'refund' => 0, //上期退款
																		'order_goods' => array()
																	);
			}
			
		}
		$all_user_ids = implode(',', $all_user_ids);

		//获取所有已支付或已完成的订单
		$all_orders = $orderDB->query("user_id in ({$all_user_ids}) and if_del = 0 and status in (2,5,7) and pay_time > '{$start}' and pay_time < '{$end}'", 'id, user_id');

		$all_order_ids = array();
		foreach ($all_orders as $_order)
		{
			$all_order_ids[] = $_order['id'];
			$order_to_user[$_order['id']] = $_order['user_id'];
		}
		$all_order_ids = implode(',', $all_order_ids);

		//根据订单获取全部订单商品
		$all_order_goods = $orderGoodsDB->query("order_id in ({$all_order_ids}) and goods_id in ({$all_goods_ids}) and is_send <> 2");
		
		//订单商品分类
		foreach ($all_order_goods as $_order_goods)
		{
			$_user_id = $order_to_user[$_order_goods['order_id']];
			$all_team_count[$user_to_team[$_user_id]]['members'][$_user_id]['order_goods'][] = $_order_goods;
			$all_team_count[$user_to_team[$_user_id]]['members'][$_user_id]['score'] += $_order_goods['real_price'] * $_order_goods['goods_nums'];
			$all_team_count[$user_to_team[$_user_id]]['score'] += $_order_goods['real_price'] * $_order_goods['goods_nums'];
		}

		/**
    	 * 非本期支付的退款明细
    	 */
		$refundObj = new IQuery('order_goods as og');
		$refundObj->fields = 'og.*, o.user_id';
		$refundObj->join   = 'left join refundment_doc as rc on find_in_set(og.id ,rc.order_goods_id)
							left join order as o on o.id=rc.order_id';

		$refundObj->where  = "rc.user_id in ({$all_user_ids}) and og.goods_id in ({$all_goods_ids}) and rc.pay_status = 2 and rc.dispose_time >= '{$start}' and rc.dispose_time < '{$end}' and rc.if_del = 0  and o.pay_time < '{$start}'";

		$refund_arr = $refundObj->find();

		foreach ($refund_arr as $_r_order_goods)
		{
			$all_team_count[$user_to_team[$_r_order_goods['user_id']]]['members'][$_r_order_goods['user_id']]['refund'] += $_r_order_goods['real_price'] * $_r_order_goods['goods_nums'];
			$all_team_count[$user_to_team[$_r_order_goods['user_id']]]['refund'] += $_r_order_goods['real_price'] * $_r_order_goods['goods_nums'];
		}

		//加载phpexcel
		$classFile = dirname(__FILE__) . '/PHPExcel.php';
		require($classFile);

		//汇总表头
		$g_total_head = array(
						'A' => '用户尾号',
						'B' => '姓名',
						'C' => '本期销售额',
						'D' => '上期退款',
						'E' => '不参与销售额',
						'F' => '核算销售额'
					);
		//明细表头
		$g_detail_head = array(
						'A' => '用户尾号',
						'B' => '姓名',
						'C' => '产品名称',
						'E' => '产品名称合并',
						'F' => '数量'
					);

		//反转表头
		$g_total_index = array_flip($g_total_head);
		$g_detail_index = array_flip($g_detail_head);


		$borderStyleArray = array(  
	        'borders' => array(  
	            'allborders' => array(  
	                'style' => PHPExcel_Style_Border::BORDER_THIN,//细边框  
	            ),  
	        ),  
	    );  


		$objPHPExcel = new PHPExcel();

		/**
		 * 设置文档属性
		 */
		$objPHPExcel->getProperties()
					->setCreator("www.dyg.cn")
					->setLastModifiedBy("dyg")
					->setTitle("【东阳光大健康电商】团队交易数据导出")
					->setSubject("【东阳光大健康电商】团队交易数据导出")
					->setDescription("【东阳光大健康电商】团队交易数据导出")
					->setKeywords("【东阳光大健康电商】团队交易数据导出")
					->setCategory("【东阳光大健康电商】");

		/**
		 * 初始化汇总页
		 */
		$objPHPExcel->setActiveSheetIndex(0);					 
		$objPHPExcel->getActiveSheet()->setTitle('各队汇总');

		/**
		 * 各队伍明细
		 */
		
		foreach ($all_team_count as $_team)
		{
			$newWorkSheet = new PHPExcel_Worksheet($objPHPExcel, $_team['name']); 
			$objPHPExcel->addSheet($newWorkSheet);
			$objPHPExcel->setActiveSheetIndexByName($_team['name']);

			//设置页边距 和 页面横向
			$pageMargins = $objPHPExcel->getActiveSheet()->getPageMargins();
			$pageMargins->setTop(0);       //上边距
			$pageMargins->setBottom(0); //下边距
			$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);

			$index = 1;

			foreach ($g_total_head as $head_index => $head_title) 
			{
				$objPHPExcel->getActiveSheet()->getColumnDimension($head_index)->setAutoSize(true);
				$objPHPExcel->getActiveSheet()->setCellValue($head_index.$index, $head_title);
			}

			$form_start = $index + 1;
			foreach ($_team['members'] as $_member)
			{
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_total_index['用户尾号']. ++$index, substr($_member['username'], -4), PHPExcel_Cell_DataType::TYPE_STRING);
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_total_index['姓名']. $index, $_member['true_name'], PHPExcel_Cell_DataType::TYPE_STRING);
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_total_index['本期销售额']. $index, $_member['score']);
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_total_index['上期退款']. $index, $_member['refund']);
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_total_index['不参与销售额']. $index, 0);
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_total_index['核算销售额']. $index, $_member['score'] - $_member['refund']);
			}
			$form_end = $index;
			
			//设置边框
			$objPHPExcel->getActiveSheet()->getStyle($g_total_index['用户尾号'].$form_start.':'.$g_total_index['核算销售额'].$form_end)->applyFromArray($borderStyleArray);

			$index += 3;

			foreach ($g_detail_head as $head_index => $head_title) 
			{
				$objPHPExcel->getActiveSheet()->getColumnDimension($head_index)->setAutoSize(true);
				$objPHPExcel->getActiveSheet()->setCellValue($head_index.$index, $head_title);
			}

			foreach ($_team['members'] as $_member)
			{
				if ($_member['order_goods'])
				{
					$form_start = $index + 1;
					$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_detail_index['用户尾号']. ++$index, substr($_member['username'], -4), PHPExcel_Cell_DataType::TYPE_STRING);
					$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_detail_index['姓名']. $index, $_member['true_name'], PHPExcel_Cell_DataType::TYPE_STRING);
					
					$_tmp_goods = array();
					foreach ($_member['order_goods'] as $_goods)
					{
						$_goods_arr = json_decode($_goods['goods_array'], true);
						if (! isset($_tmp_goods[$_goods_arr['goodsno']]))
						{
							$_tmp_goods[$_goods_arr['goodsno']] = array(
																	'name' => $_goods_arr['name'].$_goods_arr['value'],
																	'goods_nums' => 0
																);
						}
						$_tmp_goods[$_goods_arr['goodsno']]['goods_nums'] += $_goods['goods_nums'];
					}

					foreach ($_tmp_goods as $_goods)
					{
						$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_detail_index['产品名称']. $index, $_goods['name'], PHPExcel_Cell_DataType::TYPE_STRING);
						$objPHPExcel->getActiveSheet()->mergeCells($g_detail_index['产品名称'].$index.':'.$g_detail_index['产品名称合并'].$index);
						$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_detail_index['数量']. $index++, $_goods['goods_nums'], PHPExcel_Cell_DataType::TYPE_STRING);
					}
					$form_end = $index - 1;

					//合并单元格并画边框
					$objPHPExcel->getActiveSheet()->mergeCells($g_detail_index['用户尾号'].$form_start.':'.$g_detail_index['用户尾号'].$form_end);
					$objPHPExcel->getActiveSheet()->mergeCells($g_detail_index['姓名'].$form_start.':'.$g_detail_index['姓名'].$form_end);
					$objPHPExcel->getActiveSheet()->getStyle($g_detail_index['用户尾号'].$form_start)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
					$objPHPExcel->getActiveSheet()->getStyle($g_detail_index['姓名'].$form_start)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
					$objPHPExcel->getActiveSheet()->getStyle($g_detail_index['用户尾号'].$form_start.':'.$g_detail_index['数量'].$form_end)->applyFromArray($borderStyleArray);
				}
			}

		}

		// Set active sheet index to the first sheet, so Excel opens this as the first sheet
		$objPHPExcel->setActiveSheetIndex(0);

		/**
		 * 输出汇总页
		 */
		$index = 1;
		//设置表头
		foreach ($g_total_head as $head_index => $head_title) 
		{
			$objPHPExcel->getActiveSheet()->getColumnDimension($head_index)->setAutoSize(true);
			$objPHPExcel->getActiveSheet()->setCellValue($head_index.$index, $head_title);
		}

		foreach ($all_team_count as $_team)
		{
			$objPHPExcel->getActiveSheet()->setCellValue($g_total_index['姓名'].++$index, $_team['name']);
			$objPHPExcel->getActiveSheet()->setCellValue($g_total_index['本期销售额'].$index, $_team['score']);
			$objPHPExcel->getActiveSheet()->setCellValue($g_total_index['上期退款'].$index, $_team['refund']);
			$objPHPExcel->getActiveSheet()->setCellValue($g_total_index['核算销售额'].$index, $_team['score'] - $_team['refund']);
		}

		// Redirect output to a client’s web browser (Excel2007)
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="【东阳光大健康电商】团队交易数据导出_导出时间'.date("Y-m-d_His").'.xlsx"');
		header('Cache-Control: max-age=0');

		// If you're serving to IE over SSL, then the following may be needed
		
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$objWriter->save('php://output');
    }

}