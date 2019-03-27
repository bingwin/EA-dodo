<?php
/**
 * Created by PhpStorm.
 * User: zhangdongdong
 * Date: 2018/6/9
 * Time: 16:01
 */

namespace app\publish\service;


use app\common\cache\Cache;
use app\common\model\amazon\AmazonAccount;
use app\common\model\amazon\AmazonGoodsTag;
use app\common\model\amazon\AmazonPublishProduct;
use app\common\model\amazon\AmazonPublishTask as AmazonPublishTaskModel;
use app\common\model\ChannelProportion;
use app\common\model\ChannelUserAccountMap;
use app\common\model\GoodsLang;
use app\common\model\User;
use app\common\service\ChannelAccountConst;
use app\common\service\Common;
use app\goods\service\CategoryHelp;
use app\goods\service\GoodsHelp;
use app\index\service\ChannelService;
use app\index\service\Department;
use app\common\model\Department as DepartmentModel;
use app\index\service\DepartmentUserMapService;
use think\Exception;
use \app\common\traits\User as UserTraits;

class AmazonPublishTaskService
{

    use UserTraits;

    protected $lang = 'zh';

    protected $model = null;

    protected $tagModel = null;

    protected $accountModel = null;

    public function __construct()
    {
        $this->model = new AmazonPublishTaskModel();
        $this->tagModel = new AmazonGoodsTag();
        $this->accountModel = new AmazonAccount();
    }

    /**
     * 设置刊登语言
     * @param $lang
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
    }


    /**
     * 获取刊登语言
     * @return string
     */
    public function getLang()
    {
        return $this->lang ?? 'zh';
    }


    public function lists($params)
    {
        $page = $params['page'] ?? 1;
        $pageSize = $params['pageSize'] ?? 20;
        $where = $this->condition($params, $join);
        $count = $this->model->alias('t')->join($join)->where($where)->field('id')->count();
        $lists = $this->model->alias('t')
            ->join($join)
            ->where($where)
            ->field('g.spu,g.thumb image_url,g.category_id,g.name zn_name,g.pre_sale,t.id,t.goods_id,t.account_id,t.product_id,t.seller_id,t.type,t.profit,t.task_time,t.status,t.create_time')
            ->order('g.pre_sale desc,t.id desc')
            ->page($page, $pageSize)
            ->select();

        //图片的base_url;
        $baseUrl = Cache::store('configParams')->getConfig('innerPicUrl')['value'] . DS;
        $returnData = [
            'count' => $count,
            'page' => $page,
            'pageSize' => $pageSize,
            'data' => []
        ];

        $newList = [];
        $account_ids = [0];
        $seller_ids = [0];
        $product_ids = [0];
        $goodsIds = [0];
        foreach ($lists as $val) {
            $tmp = $val->toArray();
            $account_ids[] = $tmp['account_id'];
            $seller_ids[] = $val['seller_id'];
            $product_ids[] = $val['product_id'];
            $goodsIds[] = $val['goods_id'];
            $tmp['base_url'] = $baseUrl;
            $newList[] = $tmp;
        }
        //$publishStatusArr = ['待上传', '上传中', '已上传', '上传失败'];
        //$statusArr = ['未开始', '进行中', '已完成'];
        $accounts = $this->accountModel->where(['id' => ['in', $account_ids]])->column('code', 'id');
        $users = User::where(['id' => ['in', $seller_ids]])->column('realname', 'id');

        $publishs = AmazonPublishProduct::where(['id' => ['in', $product_ids]])->column('publish_status', 'id');
        $langs = GoodsLang::where(['goods_id' => ['in', $goodsIds], 'lang_id' => 2])->column('title', 'goods_id');

        $tags = $this->getTagsByGoodsIds($goodsIds);

        $toUpdateIds = [];
        $today = strtotime(date('Y-m-d'));
        $help = new CategoryHelp();
        foreach ($newList as &$val) {
            $val['en_name'] = $langs[$val['goods_id']] ?? '';
            $val['code'] = $accounts[$val['account_id']] ?? '-';
            $val['seller_name'] = $users[$val['seller_id']] ?? '-';

            if ($val['task_time'] < $today && $val['status'] == 0) {
                $val['status'] = 3;
                $val['status_text'] = '已延期';
            }

            if ($val['type'] == 1) {
                $val['tag'] = $tags[$val['goods_id']] ?? '-';
            } else {
                $val['tag'] = '外部随机分配';
            }

            $val['profit'] = $val['profit']. '%';
            //刊登状态；
            $val['publish_status'] = '';
            if (isset($publishs[$val['product_id']])) {
                $val['publish_status'] = $publishs[$val['product_id']];
                if ($val['publish_status'] == 2 && $val['status'] != 2) {
                    $val['publish_status'] = 1;
                    $toUpdateIds[] = $val['id'];
                }
            }

            $val['category_name'] = $help->getCategoryNameById($val['category_id'], ($this->lang == 'zh' ? 1 : 2));
        }
        unset($val);
        $returnData['data'] = $newList;
        if (!empty($toUpdateIds)) {
            $this->model->update(['status' => 2], ['id' => ['in', $toUpdateIds]]);
        }
        return $returnData;
    }


    public function getTagsByGoodsIds(array $goodsIds) : array
    {
        if (empty($goodsIds)) {
            return [];
        }
        $tags = AmazonGoodsTag::alias('gt')
            ->join(['department' => 'd'], 'd.id=gt.tag_id')
            ->where(['goods_id' => ['in', $goodsIds]])
            ->column('d.name', 'gt.goods_id');
        return $tags;
    }


    public function condition($params, &$join)
    {
        $where = [];

        $join[] = ['goods g', 'g.id=t.goods_id'];
        if (!empty($params['account_id'])) {
            $where['t.account_id'] = $params['account_id'];
        }
        if (isset($params['status']) && in_array($params['status'], ['0', '1', '2', '3'])) {
            switch ($params['status']) {
                case 0:
                    $where['t.status'] = 0;
                    $where['t.task_time'] = ['=', strtotime(date('Y-m-d'))];
                    break;
                case 1:
                    $where['t.status'] = 1;
                    break;
                case 2:
                    $where['t.status'] = 2;
                    break;
                case 3:
                    $where['t.status'] = 0;
                    $where['t.task_time'] = ['<', strtotime(date('Y-m-d'))];
                    break;
                default:
                    $where['t.id'] = 0;
            }
        }
        if (!empty($params['department_id'])) {
            //如果有这个数据，那么它就是tag_id，直接连表搜tag_id就好了；
            if (ChannelProportion::where(['channel_id' => 2, 'department_id' => $params['department_id']])->count()) {
                $params['tag_id'] = $params['department_id'];
            } else {
                $sellerIds = $this->getUserByDepartmentId($params['department_id']);
                if ($sellerIds) {
                    $where['t.seller_id'] = ['in', $sellerIds];
                } else {
                    $where['t.id'] = 0;
                }
            }
        }
        if (isset($params['tag_id']) && $params['tag_id'] != '') {
            if ($params['tag_id'] == '0') {
                $where['t.type'] = 2;
            } else {
                $join[] = ['amazon_goods_tag gt', 'gt.goods_id=t.goods_id'];
                $where['gt.tag_id'] = $params['tag_id'];
                $where['t.type'] = 1;
            }
        }

        $startTime = empty($params['start_time'])? 0 : strtotime($params['start_time']);
        $endTime = empty($params['end_time'])? 0 : (strtotime($params['end_time']) + 86399);
        if ($startTime == 0 && $endTime > 0) {
            $where['t.task_time'] = ['<', $endTime];
        }
        if ($startTime > 0 && $endTime == 0) {
            $where['t.task_time'] = ['>=', $startTime];
        }
        if ($startTime > 0 && $endTime > 0) {
            $where['t.task_time'] = ['between', [$startTime, $endTime]];
        }
        if (!empty($params['seller_id'])) {
            $where['t.seller_id'] = $params['seller_id'];
        }

        return $where;
    }


    public function getTags()
    {
        $departmentServ = new Department();
        $data = $departmentServ->getDepartmentByChannelId(ChannelAccountConst::channel_amazon);
        $list = [];
        foreach ($data as $val) {
            $list[] = [
                'value' => $val['id'],
                'label' => $val['name']
            ];
        }
        return $list;
    }


    /**
     * 根据商品ID和帐号ID拿取任务
     * @param $goodsId
     * @param $accountId
     * @param string $field
     * @return array
     */
    public function taskDetail($goodsId, $accountId, $field = '*') : array
    {
        $detail = $this->model->where(['goods_id' => $goodsId, 'account_id' => $accountId])->field($field)->find();
        if (empty($detail)) {
            return [];
        }
        return $detail->toArray();
    }


    /* --------------------------------- 以下分配任务 --------------------------------- */
    /**
     * 主执行方法；
     * @return bool
     */
    public function assign($day, $return = false)
    {
        //商品总数；
        $goodsHelp = new GoodsHelp();
        $goods = $goodsHelp->getAssignGoods($day);
        //$goods = $this->getLineData([
        //        'name' => 'app\goods\service\GoodsHelp',
        //        'method' => 'getAssignGoods',
        //        'result' => '2',
        //        'p1' => $day
        //]);

        if (empty($goods)) {
            return false;
        }

        //商品ID
        $goodsIds = array_keys($goods);
        $goodsTotal = count($goodsIds);

        //部门；
        $departmentServ = new ChannelService();
        $departments = $departmentServ->getProportionByChannelId(ChannelAccountConst::channel_amazon);
        //$departments = $this->getLineData([
        //        'name' => 'app\index\service\ChannelService',
        //        'method' => 'getProportionByChannelId',
        //        'result' => '2',
        //        'p1' => ChannelAccountConst::channel_amazon
        //]);
        if (empty($departments)) {
            return false;
        }

        $departments = array_combine(array_column($departments, 'department_id'), $departments);
        $departmentCount = count($departments);

        $num = 0;
        $start = 0;
        $remainder = 0;
        $departmentGoods = [];
        foreach ($departments as &$val) {
            $num++;
            if ($num < $departmentCount) {

                $len = $goodsTotal * $val['product_proportion'] / 100;
                $round = round($len);
                ////补偿值
                $remainder = $remainder + $len - $round;
                if ($remainder >= 1) {
                    $round = $round + 1;
                    $remainder = $remainder - 1;
                } else if ($remainder <= -1 && $round >= 1) {
                    $round = $round - 1;
                    $remainder = $remainder + 1;
                }

                if ($round == 0 || $start >= $goodsTotal) {
                    $tmpGoods = [];
                } else {
                    $tmpGoods = array_slice($goodsIds, $start, $round);
                }
                $start += $round;
            } else {
                if ($start > $goodsTotal) {
                    $tmpGoods = [];
                } else {
                    $tmpGoods = array_slice($goodsIds, $start);
                }
            }

            $departmentGoods[$val['department_id']] = $tmpGoods;
        }

        //给每组分配的商品；
        $departmentGroupGoods = [];
        //抽取商品
        $departmentRondomGoods = [];

        $dumServ = new DepartmentUserMapService();
        foreach ($departmentGoods as $department_id=>$department_goods_ids) {
            //每个部门底下的分组；
            $departmentGroups = $dumServ->getPublishUserByDepartmentId($department_id);
            //$departmentGroups = $this->getLineData([
            //    'name' => 'app\index\service\DepartmentUserMapService',
            //    'method' => 'getPublishUserByDepartmentId',
            //    'result' => '2',
            //    'p1' => $department_id
            //    'p2' => 2
            //]);
            //当前部门下的分组ID对应的组员；
            $departmentGroupUsers = $this->getDepartmentGroupUsers($departmentGroups);
            $departments[$department_id]['groups'] = $departmentGroupUsers;

            //每个分组ID对应的商品ID；
            $groupGoods = $this->groupAssignGoods($departmentGroupUsers, $department_goods_ids);
            $departmentGroupGoods[$department_id] = $groupGoods;

            //商品group
            $rondom_number = $departments[$department_id]['product_count'];
            if ($rondom_number > 0) {
                $userRondomGoods = $this->userRondomGoods($departmentGroupUsers, $departmentGoods, $department_id, $rondom_number);
                foreach ($userRondomGoods as $uid=>$rondomGood) {
                    $departmentRondomGoods[$uid] = $rondomGood;
                }
            }
        }

        //去除帐号
        //silverit,zayfr,silverfr,peres,zayde,silverde,zayuk,sufuit,silveruk,hualiit,sufufr,hualies,sufues,hualifr,sufude,hualide,sufuuk,diande,hualiuk,zayes,silveres,pueries,zayit,whitejp,ruijp,wenjp,luosunjp,yannjp,mingjp,dengjp,lurdajp,chenjp,fortemjp,jiangjp,yirenjp,smbes,luermeit,luermees,bius,kangit,pueriuk,bimx,qianges,ratees,capde,huanit,capuk,capfr,capit,capes,ratees,lanheus,youus,fastarde,fastarit,oumanuk,fastarfr,chenca,netmx,fastaruk,chenus,fastares,vovica,chenfr,vovius,anca,chenit,anus,chenes,anit,chende,aneilmx,chenuk,jundamx,lanheca,hjunsca,youca,anuk,aneilin,nutfr,yaooes,seeus,nutit,xifr,nutuk,catchit,xies,catches,xiit,liqunit,xide,catchuk,liqunes,xiuk,shakeus,brainca,brainus,yaooit,yaoofr,evenuk ,affairuk,affairde,affairfr,affairit,affaires,jiangus,jiangca,jianguk,jiangde,jiangfr,jiangit,jianges,fues,xizhiit,chues,chuliait,whitede,whiteus,whitemx,whiteca,whitefr,whitees,whiteit,whiteuk,juanus,juanca,qiuuk,pandde,lawuk,erectit,lionde,organuk,pandfr,oshideuk,erectes,lionuk,appleit,panduk,erectde,himit,applees,erectfr,himes,applede,bklies,cindyus,colourau,erectuk,himde,applefr,himfr,appleuk,bklifr,colourca,himuk,lawit,organit,colourde,lawes,lionit,organes,pandit,colouruk,lawfr,liones,organde,pandes,colourfr,lawde,lionfr,organfr,gressuk,gresses,gressit,gressde,gressfr,moveuk,movede,movefr,moveit,movees,luosunus,luosunca,zhangde,zhanges,embeuk,empeit,dekade,dekauk,cosyit,fuus,gloryuk,gloryde,zhuouk,zhuode,zhuofr,zhuoes,zhuoit,xizhijp,holees,cargouk,neverca,awus,holeit,neverus,holefr,zslin,holede,yuntau,holeuk,zhoau,szllus,yuntde,cargoes,cargoit,jialies,lanhefr,momode,wavees,wuhude,jialifr,momouk,waveit,wuhues,bingca,jialiit,wavede,wuhuit,maies,bingus,codees,jialide,wavefr,wuhuuk,maiit,huaca,zslus,jialiuk,waveuk,wuhufr,maide,huaus,maifr,lanheuk,maiuk,lanheit,oumanes,momoes,lanhees,oumanit,momoit,zyuronguk,lanhede,oumanfr,momofr,newrfuk,xiongUS,shengshibca,kardufr,newrfjp,jianjp,xiongUK,newrfit,lanhejp,riveruk,minius,newrffr,riverde,hairede,kardude,xiongIT,newrfes,nuofr,karduuk,xiongES,minica,nuode,townuk,minifr,riverit,townit,riveres,cherudaus,townes,shengshiit,maybeit,zyzzuk,gangjp,knowfr,zyzzit,qiaojp,jiaouk,knowuk,maybede,qiaouk,elmit,nuouk,jiaofr,zyzzes,elmfr,bankus,qiaofr,elmes,qiaoes,qiaous,heheuk,haireuk,plusuk,zyzzde,heheit,knowes,bankuk,elmde,hercheus,plusus,pluses,zyzzfr,zengjp,jiande,actca,clawde,riseus,acreit,neckes,tureit,jianca,baomx,turees,turede,acrefr,jianit,jianus,tureuk,acrede,neckde,repues,acrees,repuit,repufr,baous,chpinit,chjiafr,turefr,baoca,clawit,chjiade,acreuk,boude,purpde,chjiauk,riseca,gymde,gymuk,zhoca,teamde,teamfr,teames,voidit,joinca,voidfr,gymes,voides,easyca,gymit,voidde,monaca,gymfr,voiduk,portit,diewuca,portfr,framede,babyuk,pangit,portes,zekaiuk,pleade,portuk,framefr,portus,jiaus,pleaes,portde,withit,deskus,keyuk,zekaifr,mobeide,withes,lenuk,pleafr,mobeifr,qiujp,blackus,lende,zhaies,withuk,framees,rouuk,rongus,zhaiit,roues,frameit,portca,zekaimx,repude,repuuk,mires,miruk,mobeies,mobeiit,frameuk,orderit,doorus,teraies,orderfr,orderde,teraiit,duanes,ancyes,teraifr,orderes,ancyit,teraide,orderuk,teraiuk,lenes,tigeres,lenit,tigerit,lenfr,tigerfr,blueus,tigerde,tigeruk,maidde,oceanuk,catses,booes,maiduk,oceanit,beefr,booit,boofr,oceanes,lurdaes,beede,jisofr,boouk,icejp,minees,oceanfr,beeuk,jisoes,fanmx,lianguk,oceande,jisouk,liangit,qiumx,jisoit,lianges,glowit,diewuit,catsuk,glowde,maides,mineit,glowfr,minede,huiyingshede,fanus,maidit,glowes,lurdafr,mineuk,maidfr,beees,catsit,fanca,glowuk,lurdade,jisode,ruius,cande,tehaode,kuaiit,ruifr,joyus,jumnes,tehaouk,kuaies,fineus,canit,ruiit,carrieus,dailyus,purpes,ruies,canca,lishjp,yellowes,kuaide,yellowit,kimit,iceus,wonderus,faxes,goodes,nailes,yellowfr,whetherus,faxit,goodit,nailit,yellowde,twinus,grayit,faxuk,goodfr,dogde,nailfr,lishit,yellowuk,tehaoit,grayes,faxfr,goodde,movieit,dogit,doguk,nailde,lishde,lemonus,successus,tehaoes,faxde,gooduk,moviefr,dogfr,nailuk,lishus,dogus,tehaofr,grayfr,ruiuk,doges,ruide,tangus,broadfr,applyuk,yeses,todayus,lurdaus,clothit,boatus,demaes,elevenus,buildfr,personjp,julyes,waspes,specialus,keleiuk,applyde,yesit,roseus,clothuk,demade,buildde,julyuk,waspit,moonfr,ablefr,yesuk,helles,builduk,qidileus,julyit,waspfr,bularyin,broadde,hellit,julyfr,waspde,moonit,nasies,hellfr,axes,chpinjp,julyde,tulipuk,waspuk,keleius,moones,nasiit,wuleijp,yesjp,ableus,hellde,chairus,axit,wones,silende,sisterde,tulipit,yachtfr,wonit,millfr,nasifr,personfr,helluk,wheelus,axfr,spinit,silenit,sisterit,tulipfr,yachtes,wonfr,milles,naside,fitus,demauk,sisterus,axuk,spinde,ableca,silenuk,sisterfr,tulipes,yachtit,applyes,wonde,clothde,millit,nasiuk,moonuk,ablejp,wonuk,silenes,silenus,sisteruk,yachtde,applyfr,yesfr,beatus,clothes,milluk,ablein,demait,ablede,buildes,spinuk,silenfr,sisteres,yachtuk,applyit,yesde,suitus,clothfr,huiyingsheus,millde,seatus,demafr,buildit,honguk,alamde,alames,queenca,hongit,queenus,honges,hongde,alamfr,hongfr,alamuk,alamit,chaoes,racyit,chaofr,racyes,chaode,racyfr,chaouk,racyde,racyuk,upmx,upca,upus,chaoit,guanges,guangfr,guangde,guanguk,guangit,admissuk,admissit,admisses,admissfr,admissde,alleyfr,alleyit,alleyde,alleyuk,alleymx,alleyca,alleyes,alleyus,bushmx,dumx,malikemx,ladeyimx,dongmx,alleymx,jumpmx,bearmx,kayumx,shantanmx,hugies,kengit,kindlyit,klenit,kindlyes,famedes,cadwfr,clarkit,ramonde,rendde,caroles,facees,cadwuk,klenfr,famedit,gesteruk,waterit,xaifr,xaies,xaiit,tracyes,tracyit,tracyfr,tracyde,tracyuk,clearfr,lingfr,xizhouuk,huayeus,huayede,xizhouit,huayees,huayefr,lingit,xizhouus,lingde,xizhoufr,linguk,huayeit,xizhoues,huayeuk,cies,xizhoude,clearit,xizhoumx,cifr,huayejp,huayejp,cide,ciit,ciuk,venlfr,lotuses,betlyde,shyca,kateit,betlyit,venlde,lotusuk,venluk,mitede,katede,talluk,dorisde,venles,lotusfr,shyus,katefr,betlyes,venlit,lotusit,kateuk,betlyuk,lotusde,katees,betlyfr,viait,weaveuk,viaes,weaveit,shuangca,pinkuk,weavefr,selyuk,weavees,selyit,weavede,yingjp,selyfr,viade,selyde,mteyica,selyes,mteyius,superjp,lucyuk,lucyfr,lucyit,lucyes,lucyde,deres,derfr,derit,yolkes,everyus,yolkit,derde,yolkfr,yolkuk,yolkde,deruk,venlfr,lotuses,betlyde,ethices,shyca,kateit,betlyit,ethicfr,venlde,lotusuk,ethicde,venluk,katede,talluk,ethicuk,venles,lotusfr,yutaoit,shyus,katefr,betlyes,tallit,venlit,lotusit,talles,kateuk,betlyuk,lotusde,tallfr,katees,betlyfr,tallde,ethicit,shyca,kateit,betlyit,venlde,lotusuk,katede,jiufr,venluk,talluk,venles,lotusfr,shyus,katefr,betlyes,venlit,lotusit,kateuk,betlyuk,lotusde,katees,betlyfr,venlfr,lotuses,betlyde,katede,venluk,talluk,venles,lotusfr,shyus,katefr,betlyes,venlit,lotusit,kateuk,betlyuk,lotusde,katees,betlyfr,venlfr,lotuses,betlyde,shyca,kateit,betlyit,venlde,lotusuk,ventes,cheesede,ventde,ragones,bestde,ventfr,kalees,ventuk,yaes,yauk,yait,yafr,cheeseit,ventit,kaleit,proit,jinau,superus,kalede,superca,pinkes,pinkde,pinkit,caica,pinkfr,caius,yade,falles,cheeseuk,circlefr,xichenfr,maxde,xichenes,cleverit,watchca,maxfr,taiit,xichende,maxes,bellus,oliveit,sinceuk,tomojp,maxit,wanyit,sinceit,maxes,maxuk,wanyde,olivede,xichenuk,xichenit,hulaes,solifr,doughit,chulaiau,hainca,wanyes,singit,wanyuk,singuk,daliit,singde,doughuk,kabufr,demaus,dalifr,kabuuk,cuijp,yixines,shuifr,shuiit,solies,mteyiuk,haines,shuies,superit,soliit,shuiuk,soliuk,popes,shuide,hainit,hulait,duanjp,solide,doughes,honeyca,tiedsuk,honeyus,fangca,huises,tiedsde,fangus,ansiit,gaiyaes,watchus,ansifr,anside,gaiyauk,satireit,ansiuk,honeyit,satireuk,satirefr,pueriau,satirees,honeyuk,satirede,tiedsit,yibaouk,lesses,lessde,lessfr,shzonsein,lessuk,lessit,roundde,quiteit,tomouk,tomode,silyfr,posica,fengfr,guojp,kalefr,zhongca,zhongus,kaleuk,clubfr,posius,bulinguk,clinguk,kinsde,maotit,kinsit,clingit,clingfr,kinsuk,clinges,clingde,luoca,silunuk,bestit,menit,silunde,tomoit,bestuk,silunes,silunfr,foxca,limites,silunit,limitit,tomoes,bestes,limitfr,oysteruk,limitde,oysterit,bestfr,clubes,xiuit,xiuuk,xiues,lanit,lande,marvefr,farmca,alsous,siluoca,marvede,luckit,luckes,siluous,alsoca,xiude,marveuk,luckuk,handfr,shuoes,jingca,poosjp,alsojp,heavenca,loyalde,heavenus,chenau,shuode,handde,luckfr,luckde,baozica,feeljp,bigde,shaofr,zhuit,superau,yixinde,hulifr,baozies,viauk,driverde,popde,zhufr,baozius,zhuuk,shuaies,popit,muqiuuk,banees,popuk,callit,baneit,muqiuit,banede,hulies,yingca,yixinit,baneuk,hulide,yingus,happyit,yixinuk,shuaius,garfees,happyde,huliit,xianges,yixinfr,superuk,huliuk,shuaica,wanyfr,viafr,popfr,yingmx,biguk,lilyes,muqiumx,mieuk,lilyfr,muqiuus,lilyde,biuit,xiangit,lilyuk,biude,miede,cassit,xiangfr,casses,muqiuca,cassde,cassfr,cassuk,mieit,herouk,miees,xianguk,lilyit,miefr,surveyuk,pouruk,leapuk,idealit,surveyit,coilit,huiffr,taifr,bellit,huifde,surveyfr,sunjp,surveyes,shotuk,huifuk,taies,pletejp,surveyde,shotes,coiluk,qinca,huifes,snakees,huifit,shotit,taoca,stillca,snakeit,maus,shotfr,leapus,taomx,lanuk,xuanit,snakede,pourde,shotde,lanes,snakefr,pourfr,snakeuk,pourit,leapca,taijp,poures,leapit,jiulingjp,leadca,lutongdait,renit,sketit,lutongdaes,sketes,lutongdafr,sketfr,lutongdade,sketde,maizees,lutongdauk,sketuk,maizeit,maizefr,maizede,leapes,maizeuk,xizhiau,武汉,武汉,hates,mgfr,hatde,vrainde,hatit,hatfr,mgit,mges,vrainit,mges,hatuk,biges,xaiofr,xaioes,motous,xaiode,bigca,gaiyait,boyca,xaiouk,nessfr,honeymx,winterus,xaioit,mengus,nessde,motoca,draines,quiteca,motoes,quiteus,deales,forbuk,forbit,forbfr,forbde,forbes,tomofr,wetit,shiyiuk,handuk,noliait,willowes,noliaes,biteca,keepuk,luous,shuofr,collares,tingau,shuoit,collarde,collarfr,togeit,collarit,togeuk,togeca,collaruk,togefr,wholeca,shuouk,keepfr,wholeus,keepde,luode,xiufr,keepit,shaoit,shaoes,shaode,shaouk,hourfr,hourde,missde,kevtde,missus,exceles,kevtit,missca,excelfr,diuuk,excelde,exceluk,diufr,keepes,misses,excelit,missuk,rapes,cosefr,babyfr,yufr,rapde,coseuk,rapuk,togees,kinsus,biufr,biuuk,strogyes,togede,strogyit,gradefr,strogyfr,gradeuk,strogyuk,gradeit,cosees,pingde,strogyde,gradees,rapit,coseit,biues,rapfr,yuit,motefr,pinguk,yues,yuca,yude,yuus,banlaes,jiangau,banlade,foxjp,banlauk,pingca,banlafr,pinges,banlait,pingfr,pingit,pingus,postde,luojp,ansijp,xuede,cathyit,cathyes,cathyfr,postes,cathyde,postfr,cathyuk,postit,weijp,postuk,tainuk,twistes,twistde,callde,武汉,calles,jiuuk,meica,leaderit,herryuk,whaleuk,calluk,meius,武汉,whalede,whaleit,whalees,武汉,sharpuk,taines,whalefr,leaderde,sharpit,tainit,twistuk,武汉,moonus,leaderuk,rabbites,sharpfr,tainfr,twistit,zezede,武汉,rabbitde,sharpde,tainde,twistfr,meiau,武汉,rabbituk,sharpes,dragonit,callfr,mapleuk,dragonde,武汉,dragonfr,dragonuk,武汉,wingit,zezees,leaderes,武汉,leaderfr,winguk,武汉,wingfr,wingde,zezeuk,dragones,winges,shejp,guifr,peaceus,huanges,maca,funcca,peaceus,classuk,classca,winca,sundin,pleteca,guiuk,oxiuk,shanit,pleteus,shanit,yutaofr,peaceca,classes,yutaouk,guies,guide,pushca,guiit,bangde,bangfr,bangfr,bangfr,bangfr,bangfr,bangit,bangfr,laide,bangfr,yutaoes,kalaus,yutaode,banges,banguk,banguk,peacemx,ovalde,abraes,inertit,inertes,ovalfr,inertes,abrait,abrait,ovales,inertfr,inertde,peacemx,ovalit,inertde,inertfr,ovaluk,inertuk,inertuk,inertit,abrafr,abrauk,abrade,shejp
        $this->getRemoveAccountIds();
        $result = $this->saveTasks($departmentGroupGoods, $departmentRondomGoods, $departments, $goods, $return);
        return $result;
    }


    /**
     * 返回每个分组ID对应的用户；
     * @param $departmentGroups
     * @return array
     */
    public function getDepartmentGroupUsers($departmentGroups)
    {
        if (empty($departmentGroups['child'])) {
            return [];
        }
        $groupUsers = [];
        foreach ($departmentGroups['child'] as $val) {
            if ($val['type'] == 1) {
                $groupUsers[$val['id']] = $val['users'];
            } else {
                if (!empty($val['child'])) {
                    $subGroupUsers = $this->getDepartmentGroupUsers($val);
                    if (!empty($subGroupUsers)) {
                        foreach ($subGroupUsers as $k=>$v) {
                            $groupUsers[$k] = $v;
                        }
                    }
                }
            }
        }
        return $groupUsers;
    }


    /**
     * 给小组分配商品
     * @param $departmentGroups
     * @param $goodsIds
     * @return array
     */
    public function groupAssignGoods($departmentGroups, $goodsIds)
    {
        //如果下面分组为空，则没必要计算了；直接返回空；
        $goodsTotal = count($goodsIds);
        if (!$departmentGroups) {
            return [];
        }

        $groupGoods = [];
        $total = 0;
        $newDepartmentGroups = [];
        foreach ($departmentGroups as $groupId=>$val) {
            $groupGoods[$groupId] = [];
            $tmpTotal = count($val);
            if ($tmpTotal > 0) {
                $total += $tmpTotal;
                $newDepartmentGroups[$groupId] = $val;
            }
        }

        $num = 0;
        $start = 0;
        $remainder = 0;
        $groupTotal = count($newDepartmentGroups);
        foreach ($newDepartmentGroups as $groupId=>$val) {
            $num++;
            if ($num < $groupTotal) {
                $sellerTotal = count($val);

                //实际数量；
                $len = $goodsTotal * count($val) / $total;
                $round = round($len);
                //补偿值
                $remainder = $remainder + $len - $round;
                if ($remainder >= 1) {
                    $round = $round + 1;
                    $remainder = $remainder - 1;
                } else if ($remainder <= -1 && $round >= 1) {
                    $round = $round - 1;
                    $remainder = $remainder + 1;
                }

                $tmpGoods = [];
                if ($sellerTotal > 0) {
                    $tmpGoods = array_slice($goodsIds, $start, $round);
                }
                $start = $start + $round;
            } else {
                $tmpGoods = array_slice($goodsIds, $start);
            }
            $groupGoods[$groupId] = $tmpGoods;
        }
        return $groupGoods;
    }


    /**
     * 返回用户ID为键值为随机商品ID；
     * @param $departmentGroupUsers
     * @param $goodsIds
     * @param $department_goods_ids
     * @param int $rondom_number
     * @return array
     */
    public function userRondomGoods($departmentGroupUsers, $departmentGoods, $department_id, $rondom_number = 3)
    {
        if (empty($departmentGroupUsers)) {
            return [];
        }
        //找出部门所有销售；
        $users = [];
        foreach ($departmentGroupUsers as $val) {
            $users = array_merge($users, $val);
        }
        $users = array_filter(array_unique($users));

        //找出非本部门的商用品
        $diffGoods = [];
        $goodsTags = [];
        foreach ($departmentGoods as $key=>$val) {
            if ($department_id != $key) {
                $diffGoods = array_merge($diffGoods, $val);
                foreach ($val as $goodsId) {
                    $goodsTags[$goodsId] = $key;
                }
            }
        }

        $rondoms = [];
        if (!empty($diffGoods)) {
            foreach ($users as $uid) {
                //随机KEY；
                $rondom_keys = (array)array_rand($diffGoods, $rondom_number);
                foreach ($rondom_keys as $key) {
                    $tmp = $diffGoods[$key];
                    $rondoms[$uid][] = [
                        'goods_id' => $tmp,
                        'tag_id' => $goodsTags[$tmp]
                    ];
                }
            }
        }
        return $rondoms;
    }


    public $removeAccountIds = [];

    public function getRemoveAccountIds()
    {
        $codes = 'silverit,zayfr,silverfr,peres,zayde,silverde,zayuk,sufuit,silveruk,hualiit,sufufr,hualies,sufues,hualifr,sufude,hualide,sufuuk,diande,hualiuk,zayes,silveres,pueries,zayit,whitejp,ruijp,wenjp,luosunjp,yannjp,mingjp,dengjp,lurdajp,chenjp,fortemjp,jiangjp,yirenjp,smbes,luermeit,luermees,bius,kangit,pueriuk,bimx,qianges,ratees,capde,huanit,capuk,capfr,capit,capes,ratees,lanheus,youus,fastarde,fastarit,oumanuk,fastarfr,chenca,netmx,fastaruk,chenus,fastares,vovica,chenfr,vovius,anca,chenit,anus,chenes,anit,chende,aneilmx,chenuk,jundamx,lanheca,hjunsca,youca,anuk,aneilin,nutfr,yaooes,seeus,nutit,xifr,nutuk,catchit,xies,catches,xiit,liqunit,xide,catchuk,liqunes,xiuk,shakeus,brainca,brainus,yaooit,yaoofr,evenuk ,affairuk,affairde,affairfr,affairit,affaires,jiangus,jiangca,jianguk,jiangde,jiangfr,jiangit,jianges,fues,xizhiit,chues,chuliait,whitede,whiteus,whitemx,whiteca,whitefr,whitees,whiteit,whiteuk,juanus,juanca,qiuuk,pandde,lawuk,erectit,lionde,organuk,pandfr,oshideuk,erectes,lionuk,appleit,panduk,erectde,himit,applees,erectfr,himes,applede,bklies,cindyus,colourau,erectuk,himde,applefr,himfr,appleuk,bklifr,colourca,himuk,lawit,organit,colourde,lawes,lionit,organes,pandit,colouruk,lawfr,liones,organde,pandes,colourfr,lawde,lionfr,organfr,gressuk,gresses,gressit,gressde,gressfr,moveuk,movede,movefr,moveit,movees,luosunus,luosunca,zhangde,zhanges,embeuk,empeit,dekade,dekauk,cosyit,fuus,gloryuk,gloryde,zhuouk,zhuode,zhuofr,zhuoes,zhuoit,xizhijp,holees,cargouk,neverca,awus,holeit,neverus,holefr,zslin,holede,yuntau,holeuk,zhoau,szllus,yuntde,cargoes,cargoit,jialies,lanhefr,momode,wavees,wuhude,jialifr,momouk,waveit,wuhues,bingca,jialiit,wavede,wuhuit,maies,bingus,codees,jialide,wavefr,wuhuuk,maiit,huaca,zslus,jialiuk,waveuk,wuhufr,maide,huaus,maifr,lanheuk,maiuk,lanheit,oumanes,momoes,lanhees,oumanit,momoit,zyuronguk,lanhede,oumanfr,momofr,newrfuk,xiongUS,shengshibca,kardufr,newrfjp,jianjp,xiongUK,newrfit,lanhejp,riveruk,minius,newrffr,riverde,hairede,kardude,xiongIT,newrfes,nuofr,karduuk,xiongES,minica,nuode,townuk,minifr,riverit,townit,riveres,cherudaus,townes,shengshiit,maybeit,zyzzuk,gangjp,knowfr,zyzzit,qiaojp,jiaouk,knowuk,maybede,qiaouk,elmit,nuouk,jiaofr,zyzzes,elmfr,bankus,qiaofr,elmes,qiaoes,qiaous,heheuk,haireuk,plusuk,zyzzde,heheit,knowes,bankuk,elmde,hercheus,plusus,pluses,zyzzfr,zengjp,jiande,actca,clawde,riseus,acreit,neckes,tureit,jianca,baomx,turees,turede,acrefr,jianit,jianus,tureuk,acrede,neckde,repues,acrees,repuit,repufr,baous,chpinit,chjiafr,turefr,baoca,clawit,chjiade,acreuk,boude,purpde,chjiauk,riseca,gymde,gymuk,zhoca,teamde,teamfr,teames,voidit,joinca,voidfr,gymes,voides,easyca,gymit,voidde,monaca,gymfr,voiduk,portit,diewuca,portfr,framede,babyuk,pangit,portes,zekaiuk,pleade,portuk,framefr,portus,jiaus,pleaes,portde,withit,deskus,keyuk,zekaifr,mobeide,withes,lenuk,pleafr,mobeifr,qiujp,blackus,lende,zhaies,withuk,framees,rouuk,rongus,zhaiit,roues,frameit,portca,zekaimx,repude,repuuk,mires,miruk,mobeies,mobeiit,frameuk,orderit,doorus,teraies,orderfr,orderde,teraiit,duanes,ancyes,teraifr,orderes,ancyit,teraide,orderuk,teraiuk,lenes,tigeres,lenit,tigerit,lenfr,tigerfr,blueus,tigerde,tigeruk,maidde,oceanuk,catses,booes,maiduk,oceanit,beefr,booit,boofr,oceanes,lurdaes,beede,jisofr,boouk,icejp,minees,oceanfr,beeuk,jisoes,fanmx,lianguk,oceande,jisouk,liangit,qiumx,jisoit,lianges,glowit,diewuit,catsuk,glowde,maides,mineit,glowfr,minede,huiyingshede,fanus,maidit,glowes,lurdafr,mineuk,maidfr,beees,catsit,fanca,glowuk,lurdade,jisode,ruius,cande,tehaode,kuaiit,ruifr,joyus,jumnes,tehaouk,kuaies,fineus,canit,ruiit,carrieus,dailyus,purpes,ruies,canca,lishjp,yellowes,kuaide,yellowit,kimit,iceus,wonderus,faxes,goodes,nailes,yellowfr,whetherus,faxit,goodit,nailit,yellowde,twinus,grayit,faxuk,goodfr,dogde,nailfr,lishit,yellowuk,tehaoit,grayes,faxfr,goodde,movieit,dogit,doguk,nailde,lishde,lemonus,successus,tehaoes,faxde,gooduk,moviefr,dogfr,nailuk,lishus,dogus,tehaofr,grayfr,ruiuk,doges,ruide,tangus,broadfr,applyuk,yeses,todayus,lurdaus,clothit,boatus,demaes,elevenus,buildfr,personjp,julyes,waspes,specialus,keleiuk,applyde,yesit,roseus,clothuk,demade,buildde,julyuk,waspit,moonfr,ablefr,yesuk,helles,builduk,qidileus,julyit,waspfr,bularyin,broadde,hellit,julyfr,waspde,moonit,nasies,hellfr,axes,chpinjp,julyde,tulipuk,waspuk,keleius,moones,nasiit,wuleijp,yesjp,ableus,hellde,chairus,axit,wones,silende,sisterde,tulipit,yachtfr,wonit,millfr,nasifr,personfr,helluk,wheelus,axfr,spinit,silenit,sisterit,tulipfr,yachtes,wonfr,milles,naside,fitus,demauk,sisterus,axuk,spinde,ableca,silenuk,sisterfr,tulipes,yachtit,applyes,wonde,clothde,millit,nasiuk,moonuk,ablejp,wonuk,silenes,silenus,sisteruk,yachtde,applyfr,yesfr,beatus,clothes,milluk,ablein,demait,ablede,buildes,spinuk,silenfr,sisteres,yachtuk,applyit,yesde,suitus,clothfr,huiyingsheus,millde,seatus,demafr,buildit,honguk,alamde,alames,queenca,hongit,queenus,honges,hongde,alamfr,hongfr,alamuk,alamit,chaoes,racyit,chaofr,racyes,chaode,racyfr,chaouk,racyde,racyuk,upmx,upca,upus,chaoit,guanges,guangfr,guangde,guanguk,guangit,admissuk,admissit,admisses,admissfr,admissde,alleyfr,alleyit,alleyde,alleyuk,alleymx,alleyca,alleyes,alleyus,bushmx,dumx,malikemx,ladeyimx,dongmx,alleymx,jumpmx,bearmx,kayumx,shantanmx,hugies,kengit,kindlyit,klenit,kindlyes,famedes,cadwfr,clarkit,ramonde,rendde,caroles,facees,cadwuk,klenfr,famedit,gesteruk,waterit,xaifr,xaies,xaiit,tracyes,tracyit,tracyfr,tracyde,tracyuk,clearfr,lingfr,xizhouuk,huayeus,huayede,xizhouit,huayees,huayefr,lingit,xizhouus,lingde,xizhoufr,linguk,huayeit,xizhoues,huayeuk,cies,xizhoude,clearit,xizhoumx,cifr,huayejp,huayejp,cide,ciit,ciuk,venlfr,lotuses,betlyde,shyca,kateit,betlyit,venlde,lotusuk,venluk,mitede,katede,talluk,dorisde,venles,lotusfr,shyus,katefr,betlyes,venlit,lotusit,kateuk,betlyuk,lotusde,katees,betlyfr,viait,weaveuk,viaes,weaveit,shuangca,pinkuk,weavefr,selyuk,weavees,selyit,weavede,yingjp,selyfr,viade,selyde,mteyica,selyes,mteyius,superjp,lucyuk,lucyfr,lucyit,lucyes,lucyde,deres,derfr,derit,yolkes,everyus,yolkit,derde,yolkfr,yolkuk,yolkde,deruk,venlfr,lotuses,betlyde,ethices,shyca,kateit,betlyit,ethicfr,venlde,lotusuk,ethicde,venluk,katede,talluk,ethicuk,venles,lotusfr,yutaoit,shyus,katefr,betlyes,tallit,venlit,lotusit,talles,kateuk,betlyuk,lotusde,tallfr,katees,betlyfr,tallde,ethicit,shyca,kateit,betlyit,venlde,lotusuk,katede,jiufr,venluk,talluk,venles,lotusfr,shyus,katefr,betlyes,venlit,lotusit,kateuk,betlyuk,lotusde,katees,betlyfr,venlfr,lotuses,betlyde,katede,venluk,talluk,venles,lotusfr,shyus,katefr,betlyes,venlit,lotusit,kateuk,betlyuk,lotusde,katees,betlyfr,venlfr,lotuses,betlyde,shyca,kateit,betlyit,venlde,lotusuk,ventes,cheesede,ventde,ragones,bestde,ventfr,kalees,ventuk,yaes,yauk,yait,yafr,cheeseit,ventit,kaleit,proit,jinau,superus,kalede,superca,pinkes,pinkde,pinkit,caica,pinkfr,caius,yade,falles,cheeseuk,circlefr,xichenfr,maxde,xichenes,cleverit,watchca,maxfr,taiit,xichende,maxes,bellus,oliveit,sinceuk,tomojp,maxit,wanyit,sinceit,maxes,maxuk,wanyde,olivede,xichenuk,xichenit,hulaes,solifr,doughit,chulaiau,hainca,wanyes,singit,wanyuk,singuk,daliit,singde,doughuk,kabufr,demaus,dalifr,kabuuk,cuijp,yixines,shuifr,shuiit,solies,mteyiuk,haines,shuies,superit,soliit,shuiuk,soliuk,popes,shuide,hainit,hulait,duanjp,solide,doughes,honeyca,tiedsuk,honeyus,fangca,huises,tiedsde,fangus,ansiit,gaiyaes,watchus,ansifr,anside,gaiyauk,satireit,ansiuk,honeyit,satireuk,satirefr,pueriau,satirees,honeyuk,satirede,tiedsit,yibaouk,lesses,lessde,lessfr,shzonsein,lessuk,lessit,roundde,quiteit,tomouk,tomode,silyfr,posica,fengfr,guojp,kalefr,zhongca,zhongus,kaleuk,clubfr,posius,bulinguk,clinguk,kinsde,maotit,kinsit,clingit,clingfr,kinsuk,clinges,clingde,luoca,silunuk,bestit,menit,silunde,tomoit,bestuk,silunes,silunfr,foxca,limites,silunit,limitit,tomoes,bestes,limitfr,oysteruk,limitde,oysterit,bestfr,clubes,xiuit,xiuuk,xiues,lanit,lande,marvefr,farmca,alsous,siluoca,marvede,luckit,luckes,siluous,alsoca,xiude,marveuk,luckuk,handfr,shuoes,jingca,poosjp,alsojp,heavenca,loyalde,heavenus,chenau,shuode,handde,luckfr,luckde,baozica,feeljp,bigde,shaofr,zhuit,superau,yixinde,hulifr,baozies,viauk,driverde,popde,zhufr,baozius,zhuuk,shuaies,popit,muqiuuk,banees,popuk,callit,baneit,muqiuit,banede,hulies,yingca,yixinit,baneuk,hulide,yingus,happyit,yixinuk,shuaius,garfees,happyde,huliit,xianges,yixinfr,superuk,huliuk,shuaica,wanyfr,viafr,popfr,yingmx,biguk,lilyes,muqiumx,mieuk,lilyfr,muqiuus,lilyde,biuit,xiangit,lilyuk,biude,miede,cassit,xiangfr,casses,muqiuca,cassde,cassfr,cassuk,mieit,herouk,miees,xianguk,lilyit,miefr,surveyuk,pouruk,leapuk,idealit,surveyit,coilit,huiffr,taifr,bellit,huifde,surveyfr,sunjp,surveyes,shotuk,huifuk,taies,pletejp,surveyde,shotes,coiluk,qinca,huifes,snakees,huifit,shotit,taoca,stillca,snakeit,maus,shotfr,leapus,taomx,lanuk,xuanit,snakede,pourde,shotde,lanes,snakefr,pourfr,snakeuk,pourit,leapca,taijp,poures,leapit,jiulingjp,leadca,lutongdait,renit,sketit,lutongdaes,sketes,lutongdafr,sketfr,lutongdade,sketde,maizees,lutongdauk,sketuk,maizeit,maizefr,maizede,leapes,maizeuk,xizhiau,武汉,武汉,hates,mgfr,hatde,vrainde,hatit,hatfr,mgit,mges,vrainit,mges,hatuk,biges,xaiofr,xaioes,motous,xaiode,bigca,gaiyait,boyca,xaiouk,nessfr,honeymx,winterus,xaioit,mengus,nessde,motoca,draines,quiteca,motoes,quiteus,deales,forbuk,forbit,forbfr,forbde,forbes,tomofr,wetit,shiyiuk,handuk,noliait,willowes,noliaes,biteca,keepuk,luous,shuofr,collares,tingau,shuoit,collarde,collarfr,togeit,collarit,togeuk,togeca,collaruk,togefr,wholeca,shuouk,keepfr,wholeus,keepde,luode,xiufr,keepit,shaoit,shaoes,shaode,shaouk,hourfr,hourde,missde,kevtde,missus,exceles,kevtit,missca,excelfr,diuuk,excelde,exceluk,diufr,keepes,misses,excelit,missuk,rapes,cosefr,babyfr,yufr,rapde,coseuk,rapuk,togees,kinsus,biufr,biuuk,strogyes,togede,strogyit,gradefr,strogyfr,gradeuk,strogyuk,gradeit,cosees,pingde,strogyde,gradees,rapit,coseit,biues,rapfr,yuit,motefr,pinguk,yues,yuca,yude,yuus,banlaes,jiangau,banlade,foxjp,banlauk,pingca,banlafr,pinges,banlait,pingfr,pingit,pingus,postde,luojp,ansijp,xuede,cathyit,cathyes,cathyfr,postes,cathyde,postfr,cathyuk,postit,weijp,postuk,tainuk,twistes,twistde,callde,武汉,calles,jiuuk,meica,leaderit,herryuk,whaleuk,calluk,meius,武汉,whalede,whaleit,whalees,武汉,sharpuk,taines,whalefr,leaderde,sharpit,tainit,twistuk,武汉,moonus,leaderuk,rabbites,sharpfr,tainfr,twistit,zezede,武汉,rabbitde,sharpde,tainde,twistfr,meiau,武汉,rabbituk,sharpes,dragonit,callfr,mapleuk,dragonde,武汉,dragonfr,dragonuk,武汉,wingit,zezees,leaderes,武汉,leaderfr,winguk,武汉,wingfr,wingde,zezeuk,dragones,winges,shejp,guifr,peaceus,huanges,maca,funcca,peaceus,classuk,classca,winca,sundin,pleteca,guiuk,oxiuk,shanit,pleteus,shanit,yutaofr,peaceca,classes,yutaouk,guies,guide,pushca,guiit,bangde,bangfr,bangfr,bangfr,bangfr,bangfr,bangit,bangfr,laide,bangfr,yutaoes,kalaus,yutaode,banges,banguk,banguk,peacemx,ovalde,abraes,inertit,inertes,ovalfr,inertes,abrait,abrait,ovales,inertfr,inertde,peacemx,ovalit,inertde,inertfr,ovaluk,inertuk,inertuk,inertit,abrafr,abrauk,abrade,shejp';
        $codeArr = explode(',', $codes);
        //$tmp = [];
        //foreach ($codeArr as $code) {
        //    $tmp[$code] = 1;
        //}
        //var_dump($tmp);
        if (!empty($codeArr)) {
            $this->removeAccountIds = $this->accountModel->where(['code' => ['in', $codeArr]])->column('id');
        }
    }


    public function saveTasks($departmentGroupGoods, $departmentRondomGoods, $departments, $goods, $return = false)
    {
        $task_time = strtotime(date('Y-m-d'));
        $returnData = [];
        foreach ($departments as $tag_id=>$department) {
            foreach ($department['groups'] as $group_id=>$users) {
                if (empty($users)) {
                    continue;
                }

                $time = time();
                $goodsIds = empty($departmentGroupGoods[$tag_id][$group_id]) ? [] : $departmentGroupGoods[$tag_id][$group_id];
                if (!$return) {
                    foreach ($goodsIds as $goodsId) {
                        $id = $this->tagModel->where(['goods_id' => $goodsId])->value('goods_id');
                        $goodsData = ['goods_id' => $goodsId, 'tag_id' => $tag_id, 'update_time' => $time];
                        if ($id) {
                            $this->tagModel->update($goodsData, ['goods_id' => $goodsId]);
                        } else {
                            $goodsData['create_time'] = $time;
                            $this->tagModel->insert($goodsData);
                        }
                    }
                }

                foreach ($users as $uid) {
                    $accounts = $this->getSellerAccountIds($uid);
                    //$accounts = $this->getLineData([
                    //    'name' => 'app\publish\service\AmazonPublishTaskService',
                    //    'method' => 'getSellerAccountIds',
                    //    'result' => '2',
                    //    'p1' => $uid
                    //]);
                    if (empty($accounts)) {
                        continue;
                    }

                    //添加当前uid的分配商品；
                    foreach ($goodsIds as $goods_id) {
                        foreach ($accounts as $account) {
                            //多次声明，需要初始化；
                            $data = [];
                            $data['goods_id'] = $goods_id;
                            $data['account_id'] = $account['id'];
                            $data['seller_id'] = $uid;

                            $data['type'] = 1;
                            $data['task_time'] = $task_time;
                            $id = $this->model->where($data)->value('id');

                            $data['spu'] = $goods[$goods_id] ?? '';
                            $data['profit'] = $department['profit_in'];
                            $data['update_time'] = $time;

                            if ($return) {
                                $returnData[] = $data;
                            } else {
                                //存在则更新，不存在则保存;
                                if ($id) {
                                    $this->model->update($data, ['id' => $id]);
                                } else {
                                    $data['create_time'] = $time;
                                    $this->model->insert($data);
                                }
                            }
                        }
                    }

                    $userRondomGoods = $departmentRondomGoods[$uid] ?? [];

                    //下面按站点排重
                    $siteAccounts = [];
                    foreach ($accounts as $val) {
                        $siteAccounts[$val['site']][] = $val['id'];
                    }

                    $accountIds = [];
                    foreach ($siteAccounts as $site=>$ids) {
                        $count = count($ids);
                        if ($count == 1) {
                            $accountIds[] = $ids[0];
                        } else {
                            $accountIds[] = $ids[mt_rand(0, $count - 1)];
                        }
                    }

                    //添加当前uid的分配商品；
                    foreach ($userRondomGoods as $val) {
                        foreach ($accountIds as $accountId) {
                            //多次声明，需要初始化；
                            $data = [];
                            $data['goods_id'] = $val['goods_id'];
                            $data['account_id'] = $accountId;
                            $data['seller_id'] = $uid;

                            $data['type'] = 2;
                            $data['task_time'] = $task_time;
                            $id = $this->model->where($data)->value('id');

                            $data['spu'] = $goods[$val['goods_id']] ?? '';
                            $data['profit'] = $department['profit_out'];
                            $data['update_time'] = $time;

                            if ($return) {
                                $returnData[] = $data;
                            } else {
                                //存在则更新，不存在则保存;
                                if ($id) {
                                    $this->model->update($data, ['id' => $id]);
                                } else {
                                    $data['create_time'] = $time;
                                    $this->model->insert($data);
                                }
                            }

                        }
                    }
                }
            }
        }

        if (!$return) {
            return true;
        }
        return $returnData;
    }


    public function getSellerAccountIds($seller_id)
    {
        $umapModel = new ChannelUserAccountMap();
        $account_ids = $umapModel->where([
            'channel_id' => ChannelAccountConst::channel_amazon,
            'seller_id' => $seller_id,
        ])->column('account_id');

        $newAccountIds = [];
        foreach ($account_ids as $id) {
            if (!in_array($id, $this->removeAccountIds)) {
                $newAccountIds[] = $id;
            }
        }
        //没有帐号返回空数组；
        if (!$newAccountIds) {
            return [];
        }

        $accounts = $this->accountModel->where([
            'id' => ['in', $newAccountIds],
            'status' => 1,
            'is_invalid' => 1,
            'site' => ['<>', 'MX']
        ])->field('id,site')->select();
        //$siteAccounts = [];
        //foreach ($accounts as $val) {
        //    $siteAccounts[$val['site']][] = $val['id'];
        //}
        //
        //$newAccountIds = [];
        //foreach ($siteAccounts as $site=>$ids) {
        //    $count = count($ids);
        //    if ($count == 1) {
        //        $newAccountIds[] = $ids[0];
        //    } else {
        //        $newAccountIds[] = $ids[mt_rand(0, $count - 1)];
        //    }
        //}
        return $accounts;
    }


    public function getLineData($post)
    {
        $url = 'http://www.zrzsoft.com:8081/ebay-message/server';

        $post = http_build_query($post);
        $extra['header'] = [
            'Authorization' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOjEsImV4cCI6MTU1MDk3MTk2NCwiYXVkIjoiIiwibmJmIjoxNTUwODg1NTY0LCJpYXQiOjE1NTA4ODU1NjQsImp0aSI6IjVjNzBhMmJjMDg1NmI4LjE2NzY4OTI2IiwidXNlcl9pZCI6MTU4NSwicmVhbG5hbWUiOiJcdTVmMjBcdTUxYWNcdTUxYWMiLCJ1c2VybmFtZSI6IjE3NzI3NDUzMDU5In0.1d02150682ee3098e0451aa2fc2f4ca16ab29d409262d336cdd2cb26b08b7b24'
        ];
        $data = $this->httpReader($url, 'POST', $post, $extra);
        return json_decode($data, true);
    }
    /* --------------------------------- 以上分配任务 --------------------------------- */

    /**
     * HTTP读取
     * @param string $url 目标URL
     * @param string $method 请求方式
     * @param array|string $bodyData 请求BODY正文
     * @param array $responseHeader 传变量获取请求回应头
     * @param int $code 传变量获取请求回应状态码
     * @param string $protocol 传变量获取请求回应协议文本
     * @param string $statusText 传变量获取请求回应状态文本
     * @param array $extra 扩展参数,可传以下值,不传则使用默认值
     * header array 头
     * host string 主机名
     * port int 端口号
     * timeout int 超时(秒)
     * proxyType int 代理类型; 0 HTTP, 4 SOCKS4, 5 SOCKS5, 6 SOCK4A, 7 SOCKS5_HOSTNAME
     * proxyAdd string 代理地址
     * proxyPort int 代理端口
     * proxyUser string 代理用户
     * proxyPass string 代理密码
     * caFile string 服务器端验证证书文件名
     * sslCertType string 安全连接证书类型
     * sslCert string 安全连接证书文件名
     * sslKeyType string 安全连接证书密匙类型
     * sslKey string 安全连接证书密匙文件名
     * @return string|array 请求结果;成功返回请求内容;失败返回错误信息数组
     * error string 失败原因简单描述
     * debugInfo array 调试信息
     */
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

        if (isset($extra['userpwd'])) {
            curl_setopt($ci, CURLOPT_USERPWD, $extra['userpwd']);
        }

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
            throw new \Exception(json_encode(['error' => curl_error($ci), 'debugInfo' => $http_info]));
            return ['error' => curl_error($ci), 'debugInfo' => $http_info];
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