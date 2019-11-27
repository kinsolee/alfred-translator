<?php

use Alfred\Workflows\Workflow;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Joli\JoliNotif\Notification;
use Joli\JoliNotif\NotifierFactory;
use SleekDB\SleekDB;
use Sources\BaseSource;

class TranslatorWorkflow extends Workflow
{
    private $source;
    private $query;
    /** @var BaseSource */
    private $provider;

    public $errorMsg = '';

    public function __construct()
    {
        $this->source = getenv('SOURCE') ?: 'youdao';
        $provider = "Sources\\" . ucfirst($this->source) . 'Source';
        if (is_subclass_of($provider, BaseSource::class)) {
            $this->provider = new $provider($this);
        }
        if (!$this->provider) {
            die($this->basicOutput("来源无效", "请设置有效的查询源"));
        }
    }

    public function query($query)
    {
        $this->query = $query;

        $this->provider->translate($this->query);

        if ($this->errorMsg !== '') {
            return $this->basicOutput("翻译失败", $this->errorMsg);
        }

        if (empty($this->results)) {
            return $this->basicOutput("无结果", "请换一个文本再试");
        }

        return $this->output();
    }

    public function saveToAnki($query)
    {

        $client = new Client(['timeout' => 1]);

        $ankiServerUrl = getenv('ANKI_SERVER') ?: 'http://localhost:8765';

        $deckName = getenv('ANKI_DECK') ?: '';
        $modelName = getenv('ANKI_MODEL') ?: '';
        $fieldName = getenv('ANKI_FIELD') ?: '';

        if (!($deckName && $modelName && $fieldName)) {
            $this->notify('添加失败', '请设置正确的Anki配置');
            return;
        }

        try {

            $response = $client->post($ankiServerUrl, [
                'json' => [
                    'action' => 'addNote',
                    'version' => 6,
                    'params' => ['note' => [
                        'deckName' => $deckName,
                        'modelName' => $modelName,
                        'fields' => [
                            $fieldName => $query
                        ],
                        'tags' => []
                    ]]
                ]
            ]);
        } catch (ConnectException $e) {
            $this->notify('添加失败', 'Anki服务器不可用，请打开Anki并安装AnkiConnect插件');
            return;
        }

        $result = \GuzzleHttp\json_decode($response->getBody()->getContents());

        if ($result->error != null) {
            $this->notify('添加失败', $result->error);
            return;
        }

        $this->notify('添加成功', "$query 已被添加到 [$deckName] [$modelName] !");
    }

    public function speech($query)
    {
        $this->query = $query;

        $this->provider->speech($this->query);

    }


    public function basicOutput($title, $subtitle, $arg = null)
    {
        $this->basicResult($title, $subtitle, $arg);
        return $this->output();
    }

    public function basicResult($title, $subtitle, $arg = null)
    {
        $arg = $arg ?: $title;
        $subtitle = $subtitle ?: $this->query;

        $result = $this->result()
            ->title($title)
            ->subtitle($subtitle)
            ->arg($arg);
        return $result;
    }

    private function notify($title, $body)
    {
        dump(__DIR__ . '/assets/imgs/icon.png');
        $notifier = NotifierFactory::create();
        $notification = (new Notification())
            ->setTitle($title)
            ->setBody($body)
            ->setIcon(__DIR__ . '/assets/imgs/icon.png');

        $notifier->send($notification);
    }
}