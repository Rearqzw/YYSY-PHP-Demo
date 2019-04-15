<?php
namespace app\admin\controller;

use think\Db;

class Access
{
    private  $accessKey='1C1D365EEEAB40FD8E56901E27243DA0';
    private  $secretKey='3BDAF00B6F374A01A4DFBE32257F7E05';
    private $url='http://192.168.1.15';
    public $api_method = '';

    public function  quotaAPI()
    {



        date_default_timezone_set('UTC');


        $this->api_method='/Access/quotaAPI';
        $bodyData = @file_get_contents('php://input');
        //将获取到的值转化为数组格式
        $bodyData = json_decode($bodyData,true);

        if(empty($bodyData)){
            exit(json_encode(['result'=>'erro','msg'=>urlencode('参数错误!')]));
        }

        //验证参数
        $sign=isset($bodyData['sign']) ? $bodyData['sign']:'';
        $quota=isset($bodyData['quota']) ? $bodyData['quota']:'';
        $id=isset($bodyData['openid']) ? $bodyData['openid']:'';
        $timestamp=isset($bodyData['timestamp'])?$bodyData['timestamp']:'';
        if($sign=='' or $quota=='' or $id=='' or $timestamp==''){

            exit(json_encode(['result'=>'erro','msg'=>urlencode('参数错误')]));
        }



        if(!(date('Y-m-d H:i:s',strtotime('+5 minute'))>=$this->getMsecToMescdate($timestamp) and $this->getMsecToMescdate($timestamp) >=date('Y-m-d H:i:s',strtotime('-5 minute')))){


            exit(json_encode(['result'=>'erro','msg'=>urlencode('时间戳错误')]));
        }


        $param=[
            'accessKey'=>$this->accessKey,
            'id'=>$id,
            'quota'=>$quota,
            'timestamp'=>$timestamp
        ];





        //验证签名
        if($this->vaildate($param,$sign)==false){

            return json_encode(['result'=>'erro','msg'=>urlencode('签名错误!')]);
        }

        $sql="select id from t_user where id=".$id;
        $only=Db::execute( $sql );
        if(empty($only)){
            exit(json_encode(['result'=>'erro','msg'=>urlencode('用户不存在!')]));
        }

        $sysquota=$quota*0.01;
        $sql="update t_user set quota=quota+".$quota." where id=".$id;
        $success=Db::execute( $sql );
        if($success){
            $arr=array(
                'quota'=>$quota,
                'userid'=>$id,
                'type'=>1,
            );
            Db::table('t_user_log')
                ->insertGetId($arr);
            Db::execute('call updataBusinessQuota');

            $sql="update t_user set quota=quota+".$sysquota." where id=".$id;
            $success=Db::execute( $sql );

            $arr=array(
                'quota'=>$sysquota,
                'userid'=>$id,
                'type'=>0,
            );
            Db::table('t_user_log')
                ->insertGetId($arr);
            Db::execute('call updataBusinessQuota');
        }

        exit(json_encode(['result'=>'suc','msg'=>urlencode('额度增加成功!')]));


    }

    // 生成验签URL
    private function create_sign($param) {


        $sign_param_1 = $this->url.$this->api_method."?".implode('&', $param);

        $signature = hash_hmac('sha256', $sign_param_1, $this->secretKey, true);

        return base64_encode($signature);
    }

    //验证签名是否有效

    private function vaildate($param,$questsign){
        $sign=$this->bind_param($param);
        $sign=str_replace("+"," ",$sign);

        if($questsign==$sign)
        {
            return true;
        }else{
            return false;
        }

    }

    private function bind_param($param) {
        $u = [];
        $sort_rank = [];
        foreach($param as $k=>$v) {
            $u[] = $k."=".urlencode($v);
            $sort_rank[] = ord($k);
        }
        asort($u);

        return $this->create_sign($u);
    }


    /**
     *时间戳 转   日期
     */
    public function getMsecToMescdate($time) {

            $tag='Y-m-d H:i:s';
            $a = substr($time,0,10);
            $b = substr($time,10);
            $date = date($tag,$a).'.'.$b;
            return $date;

    }

}
