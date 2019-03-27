<?php
namespace app\publish\controller;

use app\publish\service\AmazonPublishTaskService;
use think\Request;
use think\Exception;
use app\common\controller\Base;

/**
 * @module 刊登系统
 * @title Amazon每日刊登
 * @url /publish/amazon-task
 * Class AmazonPublishTask
 * @package app\publish\controller
 */
class AmazonPublishTask extends Base
{
    protected $lang = 'zh';

    public $service = null;

    public function __construct(Request $request)
    {
        parent::__construct($request);

        if (empty($this->service)) {
            //erp的语言设置，默认是中文，目前可能的值是en:英文；
            $this->lang = $request->header('Lang', 'zh');

            $this->service = new AmazonPublishTaskService();
            $this->service->setLang($this->lang);
        }
    }


    /**
     * @title 每日刊登列表；
     * @access public
     * @method GET
     * @url /publish/amazon-task
     * @apiFilter app\publish\filter\AmazonFilter
     * @apiRelate app\publish\controller\AmazonPublishTask::tags
     * @apiRelate app\index\controller\Department::departmentUserByChannelId
     * @param Request $request
     * @return \think\response\Json
     */
    public function index(Request $request)
    {
        try {
            $params = $request->get();
            $lists = $this->service->lists($params);
            return json($lists);
        } catch (Exception $e) {
            return json(['message' => $e->getMessage()]);
        }
    }


    /**
     * @title 产品标签；
     * @access public
     * @method GET
     * @url /publish/amazon-task/tags
     * @apiFilter app\publish\filter\AmazonFilter
     * @return \think\response\Json
     */
    public function tags()
    {
        try {
            $lists = $this->service->getTags();
            return json($lists);
        } catch (Exception $e) {
            return json(['message' => $e->getMessage()]);
        }
    }

}