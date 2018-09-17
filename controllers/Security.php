<?php
class Security extends IController
{

	public function get_result_json_ds23gjgj_34rjfnf_n211ob345jhghngvs()
	{
		//防伪码
		$_code = $this->input->get('c', true);

		$result = $this->get_fw($_code);

		echo json_encode($result);
	}

	
	//获取防伪码信息
	private function get_fw($fwc)
	{
		//查询防伪码属于哪个库
		$this->db2 = $this->load->database('fwcode', true);
		$this->load->model('Sec_mdl');

		$fwc_info = $this->Sec_mdl->get_more_info_by_fwc($fwc);
		$data_arr = array('stat' => 0, 'scode' => $fwc, 'msg' => '防伪码错误');

		if ($fwc_info)
		{
			if($fwc_info['database'] == 'fwc_1m')
			{
				$url = 'http://www.zxfw315.com/cq.php?fwkey=48c2cOLrM4lmN2DybMKzplHcnzrohFXEW8pZ8yj3bcXE&fwcode='.$fwc;
				$get_data = file_get_contents($url);
			}
			elseif ($fwc_info['database'] == 'fwc_xc')
			{
				$url = 'http://tyfwjk.ty-315.com/api/FW/QueryA?fwm='.$fwc.'&ip='.rand(100,255).rand(100,255).rand(100,255).rand(100,255);
				$get_data = file_get_contents($url);
			}

			$data_arr = $this->Sec_mdl->format_fwc_return($fwc_info['database'], $get_data);

			//查询商品详情 根据防伪码包ID查询对应的产品分类
			$fwc_2_product = $this->Sec_mdl->get_fw_2_product_by_typeid($fwc_info['database'], $fwc_info['type_id']);

			//获取产品分类详情
			$data_arr['product_info'] = $this->Sec_mdl->get_product_info_by_classid($fwc_2_product['product_classid']);
		}

		return $data_arr;
	}

}
