<?php
/**
 * @copyright (c) 2015 aircheng.com
 * @file my_export.php
 * @brief 导出交易数据
 * @date 2016/08/05
 * @version 1.5
 */

class my_export
{
	
	//构造函数
	public function __construct()
	{
		
	}


    public function export($result, $start, $end)
    {

    	/**
    	 * 交易数据处理
    	 */
    	$orders_classify = array();//订单根据支付类型区分
		$orders_total = array(); //订单信息汇总
		$count_product = array(); //所有商品汇总
		$count_money = 0; //所有销售额汇总

		//获取大客户列表
		$memberObj = new IQuery('member as m');
		$memberObj->fields = 'u.username';
		$memberObj->join   = 'left join user as u on u.id=m.user_id';
		$memberObj->where  = "m.group_id in (10,11,12)";

		$big_customer_arr = $memberObj->find();

		$big_customer = array();
		foreach ($big_customer_arr as $_big_user)
		{
			$big_customer[] = $_big_user['username'];
		}		

		//特别支付方式归类
		$other_pay_type_arr = array(
								'支付宝手机网站支付' => '支付宝',
								'微信移动支付' => '微信支付',
								'微信二维码支付' => '微信支付',
								'易极付微信扫码支付'=> '易极付'
							);

		$_order_row = array();
		foreach ($result as $_key => $_row)
		{
			//替换支付方式
			if (isset($other_pay_type_arr[$_row['pay_name']]))
			{
				$_row['pay_name'] = $other_pay_type_arr[$_row['pay_name']];
			}

			//计算折扣
			if (intval($_row['discount']) != 0)
			{
				if ($_row['goods_nums'] == 0)
				{
					$_row['real_price'] += 0;
				}
				else
				{
					$_row['real_price'] += (($_row['real_price']*$_row['goods_nums']/$_row['real_amount'])*$_row['discount'])/$_row['goods_nums'];
				}
			}

			$_order_row[] = $_row;

			//订单按支付方式分类
			if (!isset($result[$_key+1]) || $_row['order_no'] != $result[$_key+1]['order_no']) 
			{
				if ($_order_row)
				{
					$orders_classify['东阳光商城_'.$_order_row[0]['pay_name']][] = $_order_row; //按支付方式归类

					//是否预存款，区分大客户和非大客户
					if ($_order_row[0]['pay_name'] == '预存款') 
					{
						if (in_array($_order_row[0]['username'], $big_customer)) 
						{
							$orders_classify['东阳光商城_'.$_order_row[0]['pay_name'].'_子表大客户'][] = $_order_row; //按支付方式归类
						}
						else
						{
							$orders_classify['东阳光商城_'.'_'.$_order_row[0]['pay_name'].'_子表非大客户'][] = $_order_row; //按支付方式归类
						}
					}
				}

				$_order_row = array();
			}
		}

		foreach($orders_classify as $key => $_orders)
		{
			foreach ($_orders as $_order) 
			{
				foreach ($_order as $_detail) 
				{
					$_tmp_goods = json_decode($_detail['goods_array'], true);

					if ($_tmp_goods['goodsno'])
					{
						if (! isset($orders_total[$key][$_tmp_goods['goodsno']]['item_name'])) 
						{
							$orders_total[$key][$_tmp_goods['goodsno']]['item_code'] = $_tmp_goods['goodsno']; //商品编码
							$orders_total[$key][$_tmp_goods['goodsno']]['item_name'] = $_tmp_goods['name'].$_tmp_goods['value']; //商品名称
							$orders_total[$key][$_tmp_goods['goodsno']]['qty'] = 0; //数量
							$orders_total[$key][$_tmp_goods['goodsno']]['amount_after'] = 0; //让利后金额
						}

						//汇总
						if(strpos($key, '子表') === FALSE)
						{
							if (! isset($count_product[$_tmp_goods['goodsno']]['item_name'])) 
							{
								$count_product[$_tmp_goods['goodsno']]['item_code'] = $_tmp_goods['goodsno']; //商品编码
								$count_product[$_tmp_goods['goodsno']]['item_name'] = $_tmp_goods['name'].$_tmp_goods['value']; //商品名称
								$count_product[$_tmp_goods['goodsno']]['qty'] = 0; //商品名称
								$count_product[$_tmp_goods['goodsno']]['amount_after'] = 0; //让利后金额
							}
						}

						//分类汇总
						$orders_total[$key][$_tmp_goods['goodsno']]['qty'] += $_detail['goods_nums']; //数量
						$orders_total[$key][$_tmp_goods['goodsno']]['amount_after'] += $_detail['real_price'] * $_detail['goods_nums']; //让利后金额

						if(strpos($key, '子表') === FALSE)
						{
							//总汇总
							$count_product[$_tmp_goods['goodsno']]['qty'] += $_detail['goods_nums']; //数量
							$count_product[$_tmp_goods['goodsno']]['amount_after'] += $_detail['real_price'] * $_detail['goods_nums']; //让利后金额
							$count_money += $_detail['real_price'] * $_detail['goods_nums']; //让利后金额
						}
					}
				}
			}
		}

		ksort($count_product);


		/**
    	 * 非本期支付的退款明细
    	 */
		$refundObj = new IQuery('refundment_doc as rc');
		$refundObj->fields = 'rc.*, pay.name as pay_name, u.username, og.goods_nums, og.goods_array';
		$refundObj->join   = 'left join order as o on o.id=rc.order_id
							  left join user as u on u.id=o.user_id
							  left join payment as pay on pay.id=o.pay_type
							  left join order_goods as og on find_in_set(og.id, rc.order_goods_id)';

		$refundObj->where  = "rc.pay_status = 2 and rc.dispose_time >= '{$start}' and rc.dispose_time < '{$end}' and rc.if_del = 0  and o.pay_time < '{$start}'";

		$refund_arr = $refundObj->find();

		$refund_list = array();
		if ($refund_arr) 
		{
			foreach ($refund_arr as $_value) //对退款明细进行处理
			{
				//替换支付方式
				if (isset($other_pay_type_arr[$_value['pay_name']]))
				{
					$_value['pay_name'] = $other_pay_type_arr[$_value['pay_name']];
				}

				if (isset($refund_list[$_value['order_no']]))
				{
					$refund_list[$_value['order_no']]['goods_list'][] = array(
																			'goods_nums' => $_value['goods_nums'],
																			'goods_array' => $_value['goods_array'],
																		);
				}
				else
				{
					$refund_list[$_value['order_no']] = $_value;
					unset($refund_list[$_value['order_no']]['goods_nums']);
					unset($refund_list[$_value['order_no']]['goods_array']);
					$refund_list[$_value['order_no']]['goods_list'][] = array(
																			'goods_nums' => $_value['goods_nums'],
																			'goods_array' => $_value['goods_array'],
																		);
				}
			}
		}


		/* 用户余额日志 */
		$accountObj = new IQuery('account_log as acc');
		$accountObj->fields = 'acc.*, u.username, m.true_name';
		$accountObj->join   = 'left join user as u on u.id=acc.user_id
							  left join member as m on m.user_id=acc.user_id';

		$accountObj->where  = "acc.time < '{$end}'";
		$accountObj->order = 'acc.id asc';

		$account_logs = $accountObj->find();

		$account_log_arr = array();
		foreach ($account_logs as $_log) //根据event和是否大客户分类
		{
			//是否大客户
			$is_big = intval(in_array($_log['username'], $big_customer));

			$account_log_arr[$is_big][$_log['username']]['balance'] = $_log['amount_log'];
			$account_log_arr[$is_big][$_log['username']]['username'] = $_log['username'];
			$account_log_arr[$is_big][$_log['username']]['true_name'] = $_log['true_name'];

			if (strtotime($_log['time']) < strtotime($start))
			{
				$account_log_arr[$is_big][$_log['username']]['begin_balance'] = $_log['amount_log'];
			}

			if (strtotime($_log['time']) >= strtotime($start))
			{
				if (! isset($account_log_arr[$is_big][$_log['username']][$_log['event']]))
				{
					$account_log_arr[$is_big][$_log['username']][$_log['event']] = 0;
				}
				$account_log_arr[$is_big][$_log['username']][$_log['event']] += $_log['amount'];
			}
		}

		//加载phpexcel
		$classFile = dirname(__FILE__) . '/PHPExcel.php';
		require($classFile);

		//汇总表头
		$g_total_head = array(
						'A' => '商品编号',
						'B' => '商品名称',
						'C' => '总数量',
						'D' => '销售总额',
					);
		//明细表头
		$g_detail_head = array(
						'A' => '平台单号',
						'B' => '平台名称',
						'C' => '创建时间',
						'D' => '支付时间',
						'E' => '会员名称',
						'F' => '商品编码',
						'G' => '商品名称',
						'H' => '数量',
						'I' => '单项合计',
						'J' => '运费',
						'K' => '订单金额',
						'L' => '支付方式',
						'M' => '支付金额',
						'N' => '支付交易号',
						'O' => '备注'
					);

		//退款明细表头
		$g_refund_head = array(
						'A' => '退款订单号',
						'B' => '退款完成时间',
						'C' => '用户名',
						'D'	=> '是否大客户',
						'E'	=> '退款商品',
						'F'	=> '退款方式',
						'G'	=> '退款金额',
					);

		//预存款明细表头
		$g_account_head = array(
						'A' => '用户名',
						'B' => '真实姓名',
						'C' => '本期初余额',
						'D' => '预存款充值',
						'E' => '支付',
						'F'	=> '退款',
						'G'	=> '提现申请',
						'H'	=> '取消提现',
						'I'	=> '佣金',
						'J'	=> '本期末余额',
					);

		//反转表头
		$g_detail_index = array_flip($g_detail_head);
		$g_refund_index = array_flip($g_refund_head);
		$g_account_index = array_flip($g_account_head);

		//需合并单元格的列
		$g_detail_merge = 'A,B,C,D,E,J,K,L,M,N,O';

		$objPHPExcel = new PHPExcel();

		/**
		 * 设置文档属性
		 */
		$objPHPExcel->getProperties()
					->setCreator("www.dyg.cn")
					->setLastModifiedBy("dyg")
					->setTitle("【东阳光大健康电商】交易数据导出")
					->setSubject("【东阳光大健康电商】交易数据导出")
					->setDescription("【东阳光大健康电商】交易数据导出")
					->setKeywords("【东阳光大健康电商】交易数据导出")
					->setCategory("【东阳光大健康电商】");

		/**
		 * 初始化汇总页
		 */
		$objPHPExcel->setActiveSheetIndex(0);					 
		$objPHPExcel->getActiveSheet()->setTitle('汇总');

		/**
		 * 退款明细
		 */
		$newWorkSheet = new PHPExcel_Worksheet($objPHPExcel, '处理上期退款明细'); 
		$objPHPExcel->addSheet($newWorkSheet);
		$objPHPExcel->setActiveSheetIndexByName('处理上期退款明细');

		$index = 1;

		foreach ($g_refund_head as $head_index => $head_title) 
		{
			$objPHPExcel->getActiveSheet()->getColumnDimension($head_index)->setAutoSize(true);
			$objPHPExcel->getActiveSheet()->setCellValue($head_index.$index, $head_title);
		}

		foreach ($refund_list as $_refund)
		{
			$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_refund_index['退款订单号']. ++$index, $_refund['order_no'], PHPExcel_Cell_DataType::TYPE_STRING);
			$objPHPExcel->getActiveSheet()->setCellValue($g_refund_index['退款完成时间'].$index, $_refund['dispose_time']);
			$objPHPExcel->getActiveSheet()->setCellValue($g_refund_index['用户名'].$index, $_refund['username']);
			$objPHPExcel->getActiveSheet()->setCellValue($g_refund_index['是否大客户'].$index, in_array($_refund['username'], $big_customer)?'是':'否');
			
			$_refund_goods_str = '';
			foreach ($_refund['goods_list'] as $_good)
			{
				$_good_array = json_decode($_good['goods_array'], true);
				$_refund_goods_str .=  $_good_array['name'].$_good_array['value']." 退款数量:".$_good['goods_nums'] ."\n";
			}

			$objPHPExcel->getActiveSheet()->setCellValue($g_refund_index['退款商品'].$index, $_refund_goods_str);
			$objPHPExcel->getActiveSheet()->getStyle($g_refund_index['退款商品'].$index)->getAlignment()->setWrapText(true);

			$objPHPExcel->getActiveSheet()->setCellValue($g_refund_index['退款方式'].$index, $_refund['pay_name']);
			$objPHPExcel->getActiveSheet()->setCellValue($g_refund_index['退款金额'].$index, $_refund['amount']);
		}

		/**
		 * 预存款明细
		 */
		$newWorkSheet = new PHPExcel_Worksheet($objPHPExcel, '大客户预存款报表'); 
		$objPHPExcel->addSheet($newWorkSheet);
		$objPHPExcel->setActiveSheetIndexByName('大客户预存款报表');

		$index = 1;

		foreach ($g_account_head as $head_index => $head_title) 
		{
			$objPHPExcel->getActiveSheet()->getColumnDimension($head_index)->setAutoSize(true);
			$objPHPExcel->getActiveSheet()->setCellValue($head_index.$index, $head_title);
		}

		if(isset($account_log_arr[1]) && $account_log_arr[1])
		{
			foreach ($account_log_arr[1] as $_log)
			{
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_account_index['用户名']. ++$index, $_log['username'], PHPExcel_Cell_DataType::TYPE_STRING);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['真实姓名'].$index, $_log['true_name']);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['本期初余额'].$index, isset($_log['begin_balance']) ? $_log['begin_balance'] : 0);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['预存款充值'].$index, isset($_log[1]) ? $_log[1] : 0 );
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['支付'].$index, isset($_log[3]) ? $_log[3] : 0);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['退款'].$index, isset($_log[4]) ? $_log[4] : 0);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['提现申请'].$index, (isset($_log[5]) ? $_log[5] : 0) + (isset($_log[2]) ? $_log[2] : 0));
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['取消提现'].$index, (isset($_log[6]) ? $_log[6] : 0) + (isset($_log[7]) ? $_log[7] : 0));
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['佣金'].$index, isset($_log[8]) ? $_log[8] : 0);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['本期末余额'].$index, isset($_log['balance']) ? $_log['balance'] : 0);
			}
		}

		$newWorkSheet = new PHPExcel_Worksheet($objPHPExcel, '普通用户预存款报表'); 
		$objPHPExcel->addSheet($newWorkSheet);
		$objPHPExcel->setActiveSheetIndexByName('普通用户预存款报表');

		$index = 1;

		foreach ($g_account_head as $head_index => $head_title) 
		{
			$objPHPExcel->getActiveSheet()->getColumnDimension($head_index)->setAutoSize(true);
			$objPHPExcel->getActiveSheet()->setCellValue($head_index.$index, $head_title);
		}

		if(isset($account_log_arr[0]) && $account_log_arr[0])
		{
			foreach ($account_log_arr[0] as $_log)
			{
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_account_index['用户名']. ++$index, $_log['username'], PHPExcel_Cell_DataType::TYPE_STRING);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['真实姓名'].$index, $_log['true_name']);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['本期初余额'].$index, isset($_log['begin_balance']) ? $_log['begin_balance'] : 0);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['预存款充值'].$index, isset($_log[1]) ? $_log[1] : 0 );
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['支付'].$index, isset($_log[3]) ? $_log[3] : 0);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['退款'].$index, isset($_log[4]) ? $_log[4] : 0);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['提现申请'].$index, (isset($_log[5]) ? $_log[5] : 0) + (isset($_log[2]) ? $_log[2] : 0));
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['取消提现'].$index, (isset($_log[6]) ? $_log[6] : 0) + (isset($_log[7]) ? $_log[7] : 0));
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['佣金'].$index, isset($_log[8]) ? $_log[8] : 0);
				$objPHPExcel->getActiveSheet()->setCellValue($g_account_index['本期末余额'].$index, isset($_log['balance']) ? $_log['balance'] : 0);
			}
		}

		/**
		 * 各支付方式明细
		 */
		$post_fee_total = 0; //邮费合计
		$refund_total = 0; //退款合计
		foreach ($orders_total as $key => $_total) 
		{
			/**
			 * 创建新的页面
			 */
			$newWorkSheet = new PHPExcel_Worksheet($objPHPExcel, $key); 
			$objPHPExcel->addSheet($newWorkSheet);
			$objPHPExcel->setActiveSheetIndexByName($key);

			/**
			 * 先输出汇总
			 */
			//设置汇总表头
			$index = 1;

			foreach ($g_total_head as $head_index => $head_title) 
			{
				$objPHPExcel->getActiveSheet()->getColumnDimension($head_index)->setAutoSize(true);
				$objPHPExcel->getActiveSheet()->setCellValue($head_index.$index, $head_title);
			}

			foreach ($_total as $_t) 
			{
				$objPHPExcel->getActiveSheet()->setCellValue('A'.++$index, $_t['item_code']);
				$objPHPExcel->getActiveSheet()->setCellValue('B'.$index, $_t['item_name']);
				$objPHPExcel->getActiveSheet()->setCellValue('C'.$index, $_t['qty']);
				$objPHPExcel->getActiveSheet()->setCellValue('D'.$index, $_t['amount_after']);
			}

			//邮费合计
			$formulas = "=SUM(".$g_detail_index['运费'].":".$g_detail_index['运费'].")";
			$objPHPExcel->getActiveSheet()->setCellValue('C'.++$index, '邮费合计');
			$objPHPExcel->getActiveSheet()->setCellValue('D'.$index, $formulas);

			//处理上期的退款汇总
			$objPHPExcel->getActiveSheet()->setCellValue('C'.++$index, '处理上期的退款汇总');

			$refunds = 0;
			if ($refund_list)
			{
				foreach ($refund_list as $_refund)
				{
					//根据支付方式添加退款金额
					if (  ('东阳光商城_'.$_refund['pay_name'] == $key)
						  || (strpos($key, '_子表大客户') !== false && $_refund['pay_name'] == '预存款' && in_array($_refund['username'], $big_customer))
						  || (strpos($key, '_子表非大客户') !== false && $_refund['pay_name'] == '预存款' && !in_array($_refund['username'], $big_customer))
						)
					{
						$counted_refunds[] = $_refund['order_no'];
						$refunds += $_refund['amount'];

						if (strpos($key, '_子表') == false)
						{
							$refund_total += $_refund['amount'];
						}
					}
				}
			}
			$objPHPExcel->getActiveSheet()->setCellValue('D'.$index, -$refunds);
			

			$objPHPExcel->getActiveSheet()->setCellValue('C'.++$index, '总合计');
			$objPHPExcel->getActiveSheet()->setCellValue('D'.$index, '=SUM(D2:D'.($index-1).')');

			//换行
			$index += 4;

			//设置明细表头
			foreach ($g_detail_head as $head_index => $head_title) 
			{
				$objPHPExcel->getActiveSheet()->getColumnDimension($head_index)->setAutoSize(true);
				$objPHPExcel->getActiveSheet()->setCellValue($head_index.$index, $head_title);
			}

			//插入订单明细
			foreach ($orders_classify[$key] as $_order) 
			{
				$now_index = ++$index;
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_detail_index['平台单号'].$index, $_order[0]['order_no'], PHPExcel_Cell_DataType::TYPE_STRING);
				$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['平台名称'].$index, '东阳光商城');
				$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['创建时间'].$index, $_order[0]['create_time']);
				$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['支付时间'].$index, $_order[0]['pay_time']);
				$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['会员名称'].$index, $_order[0]['username']);

				if(strpos($key, '子表') === FALSE)
				{
					$post_fee_total += $_order[0]['real_freight'];
				}
				$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['运费'].$index, $_order[0]['real_freight']);
				$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['订单金额'].$index, $_order[0]['order_amount']);
				$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['备注'].$index, $_order[0]['note']);
				
				$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['支付方式'].$index, $_order[0]['pay_name']);
				$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['支付金额'].$index, $_order[0]['order_amount']);
				$objPHPExcel->getActiveSheet()->setCellValueExplicit($g_detail_index['支付交易号'].$index, $_order[0]['trade_no'], PHPExcel_Cell_DataType::TYPE_STRING);

				$index--;
				foreach ($_order as $_d) 
				{
					$_tmp_goods = json_decode($_d['goods_array'], true);
					$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['商品编码'].++$index, $_tmp_goods['goodsno']);
					$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['商品名称'].$index, $_tmp_goods['name'].$_tmp_goods['value']);
					$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['数量'].$index, $_d['goods_nums']);
					
					if ($_d['goods_nums'] == 0) //退款订单
					{
						$objPHPExcel->getActiveSheet()->getStyle($g_detail_index['数量'].$index)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB("FFFFAA00");
					}
					$objPHPExcel->getActiveSheet()->setCellValue($g_detail_index['单项合计'].$index, $_d['real_price']*$_d['goods_nums']);
				}

				//合并单元格
				$mergeArr = explode(',', $g_detail_merge);
				foreach ($mergeArr as $_merge_index) 
				{
					$objPHPExcel->getActiveSheet()->mergeCells($_merge_index.$now_index.':'.$_merge_index.$index);
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

		foreach ($count_product as $_count) 
		{
			$objPHPExcel->getActiveSheet()->setCellValue('A'.++$index, $_count['item_code']);
			$objPHPExcel->getActiveSheet()->setCellValue('B'.$index, $_count['item_name']);
			$objPHPExcel->getActiveSheet()->setCellValue('C'.$index, $_count['qty']);
			$objPHPExcel->getActiveSheet()->setCellValue('D'.$index, $_count['amount_after']);
		}

		//邮费合计
		$objPHPExcel->getActiveSheet()->setCellValue('C'.++$index, '邮费合计');
		$objPHPExcel->getActiveSheet()->setCellValue('D'.$index, $post_fee_total);

		//退款合计
		$objPHPExcel->getActiveSheet()->setCellValue('C'.++$index, '处理上期的退款汇总');
		$objPHPExcel->getActiveSheet()->setCellValue('D'.$index, -$refund_total);

		$objPHPExcel->getActiveSheet()->setCellValue('C'.++$index, '总合计');
		$objPHPExcel->getActiveSheet()->setCellValue('D'.$index, '=SUM(D2:D'.($index-1).')');

		// Redirect output to a client’s web browser (Excel2007)
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="【东阳光大健康电商】交易数据导出_导出时间'.date("Y-m-d_His").'.xlsx"');
		header('Cache-Control: max-age=0');

		// If you're serving to IE over SSL, then the following may be needed
		
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
		$objWriter->save('php://output');
    }

}