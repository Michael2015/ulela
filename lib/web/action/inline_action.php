<?php
/**
 * @copyright (c) 2016 aircheng.com
 * @file inline_action.php
 * @brief 控制器内部action
 * @author nswe
 * @date 2016/3/7 9:07:57
 * @version 4.4
 */

/**
 * @class IInlineAction
 * @brief 控制器内部action
 */
class IInlineAction extends IAction
{
	/**
	 * @brief 内部action动作执行方法
	 */
	public function run()
	{
		$controller = $this->getController();
		$methodName = $this->getId();
		is_callable($controller->$methodName) ? call_user_func($controller->$methodName) : call_user_func(array($controller,$methodName));
	}
}