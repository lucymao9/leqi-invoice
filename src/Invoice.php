<?php

namespace Lucymao9\Leqi;

use Evit\PhpGmCrypto\Encryption\EvitSM4Encryption;
use GuzzleHttp\Client;
use Lucymao9\Leqi\Exceptions\InvalidArgumentException;
use Lucymao9\Leqi\Exceptions\InvalidPublicKeyException;
use Lucymao9\Leqi\Exceptions\InvalidResponseException;
use Rtgm\sm\RtSm4;

class Invoice
{
    protected $host = 'https://lqpt.chinatax.gov.cn:8443/access/v2/invoke';

    protected $secret = '';

    protected $leqiId = '';//接入单位|直连单位
    protected $leqiId2 = '';//使用单位


    /**
     * 能力编号
     * @var mixed
     */
    protected $abilityCode = '';
    /**
     * 用例编号
     * @var string
     */
    protected $caseCode = '';

    protected $isSandbox = '';


    public function __construct(array $config)
    {
        if (!isset($config['abilityCode']) || !$config['abilityCode']) {
            throw new InvalidArgumentException('miss required parameter[abilityCode]');
        }
        if (!isset($config['leqiId']) || !$config['leqiId']) {
            throw new InvalidArgumentException('miss required parameter[leqiId]');
        }
        if (isset($config['host']) && $config['host']) $this->host = $config['host'];
        $this->secret = $config['secret'] ?? $this->secret;
        $this->isSandbox = $config['isSandbox'] ?? $this->isSandbox;
        $this->abilityCode = $config['abilityCode'];
        $this->caseCode = $config['caseCode'] ?? '';
        $this->leqiId = $config['leqiId'];
        $this->leqiId2 = $config['leqiId2'] ?? $this->leqiId;
    }

    private function _doRequest(string $method, $content, array $opts = [])
    {
        $options = [];
        $sm4 = new RtSm4(hex2bin($this->secret));
        $encBody = $sm4->encrypt($content ? json_encode($content, 256) : '{}','sm4-ecb',hex2bin($this->secret),'base64');
        $options['body'] = $encBody;
        $options['headers'] = [
            'fwbm' => $opts['fwbm'],//添加服务编码
            'jrdwptbh' => $this->leqiId,// 添加接入单位平台编号
            'sydwptbh' => $this->leqiId2,// 添加使用单位平台编号
            'nlbm' => $this->abilityCode,// 添加能力编码
            'ylbm' => $this->caseCode,// 添加用例编码
            'access_signature' => '',// 添加访问签名
            'sxcsbz' => $this->isSandbox,// 添加沙箱测试标志
        ];
        if (isset($opts['isControl']) && $opts['isControl']) {
            $url = $this->host . '/' . $opts['fwbm'];
        } else {
            $url = $this->host . '/' . $this->abilityCode . '/' . $opts['fwbm'];
        }
        $client = new Client();
        $request = $client->request($method, $url, $options);
        $response = $request->getBody()->getContents();
        $result = json_decode($response, true);
        if (!$result) {
            throw new InvalidResponseException('invalid response', $result['httpStatusCode'] ?? 500);
        }
        $responseBody = json_decode($result['body'], true);
        if (!isset($responseBody['Response']['Data'])) {
            throw new InvalidResponseException($result['body'] ?? 'invalid response', $result['httpStatusCode'] ?? 500);
        }
        $json = $sm4->decrypt($responseBody['Response']['Data'], 'sm4-ecb', hex2bin($this->secret),'base64');
        $data = json_decode($json, true);
        return $data;
    }

    /**
     * 获取数电票批量预赋码信息接口 QDFPPLFM
     * @param array $params
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getInvoiceCode(array $params = [])
    {
        $serviceCode = 'QDFPPLFM';

        $content = [
            'nsrsbh' => $params['seller_tax_serial'],//纳税人识别号/统一社会信用代码
            'lysl' => $params['quantity'],//领用数量
            'ywlsh' => $params['seq_id'],//业务流水号
        ];
//        if (isset($params['certificate_ids']) && $params['certificate_ids'])
//            $json['certificate_ids'] = $params['certificate_ids'];


        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 查询发票额度 CXSXED
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function getInvoiceAmount(array $params = [])
    {
        $serviceCode = 'CXSXED';
        $content = [
            'nsrsbh' => $params['seller_tax_serial'],//纳税人识别号/统一社会信用代码
        ];

        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 下载/退回发票额度 XZTHSXED
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function updateInvoiceAmount(array $params = [])
    {
        $serviceCode = 'XZTHSXED';
        $content = [
            'nsrsbh' => $params['seller_tax_serial'],//纳税人识别号/统一社会信用代码
            'ptbh' => $this->leqiId,//平台编号
            'sqlx' => $params['type'],//申请类型0：下载1：退回
            'sqed' => $params['quantity'],//申请额度
            'ywlsh' => $params['seq_id'],//业务流水号
        ];

        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 调整发票额度有效期 TZSXEDYXQ
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function updateInvoiceAmountDate(array $params = [])
    {
        $serviceCode = 'TZSXEDYXQ';
        $content = [
            'xsfnsrsbh' => $params['seller_tax_serial'],//纳税人识别号/统一社会信用代码
            'sxedsq' => $params['date'],//发票额度属期 yyyy-MM
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 查询纳税人风险信息 CXNSRFXXX
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function getTaxerRisk(array $params = [])
    {
        $serviceCode = 'CXNSRFXXX';
        $content = [
            'nsrsbh' => $params['seller_tax_serial'],//纳税人识别号/统一社会信用代码
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 查询纳税人基本信息 CXNSRJBXX
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function getTaxerInfo(array $params = [])
    {
        $serviceCode = 'CXNSRJBXX';
        $content = [
            'nsrsbh' => $params['seller_tax_serial'],//纳税人识别号/统一社会信用代码
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }
//7 查询可用税率信息 CXKYSL

    /**
     * 查询可用税率信息 CXKYSL
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function getTaxRate(array $params = [])
    {
        $serviceCode = 'CXKYSL';
        $content = [
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 查询税收分类编码信息 CXSSFLBM
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function getTaxCategory(array $params = [])
    {
        $serviceCode = 'CXSSFLBM';
        $content = [
            'nsrsbh' => $params['seller_tax_serial'],//纳税人识别号/统一社会信用代码
            'sjc' => $params['time'] ?? '',//时间戳,格式：yyyyMMddHHmmss,首次下载时为空；非首次下载时，传入上次下载返回的时间戳.格式：yyyyMMddHHmmss
            'sjswjgdm' => $params['code'] ?? '',//省级税务机关代码
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 查询数电红字确认单列表信息 CXQDHZQRDLB
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function queryRedInvoices(array $params = [])
    {
        $serviceCode = 'CXQDHZQRDLB';
        $content = [
            'yhjslx' => $params['taxer_type'],//用户角色类型0：销方1：购方
            'xsfnsrsbh' => $params['seller_tax_serial'],//用户角色类型0：销方1：购方
            'xsfmc' => $params['seller_name'] ?? '',//（销售方）名称
            'gmfnsrsbh' => $params['buyer_tax_serial'] ?? '',//（购买方）统一社会信用代码/纳人识别号/身份证件号码
            'gmfmc' => $params['buyer_name'] ?? '',//（购买方）名称
            'lrfsf' => $params['record_type'] ?? '',//录入方身份0：销方1：购方
            'lrrqq' => $params['record_date_from'] ?? '',//录入日期起 yyyy-MM-dd
            'lrrqz' => $params['record_date_to'] ?? '',//录入日期止 yyyy-MM-dd
            'lzfpdm' => $params['blue_invoice_code'] ?? '',//蓝字发票代码
            'lzfphm' => $params['blue_invoice_no'] ?? '',//蓝字发票号码
            'hzfpxxqrdbh' => $params['red_invoice_no'] ?? '',//红字确认单编号
            'hzqrxxztDm' => $params['red_invoice_state'] ?? '',//红字确认信息状态代码
            'fppzDm' => $params['invoice_type'] ?? '',//发票票种代码01：数电专02：数电普
            'pageNumber' => $params['page'] ?? '',//页码
            'pageSize' => $params['page_size'] ?? '',//每页数量
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 查询数电红字确认单明细信息 CXQDHZQRDMX
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function getRedInvoice(array $params = [])
    {
        $serviceCode = 'CXQDHZQRDMX';
        $content = [
            'xsfnsrsbh' => $params['seller_tax_serial'],//纳税人识别号/统一社会信用代码
            'uuid' => $params['uuid'],//红字确认单 UUID
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 数电票上传 QDFPSC
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function uploadInvoice(array $params = [])
    {
        $serviceCode = 'QDFPSC';
        $content = [];
        foreach ($params as $v) {
            $content[] = [
                'fphm' => $v['invoice_code'],//发票号码
                'lzfpbz' => $v['invoice_tag'],//蓝字发票标志 0：蓝字发票1：红字发票
                'ptbh' => $this->leqiId,//平台编号,直连单位ID
                'fppz' => $v['invoice_type'],//发票票种01：数电专02：数电普
                'gmfzrrbz' => $v['invoice_flag'] ?? '',//购买方自然人标志Y：购买方是自然人N：购买方非自然人
                'tdys' => $v['special'] ?? '',//特定要素
                'qyDm' => $v['area_code'],//区域代码
                'cezslxDm' => $v['tax_type'] ?? '',//差额征税类型代码,空：非差额发票01：全额开票02：差额开票
                'sgfplxDm' => $v['buyer_tax_type'] ?? '',//收购发票类型代码,空：非收购发票01：农产品收购发票02：光伏收购发票03：二手车收购发票
                'ckywsyzcDm' => $v['export_tax_type'] ?? '',//出口业务适用政策代码,空：非出口业务01：退税政策02：免税政策03：征税政策
                'zzsjzjtDm' => $v['value_add_tax_type'] ?? "",//增值税即征即退代码,空：非增值税即征即退01：软件产品发票02：资源综合利用产品发票03：管道运输服务发票04：有形动产融资租赁服务05：有形动产融资性售后回租服务发票06：新型墙体材料发票07：风力发电产品发票08：光伏发电产品发票09：动漫软件产品发票10：飞机维修劳务发票11：黄金发票12：铂金发票
                'xsfnsrsbh' => $v['seller_tax_serial'],//纳税人识别号/统一社会信用代码
                'xsfmc' => $v['seller_name'],//(销售方)名称
                'xsfdz' => $v['seller_address'] ?? '',//(销售方)地址
                'xsfdh' => $v['seller_mobile'] ?? '',//销售方电话
                'xsfkhh' => $v['seller_bank'] ?? '',//销售方开户行
                'xsfzh' => $v['seller_bank_account'] ?? '',//销售方账号
                'gmfnsrsbh' => $v['buyer_tax_serial'] ?? '',//（购买方）统一社会信用代码/纳税人识别号/身份证件号码,开具数电专票时，必填
                'gmfmc' => $v['buyer_name'],//(购买方)名称
                'gmfdz' => $v['buyer_address'] ?? '',//（购买方）地址
                'gmfdh' => $v['buyer_mobile'] ?? '',//（购买方）电话
                'gmfkhh' => $v['buyer_bank'] ?? '',//（购买方）开户行
                'gmfzh' => $v['buyer_bank_account'] ?? '',//（购买方）账号
                'gmfjbr' => $v['agent_name'] ?? '',//购买方经办人姓名
                'jbrsfzjhm' => $v['agent_tax_serial'] ?? '',//购买方经办人证件号码
                'gmfjbrlxdh' => $v['agent_mobile'] ?? '',//购买方经办人电话
                'hjje' => round($v['amount'] - $v['amount_tax'], 2),//合计金额
                'hjse' => $v['amount_tax'],//合计税额
                'jshj' => $v['amount'],//价税合计
                'skyhmc' => $v['bank_name'] ?? '',//收款银行名称
                'skyhzh' => $v['bank_account'] ?? '',//收款银行账号
                'jsfs' => $v['pay_method'] ?? '',//结算方式 01：现金02：银行转账03：票据04：第三方支付05：预付卡99：其他
                'ysxwfsd' => $v['tax_address'] ?? '',//应税行为发生地
                'kpr' => $v['drawer_name'],//开票人
                'kprzjhm' => $v['drawer_ID'] ?? '',//开票人证件号码
                'kprzjlx' => $v['drawer_ID_type'] ?? '',//开票人证件类型 100：单位101：组织机构代码证102：营业执照103：税务登记证199：其他单位证件200：个人201：居民身份证202：军官证203：武警警官证204：士兵证205：军队离退休干部证206：残疾人证207：残疾军人证（1-8 级）208：外国护照209：港澳同胞回乡证210：港澳居民来往内地通行证211：台胞证212：中华人民共和国往来港澳通行证 213：台湾居民来往大陆通行证214：大陆居民往来台湾通行证215：外国人居留证216：外交官证217：使（领事）馆证218：海员证219：香港永久性居民身份证220：台湾身份证221：澳门特别行政区永久性居民身份证222：外国人身份证件223：高校毕业生自主创业证224：就业失业登记证225：退休证220：离休证227：中国护照228：城镇退役士兵自谋职业证229：随军家属身份证明230：中国人民解放军军官转业证书231：中国人民解放军义务兵退出现役证232：中国人民解放军士官退出现役证233：外国人永久居留身份证（外国人永久居留证）234：就业创业证235：香港特别行政区护照236：澳门特别行政区护照237：中华人民共和国港澳居民居住证238：中华人民共和国台湾居民居住证239：《中华人民共和国外国人工作许可证》（A类）240：《中华人民共和国外国人工作许可证》（B类）241：《中华人民共和国外国人工作许可证》（C类）291：出生医学证明299：其他个人证件
                'dylzfphm' => $v['blue_invoice_no'] ?? '',//对应蓝字发票号码,是否蓝字发票标志为1 时，此节点有内容红票开具时必传；如果红冲的是税控发票，对应蓝字发票号码的填写规则为税控发票的发票代码+发票号码。
                'hzqrxxdbh' => $v['red_invoice_no'] ?? '',//红字确认信息单编号,是否蓝字发票标志为1 时，此节点有内容红票开具时必传
                'hzqrduuid' => $v['red_invoice_uuid'] ?? '',//红字确认单 uuid,是否蓝字发票标志为1 时，此节点有内容红票开具时必传
                'bz' => $v['comment'] ?? '',//备注
                'ip' => $v['ip'],//服务器地址
                'macdz' => $v['mac'],//mac 地址
                'cpuid' => $v['cpu'] ?? '',//CPU 序列号
                'zbxlh' => $v['pn'] ?? '',//主板序列号
                'kprq' => $v['invoice_date'],//开票日期 yyyy-MM-ddHH:mm:ss
                'sfzsxsfyhzhbq' => $v['show_seller_bank'] ?? '',//是否展示销售方银行账号标签
                'sfzsgmfyhzhbq' => $v['show_buyer_bank'] ?? '',//是否展示购买方银行账号标签
                'skrxm' => $v['cashier_name'] ?? '',//收款人姓名
                'fhrxm' => $v['reviewer_name'] ?? '',//复核人姓名
                'fpmxList'=>[
                    [
                        'mxxh' => $v['serial_no'],//明细序号
                        'dylzfpmxxh' => $v['blue_serial_no'] ?? '',//对应蓝字发票明细序号,红票必传
                        'xmmc' => $v['item_name'],//项目名称
                        'hwhyslwfwmc' => $v['goods_name'],//货物或应税劳务、服务名称,拼装规则：“*商品服务简称（spfwjc ）*”+“项目名称（xmmc）”
                        'spfwjc' => $v['short_name'],//商品服务简称
                        'ggxh' => $v['specification'] ?? '',//规格型号
                        'dw' => $v['unit'] ?? '',//单位
                        'sl' => $v['quantity'] ?? '',//数量 如“单价”栏次非空，则本栏次必须非空
                        'dj' => $v['price'] ?? '',//单价 如“数量”栏次非空，则本栏次必须非空
                        'je' => $v['item_amount'],//金额
                        'slv' => $v['item_tax_rate'],//增值税税率/征收率
                        'se' => $v['item_tax_amount'],//税额
                        'hsje' => $v['amount'],//含税金额
                        'kce' => $v['deduce_amount'] ?? '',//扣除额
                        'sphfwssflhbbm' => $v['category_no'],//商品和服务税收 分类合并编码
                        'fphxz' => $v['invoice_quality'],//发票行性质 00：正常行01：折扣行02：被折扣行
                        'yhzcbs' => $v['preferential_policies'] ?? '',//优惠政策标识 01：简易征收02：稀土产品03：免税04：不征税05：先征后退06：100%先征后退07：50%先征后退08：按3%简易征收09：按5%简易征收10：按5%简易征收减按1.5%计征11：即征即退30%12：即征即退50%13：即征即退70%14：即征即退100%15：超税负3%即征即退16：超税负8%即征即退17 ：超税负12%即征即退18：超税负6%即征即退

                    ]
                ],
                'fjysList'=> [
                    [
                        'fjysmc' => $v['additional_name'] ?? '',//附加要素名称
                        'fjyslx' => $v['additional_type'] ?? '',//附加要素类型
                        'fjysz' => $v['additional_value'] ?? '',//附加要素值
                    ]
                ],
//                'cekcList' => [
//                    [
//                        'xh' => $v['number'] ?? '',//序号
//                        'pzlx' => $v['voucher_type'] ?? '',//凭证类型 01：数电票02：增值税专用发票03：增值税普通发票04：营业税发票05：财政票据06：法院裁决书07：契税完税凭证08：其他发票类09：其他扣除凭证
//                        'fpdm' => $v['invoice_number'] ?? '',//发票代码
//                        'cepzhm' => $v['difference_voucher_no'] ?? '',//差额凭证号码
//                        'kjrq' => $v['invoice_date_short'] ?? '',//开具日期yyyy-MM-dd
//                        'pzhjje' => $v['voucher_amount'] ?? 0,//凭证合计金额
//                        'bckcje' => $v['voucher_deduct_amount'] ?? 0,//本次扣除金额该字段需要小于等于凭证合计金额
//                    ]
//                ]

            ];
        }
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 查询数电票上传结果 CXQDFPSCJG
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function queryInvoicesState(array $params = [])
    {
        $serviceCode = 'CXQDFPSCJG';
        $content = [
            'sllsh' => $params['seq_id'],//受理流水号,开票接口返回的流水号
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 数电红字确认单申请 QDHZQRDSQ
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function uploadRedInvoice(array $params = [])
    {
        $serviceCode = 'QDHZQRDSQ';
        $content = [
            'lrfsf' => $params['type'],//录入方身份0：销方1：购方
            'xsfnsrsbh' => $params['seller_tax_serial'],//销售方纳税人识别号
            'xsfmc' => $params['seller_name'],//销售方名称
            'gmfnsrsbh' => $params['buyer_tax_serial'] ?? '',//购买方纳税人识别号
            'gmfmc' => $params['buyer_name'],//购买方名称
            'lzfpdm' => $params['blue_invoice_number'] ?? "",//蓝字发票代码
            'lzfphm' => $params['blue_invoice_code'],//蓝字发票号码
            'sfzzfpbz' => $params['is_paper'],//是否纸质发票 Y：纸质发票N：电子发票
            'lzkprq' => $params['blue_invoice_date'],//蓝字发票开票日期,yyyy-MM-dd HH:mm:ss
            'lzhjje' => round($params['blue_invoice_amount'] - $params['blue_invoice_amount_tax'], 2),//蓝字发票合计金额
            'lzhjse' => $params['blue_invoice_amount_tax'],//蓝字发票合计税额
            'lzfppzDm' => $params['blue_invoice_type'],//蓝字发票票种代码 01: 增值税专用发票02: 普通发票03: 机动车统一销售发票04: 二手车统一销售发票
            'lzfpTdyslxDm' => $params['blue_invoice_type'] ?? '',//蓝字发票特定要素类型代码 01：成品油发票02：稀土发票03：建筑服务发票04：货物运输服务发票05：不动产销售服务发票06：不动产租赁服务发票07：代收车船税08：通行费09：旅客运输服务发票10：医疗服务（住院）发票11：医疗服务（门诊）发票12：自产农产品销售发票13 拖拉机和联合收割机发票14：机动车15：二手车16：农产品收购发票17：光伏收购发票 18：卷烟发票20：农产品
            'hzcxje' => round($params['red_invoice_amount'] - $params['red_invoice_amount_tax'], 2),//红字冲销金额
            'hzcxse' => $params['red_invoice_amount_tax'],//红字冲销税额
            'chyyDm' => $params['red_invoice_reason_code'],//红字发票冲红原因代码
            'hzqrdmxList' => [
                [
                    'lzmxxh' => $params['blue_invoice_serial_no'],//蓝字发票明细序号
                    'xh' => $params['number'],//序号
                    'sphfwssflhbbm' => $params['category_no'],//商品和服务税收 分类合并编码
                    'hwhyslwfwmc' => $params['goods_name'],//货物或应税劳务、服务名称
                    'spfwjc' => $params['short_name'],//商品服务简称
                    'xmmc' => $params['item_name'],//项目名称
                    'ggxh' => $params['specification'] ?? '',//规格型号
                    'dw' => $params['unit'] ?? '',//单位
                    'fpspsl' => $params['quantity'] ?? '',//数量 如“单价”栏次非空，则本栏次必须非空
                    'fpspdj' => $params['price'] ?? '',//单价 如“数量”栏次非空，则本栏次必须非空
                    'je' => $params['item_amount'],//金额
                    'sl1' => $params['item_tax_rate'],//增值税税率/征收率
                    'se' => $params['item_tax_amount'],//税额
                ]
            ],
        ];

        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 数电红字确认单确认 QDHZQRDQR
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function confirmRedInvoice(array $params = [])
    {
        $serviceCode = 'QDHZQRDQR';
        $content = [
            'xsfnsrsbh' => $params['seller_tax_serial'],//纳税人识别号/统一社会信用代码
            'uuid' => $params['uuid'],//红字确认单 UUID,申请接口返回的编号
            'hzqrdbh' => $params['invoice_code'],//红字确认单编号
            'qrlx' => $params['invoice_state'],//确认类型 Y：同意N：不同意C：撤销
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 上传发票汇总确认信息 SCFPHZQRXX
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function uploadInvoiceSummary(array $params = [])
    {
        $serviceCode = 'SCFPHZQRXX';
        $content = [
            "xsfnsrsbh" => $params['seller_tax_serial'],//"销售方纳税人识别号",
            "xsfsjswjgdm" => $params['agent_code'],//"销售方省级税务机关代码",
            "ywlx" => $params['type'],//"业务类型",0：确认1：取消
            "ptbh" => $this->leqiId,//"平台编号",
            "yf" => $params['month'],//"月份",yyyy-MM
            "lzfpsl" => $params['blue_invoice_quantity'],//"蓝字发票数量",业务类型为“0”时必填
            "lzfpje" => $params['blue_invoice_amount'],//"蓝字发票金额",业务类型为“0”时必填
            "lzfpse" => $params['blue_invoice_amount_tax'],//"蓝字发票税额",业务类型为“0”时必填
            "hzfpsl" => $params['red_invoice_quantity'],//"红字发票数量",业务类型为“0”时必填
            "hzfpje" => $params['red_invoice_amount'],//"红字发票金额",业务类型为“0”时必填
            "hzfpse" => $params['red_invoice_amount_tax'],//"红字发票税额",业务类型为“0”时必填
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }
//16 查询发票汇总确认信息 CXFPHZQRXX

    /**
     * 查询发票汇总确认信息 CXFPHZQRXX
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function queryInvoiceSummary(array $params = [])
    {
        $serviceCode = 'CXFPHZQRXX';
        $content = [
            "xsfnsrsbh" => $params['seller_tax_serial'],//"销售方纳税人识别号",
            "xsfsjswjgdm" => $params['agent_code'],//"销售方省级税务机关代码",
            "ywlx" => $params['type'],//"业务类型",0：确认1：取消
            "ptbh" => $this->leqiId,//"平台编号",
            "yf" => $params['month'],//"月份",yyyy-MM
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    /**
     * 查询差额征税编码 CXCEZSBM
     * @param array $params
     * @return mixed
     * @throws InvalidResponseException
     */
    public function queryTaxCode(array $params = [])
    {
        $serviceCode = 'CXCEZSBM';
        $content = [
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode]);
        return $result;
    }

    public function getFakeCompany(array $params = [])
    {
        $serviceCode = 'LQSX_SWZJ_GT4_CXXNQYXX';
        $content = [
            'nsrsbh' => $params['seller_tax_serial'],
            'ssjswjgDm' => $params['agent_code'],
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode, 'isControl' => true]);
        return $result;
    }


    public function initFakeCompanyInfo(array $params = [])
    {
        $serviceCode = 'LQSX_SWZJ_GT4_QYXXCSH';
        $content = [
            'nsrsbh' => $params['seller_tax_serial'],
            'ssjswjgDm' => $params['agent_code'],
        ];
        $result = $this->_doRequest('post', $content, ['fwbm' => $serviceCode, 'isControl' => true]);
        return $result;
    }

}