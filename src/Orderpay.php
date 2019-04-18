<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2019-01-10
 * Time: 14:06
 */
namespace Pay\Orderpay;

use Illuminate\Config\Repository;
use Pay\Orderpay\aop\AopClient;
use Pay\Orderpay\aop\request\AlipayTradeAppPayRequest;
use Pay\Orderpay\aop\request\AlipayFundTransToaccountTransferRequest;

class Orderpay {
    protected $config;
    /**
     * 构造方法
     */
    public function __construct(Repository $config)
    {
        $this->config = $config->get('orderpay');
		
    }
	/**
     * 微信支付
     * @param unknown $total_fee 订单金额（单位分）
     * @param unknown $out_trade_no 商户订单号,不能重复
     * @param unknown $notify_url 回调地址,用户接收支付后的通知,必须为能直接访问的网址,不能跟参数
     */
    public function wx_pay($total_fee,$out_trade_no,$notify_url) {
        $nonce_str = $this->rand_code();        //调用随机字符串生成方法获取随机字符串
        $data['appid'] = $this->config['appid'];   //appid
        $data['mch_id'] = $this->config['mch_id'];        //商户号
        $data['body'] = "APP支付测试";
        $data['spbill_create_ip'] = $_SERVER['HTTP_HOST'];   //ip地址
        $data['total_fee'] = $total_fee;                         //金额
        $data['out_trade_no'] = $out_trade_no;    //商户订单号,不能重复
        $data['nonce_str'] = $nonce_str;                   //随机字符串
        $data['notify_url'] = $notify_url;   //回调地址,用户接收支付后的通知,必须为能直接访问的网址,不能跟参数
        $data['trade_type'] = 'APP';      //支付方式
        //将参与签名的数据保存到数组  注意：以上几个参数是追加到$data中的，$data中应该同时包含开发文档中要求必填的剔除sign以外的所有数据
        $data['sign'] = $this->getSign($data);        //获取签名
        $xml = $this->ToXml($data);            //数组转xml
        //curl 传递给微信方
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        //header("Content-type:text/xml");
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        if(stripos($url,"https://")!==FALSE){
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }    else    {
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        }
        //设置header
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        //传输文件
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            //返回成功,将xml数据转换为数组.
            $re = $this->FromXml($data);
            if($re['return_code'] != 'SUCCESS'){
                return [
					'status'=>false,
					'msg'=>'签名失败'
				];
            }
            else{
                //接收微信返回的数据,传给APP!
                $arr =array(
                    'prepayid' =>$re['prepay_id'],
                    'appid' => $this->config['appid'],
                    'partnerid' => $this->config['mch_id'],
                    'package' => 'Sign=WXPay',
                    'noncestr' => $nonce_str,
                    'timestamp' =>time(),
                );
                //第二次生成签名
                $sign = $this->getSign($arr);
                $arr['sign'] = $sign;
                return [
					'status'=>true,
					'msg'=>'签名成功'
				];
            }
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
			return [
				'status'=>false,
				'msg'=>"curl出错，错误码:$error"
			];
        }
    }
    
    
    /***************************支付宝支付*****************************/
    /**
     * 支付宝支付
     * @param unknown $order_data
     * @param unknown $total_fee 订单金额（单位元）
     * @param unknown $out_trade_no 商户订单号,不能重复
     * @param unknown $notify_url 回调地址,用户接收支付后的通知,必须为能直接访问的网址,不能跟参数
     */
    public function ali_pay($total_fee,$out_trade_no,$notify_url){
        $aop = new AopClient;
        //这里是支付宝网关，正式环境用这个即可，沙箱环境网关为	https://openapi.alipaydev.com/gateway.do
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
            //填写appid，在应用的头上面有
        $aop->appId = $this->config['ali_appid'];
        //这个地方填写私钥，就是我们在上面用工具生成的私钥，这个私钥必须是和上传到支付宝的公钥匹配，不让，支付宝访问的时候会匹配错误
        $aop->rsaPrivateKey = $this->config['rsaPrivateKey'];
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
        //这个地方的公钥也是一样，必须是上传到支付宝的那个公钥要一样
        $aop->alipayrsaPublicKey = $this->config['alipayrsaPublicKey'];
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new AlipayTradeAppPayRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent = "{\"body\":\"BKC支付订单\","
                        . "\"subject\": \"App支付测试\","
                        . "\"out_trade_no\": \"{$out_trade_no}\","
                        //. "\"timeout_express\": \"30m\","
                        . "\"total_amount\": \"{$total_fee}\","
                        . "\"product_code\":\"QUICK_MSECURITY_PAY\""
                        . "}";
        $request->setNotifyUrl($notify_url);//这个是异步回调地址
        $request->setBizContent($bizcontent);
        //调用sdkExecute生成订单url
        $response = $aop->sdkExecute($request);
        /*htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
         *如果这里需要用json返回可以去掉htmlspecialchars
         */
        //echo htmlspecialchars($response);//就是orderString 可以直接给客户端请求，无需再做处理。
		return $response;
    }
    public function ToXml($data=array())
    
    {
        if(!is_array($data) || count($data) <= 0)
        {
            return '数组异常';
        }
        
        $xml = "<xml>";
        foreach ($data as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }
    public function rand_code(){
        $str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';//62个字符
        $str = str_shuffle($str);
        $str = substr($str,0,32);
        return  $str;
    }
    private function getSign($params) {
        ksort($params);        //将参数数组按照参数名ASCII码从小到大排序
        foreach ($params as $key => $item) {
            if (!empty($item)) {         //剔除参数值为空的参数
                $newArr[] = $key.'='.$item;     // 整合新的参数数组
            }
        }
        $stringA = implode("&", $newArr);         //使用 & 符号连接参数
        $stringSignTemp = $stringA."&key=".$this->config['key'];        //拼接key
        // key是在商户平台API安全里自己设置的
        $stringSignTemp = MD5($stringSignTemp);       //将字符串进行MD5加密
        $sign = strtoupper($stringSignTemp);      //将所有字符转换为大写
        return $sign;
    }
    public function FromXml($xml)
    {
        if(!$xml){
            echo "xml数据异常！";
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }
	/**
	 * 支付宝直接转账到用户账户
	 * @param  string  $out_biz_no：商户转账唯一订单号
	 * @param  string  $payee_type:（ALIPAY_USERID：支付宝账号对应的支付宝唯一用户号，ALIPAY_LOGONID：支付宝登录号，支持邮箱和手机号格式）
	 * @param  string  $payee_account：收款方账户
	 * @param  unknown  $amount：转账金额，单位：元。
	 * @param  string  $remark: 备注信息
	 */
	public function toaccount_transfer($out_biz_no,$payee_type,$payee_account,$amount,$remark){
		$aop = new AopClient ();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$aop->appId = $this->config['ali_appid'];
		$aop->rsaPrivateKey = $this->config['rsaPrivateKey'];
		$aop->alipayrsaPublicKey=$this->config['alipayrsaPublicKey'];
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset='UTF-8';
		$aop->format='json';
		$request = new AlipayFundTransToaccountTransferRequest ();
		$request->setBizContent("{" .
		"\"out_biz_no\":\"{$out_biz_no}\"," .
		"\"payee_type\":\"{$payee_type}\"," .
		"\"payee_account\":\"{$payee_account}\"," .
		"\"amount\":\"{$amount}\"," .
		"\"payer_show_name\":\"{$remark}\"," .
		"  }");
		$result = $aop->execute ( $request); 

		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
		$resultSubMsg = $result->$responseNode->sub_msg;
		if(!empty($resultCode)&&$resultCode == 10000){
			return [
				'status'=>true,
				'msg'=>"转账成功"
			];
		} else {
			return [
				'status'=>false,
				'msg'=>"转账失败"
			];
		}
	}
}