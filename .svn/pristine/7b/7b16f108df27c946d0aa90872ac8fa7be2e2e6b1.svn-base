<?php
namespace app\index\controller;

use app\common\controller\Base;
use app\index\service\JobService;
use think\Request;

/**
 * @module 通用系统
 * @title 职务职位
 * @url /job
 * @author phill
 * Created by PhpStorm.
 * User: PHILL
 * Date: 2017/5/8
 * Time: 10:39
 */
class Job extends Base
{
    /**
     * @var \app\index\service\JobService
     */
    protected $jobService;

    protected function init()
    {
       if(is_null($this->jobService)){
           $this->jobService = new JobService();
       }
    }

    /**
     * @title 部门代码列表
     * @param Request $request
     * @return \think\response\Json
     */
    public function index(Request $request)
    {
        $type = $request->get('type',0);
        $code = $request->get('code','');
        $departmentType = $request->get('department_type','');
        $result = $this->jobService->codeList($type,$code,$departmentType);
        return json($result,200);
    }
}