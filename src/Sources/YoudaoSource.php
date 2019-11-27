<?php

namespace Sources;

use GuzzleHttp\Client;

class YoudaoSource extends BaseSource
{

    private $isChinese;
    private $query;

    protected $icon = __DIR__ . '/../assets/imgs/icon.png';

    public function translate($query)
    {

        $client = new Client(['timeout' => 5]);

        $this->query = $query;
        $this->workflow->variable('query', $query);

        $this->isChinese = $this->isChinese($query);

        $url = $this->getOpenQueryUrl($query);
        $response = $client->get($url)->getBody()->getContents();
        $result = json_decode($response, TRUE);

        if (empty($result) || (int)$result['errorCode'] !== 0) {
            $this->workflow->errorMsg = "错误代码：" . $result['errorCode'];
            return;
        }

        $this->dealWithResult($result);
    }


    public function speech($query)
    {
        $speech = getenv('speech') ?? '';
        if (!$speech) {
            $this->addResult('播放失败', '无有效音频');
        }

        $tempFile = sys_get_temp_dir() . '/youdao-' . getenv('query');
        $cmd = "curl '$speech' -H 'Connection: keep-alive' -H 'Pragma: no-cache' -H 'Cache-Control: no-cache' -H 'Accept-Encoding: identity;q=1, *;q=0' -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.108 Safari/537.36' -H 'Accept: */*' -H 'Referer: http://openapi.youdao.com/ttsapi' -H 'Accept-Language: zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7' -H 'Range: bytes=0-' --compressed --insecure -o $tempFile && afplay $tempFile && rm $tempFile";
        exec($cmd);
    }

    private function getOpenQueryUrl($query)
    {
        $appKey = getenv('APPKEY') ?: '';
        $secret = getenv('SECRET') ?: '';

        $api = 'https://openapi.youdao.com/api?';
        $params['q'] = $query;
        $params['appKey'] = $appKey;
        $params['secret'] = $secret;
        $params['salt'] = strval(rand(1, 100000));

        $params['from'] = getenv('FROM') ?: ($this->isChinese ? 'zh-CHS' : 'en');
        $params['to'] = getenv('TO') ?: ($this->isChinese ? 'en' : 'zh-CHS');

        $params['sign'] = md5($appKey . $query . $params['salt'] . $secret);
        return $api . http_build_query($params);
    }

    private function dealWithResult($result)
    {
        $translation = implode('；', $result['translation']);
        $this->addResult($translation, '[翻译结果]', $result['query'])
            ->variables(['text' => $translation])
            ->uid('basic');

        $basic = $result['basic'] ?? [];
        //放入环境变量以便output使用
        $this->workflow->variable('speech', $this->isChinese ? $result['tSpeakUrl'] : $result['speakUrl']);

        if (!empty($basic)) {


            $phonetic = "[英] {$basic['uk-phonetic']} [美] {$basic['us-phonetic']}";

            $this->addResult($phonetic, '发音', $result['query'])
                ->variables(['text' => $phonetic])
                ->copy($phonetic)
                ->largetype($phonetic);

            foreach ($basic['explains'] as $item) {
                $this->addResult($item, '[基本词典]', $result['query'])
                    ->uid('basic')
                    ->copy($item)
                    ->variables(['text' => $item])
                    ->largetype($item);
            }

            $wfs = $basic['wfs'] ?? [];

            if (!empty($wfs)) {
                $wfsString = implode(';', array_map(function ($wf) {
                    return $wf['wf']['name'] . ' ' . $wf['wf']['value'];
                }, $wfs));
                $this->addResult($wfsString, '单词变形', $result['query'])
                    ->variables(['text' => $wfsString])
                    ->uid('addition')
                    ->copy($wfsString)
                    ->largetype($wfsString);
            }

        }

        if (!empty($result['web'])) {
            $this->addResult(
                implode(";", $result['web'][0]['value']),
                '网络释义',
                $result['query'])
                ->copy($result['web'][0]['key'])
                ->largetype($result['web'][0]['key']);
        }
    }

    private function isChinese($query)
    {
        return preg_match("/[\x7f-\xff]/", $query);
    }

}