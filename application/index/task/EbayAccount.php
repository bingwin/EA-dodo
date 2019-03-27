<?php
namespace app\index\task;

use app\index\service\AbsTasker;
use app\common\cache\Cache;
use service\ebay\EbayAccountApi;
use app\common\model\ebay\EbayAccount as EbayAccountModel;
use think\Exception;
use app\common\exception\TaskException;



class EbayAccount extends AbsTasker{
    public function getName()
    {
        return "Ebay 账号/店铺评分";
    }

    public function getDesc()
    {
        return "Ebay 账号/店铺评分";
    }

    public function getCreator()
    {
        return "TanBin";
    }

    public function getParamRule()
    {
        return [];
    }

    public function execute()
    {
         
        $accountList    = Cache::store('EbayAccount')->getTableRecord();
        foreach ($accountList as $k => $v) {
            $token = $v['token'];
            if (!empty($token) && $v['is_invalid'] ==1) {
                $data              = [
                    'userToken'      => $token,
                    'account_id'     => $v['id'],
                    'account_name'     => $v['account_name']
                ] ;
                
                $res = $this->downAccountInfo($data);
                sleep(10);
                
            }
        }
        return false;
    }
    
    /**
     * 下载账号信息
     * @param array $data
     * @throws TaskException
     */
    function downAccountInfo($data = []){
        try {
            $ebay = new EbayAccountApi($data);
            $result = $ebay->getUser($data['account_name']);
            if($result){
                $update = [
                    'email' => param($result, 'Email'),
                    'feedback_score' => param($result, 'FeedbackScore' ,0),
                    'positive_feedback_percent' => param($result, 'PositiveFeedbackPercent' ,0),
                    'feedback_rating_star' => param($result, 'FeedbackRatingStar'),
                    'register_time' => strtotime(param($result, 'RegistrationDate',0)),
                ];
                $res = EbayAccountModel::update($update,['id'=>$data['account_id']]);     
            }
        } catch (Exception $ex) {
            throw new TaskException($ex->getMessage());
        }
    }
    
}