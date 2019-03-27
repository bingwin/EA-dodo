<?php
namespace app\publish\controller;

use app\common\exception\JsonErrorException;
use think\config\driver\Json;
use think\Request;
use think\Exception;
use app\common\controller\Base;
use app\publish\service\AmazonNoticeService;
use app\common\service\Common as CommonService;
use app\publish\service\AmazonPublishHelper;
use Waimao\AmazonMws\AmazonConfig;
use Waimao\AmazonMws\AmazonSqs;
use app\common\model\amazon\AmazonAccount;
use app\common\model\amazon\AmazonNotice as AmazonNoticeModel;
use app\common\service\UniqueQueuer;
use app\publish\queue\AmazonSetNoticeQueuer;
use app\publish\service\AmazonCategoryXsdConfig;

/**
 * @module 亚马逊通知
 * @title 亚马逊通知
 * Class AmazonNotice
 * @author hao
 * @package app\publish\controller
 */
class AmazonNotice extends Base
{

    public function __construct()
    {
        parent::__construct();
    }


    /**
     * @param Request $request
     * @title 亚马逊账号通知信息
     * @access public
     * @method get
     * @url /publish/amazon-notice/notice-info
     * @param array $request
     */
    public function noticeInfo(Request $request)
    {
        try {

            $id = $request->get('id');

            if(!$id){
                throw new Exception('参数id为空');
            }

            $service = new AmazonNoticeService;

            $result = $service->noticeInfo($id);

            return json(['message' => $result]);

        } catch (JsonErrorException $e) {
            return json(['message' => $e->getMessage(), 'data' => []], 500);
        }
    }



    /**
     * @param Request $request
     * @title 亚马逊账号通知设置
     * @access public
     * @method Post
     * @url /publish/amazon-notice/set-notice
     * @param array $request
     *
     */
    public function setNotice(Request $request)
    {
        try{
            $params = $request->post();

            if(!$params){
                throw  new Exception('提交数据不能为空');
            }

            $user = CommonService::getUserInfo($request);
            $uid = $user['user_id'];

            $service = new AmazonNoticeService;

            $data = $service->noticeEdit($params, $uid);

            if($data['status']){
                return json(['message' => $data['message']]);
            }

            return json(['message' => $data['message']], 400);

        } catch (JsonErrorException $e){
            return json(['message' => $e->getMessage(), 'data' => []], 500);
        }
    }



    /**
     * @param Request $request
     * @title 亚马逊账号通知测试
     * @access public
     * @method get
     * @url /publish/amazon-notice/notice-ceshi
     * @param array $request
     *
     */
    public function noticeCeShi(Request $request)
    {
        $page = 1;
        $pageSize = 100;

        try{


            $accounts = (new \app\index\service\AccountService())->accountInfo(4);
            print_r($accounts);
            exit;
            do{
                $model = (new \app\common\model\amazon\AmazonHeelSaleLog());

                $listingModel = (new \app\common\model\amazon\AmazonListing());

                $list = $model->field('id,sku,account_id,asin')->whereNotIn('status', 2)->where(['type' => 1, 'listing_id' => 0])->page($page++, $pageSize)->select();

                if(empty($list)) {
                    return;
                }

                foreach ($list as $val) {

                    $val = $val->toArray();

                    $where = [
                        'seller_sku' => $val['sku'],
                        'account_id' => $val['account_id'],
                        'asin1' => $val['asin'],
                        'seller_type' => 2
                    ];

                    $listId = $listingModel->field('id')->where($where)->find();
                    var_dump($listId);exit;
                    if($listId) {

                        $model->update(['listing_id' => $listId['id']], ['id' => $val['id']]);
                    }

                }

            }while(count($list) == $pageSize);

            exit;
            return json(['message' => 'message'], 200);

        }catch (JsonErrorException $e){
            return json(['message' => $e->getMessage(), 'data' => []], 500);
        }

    }



    /**
     * @param Request $request
     * @title 亚马逊账号通知消息
     * @access public
     * @method post
     * @url /publish/amazon-notice/check-notice
     * @param array $request
     *
     */
    public function checkNotice(Request $request)
    {

        set_time_limit(0);


        $list = [['product_id' => 32901492235, 'custom_template_id' => 47728563]];
        foreach($list as $key=>$val) {

            $params = json_encode($val);
            $url = 'http://www.zrzsoft.com:8081/ebay-message/queue';
            $post = [
                'name' => 'app\publish\queue\AliexpressPublishSyncDetailQueue',
                'params' => $params,
                'postman' => '0',
                ];

            $post = http_build_query($post);

            $extra['header'] = ['Authorization' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOjEsImV4cCI6MTU1MTQwNTMwMiwiYXVkIjoiIiwibmJmIjoxNTUxMzE4OTAyLCJpYXQiOjE1NTEzMTg5MDIsImp0aSI6IjVjNzczZjc2NjgwYWMwLjEwMjExNzgzIiwidXNlcl9pZCI6MzMwNCwicmVhbG5hbWUiOiJcdTkwZGRcdTlmOTlcdTk4ZGUiLCJ1c2VybmFtZSI6ImhsZjQwNjMifQ.2ad329f5aa0b86a187346caa104ef1f0fbc2c05fb48593728f8c718e99265b6a'];
            $data = $this->httpReader($url, 'POST', $post, $extra);
            printf("%s\t\t%d\r\n", $data, $key);
        }

    }


    public function httpReader($url, $method = 'GET', $bodyData = [], $extra = [], &$responseHeader = null, &$code = 0, &$protocol = '', &$statusText = '')
    {
        $ci = curl_init();

        if (isset($extra['timeout'])) {
            curl_setopt($ci, CURLOPT_TIMEOUT, $extra['timeout']);
        }
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ci, CURLOPT_HEADER, true);
        curl_setopt($ci, CURLOPT_AUTOREFERER, true);
        curl_setopt($ci, CURLOPT_FOLLOWLOCATION, true);

        if (isset($extra['proxyType'])) {
            curl_setopt($ci, CURLOPT_PROXYTYPE, $extra['proxyType']);

            if (isset($extra['proxyAdd'])) {
                curl_setopt($ci, CURLOPT_PROXY, $extra['proxyAdd']);
            }

            if (isset($extra['proxyPort'])) {
                curl_setopt($ci, CURLOPT_PROXYPORT, $extra['proxyPort']);
            }

            if (isset($extra['proxyUser'])) {
                curl_setopt($ci, CURLOPT_PROXYUSERNAME, $extra['proxyUser']);
            }

            if (isset($extra['proxyPass'])) {
                curl_setopt($ci, CURLOPT_PROXYPASSWORD, $extra['proxyPass']);
            }
        }

        if (isset($extra['caFile'])) {
            curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, 2); //SSL证书认证
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, true); //严格认证
            curl_setopt($ci, CURLOPT_CAINFO, $extra['caFile']); //证书
        } else {
            curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, false);
        }

        if (isset($extra['sslCertType']) && isset($extra['sslCert'])) {
            curl_setopt($ci, CURLOPT_SSLCERTTYPE, $extra['sslCertType']);
            curl_setopt($ci, CURLOPT_SSLCERT, $extra['sslCert']);
        }

        if (isset($extra['sslKeyType']) && isset($extra['sslKey'])) {
            curl_setopt($ci, CURLOPT_SSLKEYTYPE, $extra['sslKeyType']);
            curl_setopt($ci, CURLOPT_SSLKEY, $extra['sslKey']);
        }

        $method = strtoupper($method);
        switch ($method) {
            case 'GET':
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'GET');
                if (!empty($bodyData)) {
                    if (is_array($bodyData)) {
                        $url .= (stristr($url, '?') === false ? '?' : '&') . http_build_query($bodyData);
                    } else {
                        curl_setopt($ci, CURLOPT_POSTFIELDS, $bodyData);
                    }
                }
                break;
            case 'POST':
                curl_setopt($ci, CURLOPT_POST, true);
                if (!empty ($bodyData)) {
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $bodyData);
                }
                break;
            case 'PUT':
                //                 curl_setopt ( $ci, CURLOPT_PUT, true );
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty ($bodyData)) {
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $bodyData);
                }
                break;
            case 'DELETE':
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'HEAD':
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'HEAD');
                break;
            default:
                throw new \Exception(json_encode(['error' => '未定义的HTTP方式']));
                return ['error' => '未定义的HTTP方式'];
        }

        if (!isset($extra['header']) || !isset($extra['header']['Host'])) {
            $urldata = parse_url($url);
            $extra['header']['Host'] = $urldata['host'];
            unset($urldata);
        }

        $header_array = array();
        foreach ($extra['header'] as $k => $v) {
            $header_array[] = $k . ': ' . $v;
        }

        curl_setopt($ci, CURLOPT_HTTPHEADER, $header_array);
        curl_setopt($ci, CURLINFO_HEADER_OUT, true);

        curl_setopt($ci, CURLOPT_URL, $url);

        $response = curl_exec($ci);

        if (false === $response) {
            $http_info = curl_getinfo($ci);
            //throw new \Exception(json_encode(['error' => curl_error($ci), 'debugInfo' => $http_info]));
            return json_encode(['error' => curl_error($ci), 'debugInfo' => $http_info]);
        }

        $responseHeader = [];
        $headerSize = curl_getinfo($ci, CURLINFO_HEADER_SIZE);
        $headerData = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $responseHeaderList = explode("\r\n", $headerData);

        if (!empty($responseHeaderList)) {
            foreach ($responseHeaderList as $v) {
                if (false !== strpos($v, ':')) {
                    list($key, $value) = explode(':', $v, 2);
                    $responseHeader[$key] = ltrim($value);
                } else if (preg_match('/(.+?)\s(\d+)\s(.*)/', $v, $matches) > 0) {
                    $protocol = $matches[1];
                    $code = $matches[2];
                    $statusText = $matches[3];
                }
            }
        }

        curl_close($ci);
        return $body;
    }
}