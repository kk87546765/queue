<?php
require_once dirname(__FILE__).'/ChannelCallback.php';
require_once(dirname(dirname(__FILE__)) . "/db/class.db.php");
require_once dirname(dirname(__FILE__)).'/db/RedisModel.class.php';
require_once(dirname(dirname(__FILE__)) . "/model/StorageAdv.php");

class Report
{
	public $redis;

	public function __construct(){
		$this->redis = RedisModel::instance();
	}

	/**
	 * [Run description]
	 * @param [type] $data [description]
	 */
	public function run($data)
	{
        $channel_rs = true;
        if($data['common_log']) {
            if ($data['device'] == 1) $data['ver_id'] = $data['common_log']['channel_ver'];
            $adv_db = new storageAdv();
            $ver_info = $adv_db->getInfo($data['ver_id']);
            if(isset($ver_info['callback_func']) && $ver_info['callback_func']){
                $callback_func = $ver_info['callback_func'];// 调用渠道回调函数
                try{
                    $data = array_merge($ver_info,$data);
                    $file = dirname(dirname(__FILE__)) . "/callback_model/{$callback_func}.php";
                    if (file_exists($file)){
                        require_once(dirname(dirname(__FILE__)) . "/callback_model/{$callback_func}.php");
                        $mod = new $callback_func();
                        $channel_rs = $mod->index($data);
                    }
                } catch (Exception $e){
                    echo $e->getMessage();
                    $channel_rs = true;
                }
            }else {
                $channel_rs = true;
            }
		}

		if(isset($channel_rs) && $channel_rs) {
			return true;
		} else {
			return false;
		}
	}

	public function getChannelConfig($channel_from = '') 
	{
		$expires_time = $this->redis->hget('config_expires', 'channel_expires_time');

		if(empty($expires_time) || $expires_time < time() - 300) {
			$db = Database::instance();
			$db->table('tbl_channel_config', 'adv');
			$db->select(array('condition'=>' where `status`=1'));
			$configs = $db->get();

			$this->redis->del('channel_config');
			foreach($configs as $key=>$value){
				if($value['callback_func']){
					$this->redis->hset('channel_config', $value['channel_id'], json_encode($value));
				}
			}
			$this->redis->hset('config_expires', 'channel_expires_time', time());
		}

		$channel_config = $this->redis->hget('channel_config', $channel_from);
		$channel_config = json_decode($channel_config, true);
		return $channel_config;
	}
}