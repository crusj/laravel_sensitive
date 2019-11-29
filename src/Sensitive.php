<?php
/**
 * author crusj
 * date   2019/11/29 10:34 下午
 */


namespace Crusj\Sensitive;


use DfaFilter\SensitiveHelper;
use Predis\Client;

class Sensitive
{
    /**
     * 敏感词
     * @var array
     */
    private $sensitiveWords = [];
    /**
     * 过滤内容
     * @var string
     */
    private $content = "";

    /**
     * @var Client
     */
    private $redis;

    /**
     * @var SensitiveHelper;
     */
    private $sensitiveHelper;
    /**
     * @var array
     */
    private $delimiter = [',', '，', '_', '-','+'];

    public function __construct(Client $PredisClient, array &$sensitiveWords = [], string $content = "", array $delimiter = [])
    {
        $this->redis = new Redis($PredisClient);
        $this->sensitiveHelper = SensitiveHelper::init();

        $this->sensitiveWords = $sensitiveWords;
        $this->content = $content;
        if (!empty($delimiter)) {
            $this->delimiter = $delimiter;
        }
    }

    /**
     * 设置敏感词
     * @param array $sensitiveWords
     */
    public function setSensitiveWords(array &$sensitiveWords)
    {
        //删除缓存
        $this->delCacheSensitiveWords();
        $this->sensitiveWords = $sensitiveWords;
        foreach ($this->sensitiveWords as $sensitiveWord) {
            $splitWords = $this->splitWords($this->delimiter, [$sensitiveWord]);
            if (count($splitWords) == 1) {//单个敏感词
                $this->setPushSingleWord($splitWords[0]);
            } else {
                foreach ($splitWords as $splitWord) {
                    $this->setPushCombineWord($splitWord);
                }
                $this->setPushCombine($splitWords);
            }
        }
    }

    /**
     * 设置敏感词
     * @param string $content
     */
    public function setContent(string $content)
    {
        $this->content = $content;
    }

    private function delCacheSensitiveWords()
    {
        $this->redis->del('sensitive_single_words');
        $this->redis->del('sensitive_combine_words');
        $this->redis->del('sensitive_combine');
    }

    public function analyzeSensitiveWords(): array
    {
        list($a, $b) = $this->getContentSensitiveWords();

        //敏感词的种类[单词数=>[对应的组合]]
        $sensitiveKinds = [];
        foreach ($this->redis->setGetAll('sensitive_combine') as $item) {
            $items = explode('_', $item);
            $itemCount = count($items);
            if (isset($sensitiveKinds[$itemCount])) {
                $sensitiveKinds[$itemCount][$item] = true;//hash,数组查找会变慢
            } else {
                $sensitiveKinds[$itemCount] = [$item => true];
            }
        }
        $combineSensitiveCountAndValue = [];
        //从内容中只找存在多词数量的组合
        foreach (array_keys($sensitiveKinds) as $count) {
            $combineSensitiveCountAndValue[$count] = [];
            $this->getCombine($count, $b, [], $combineSensitiveCountAndValue[$count]);
        }
        $combineBadWords = [];
        foreach ($combineSensitiveCountAndValue as $key => $words) {
            if (!empty($words)) {
                foreach ($words as $word) {
                    if (isset($sensitiveKinds[$key][$word])) {
                        array_push($combineBadWords,$word);
                    }
                }
            }
        }
        return [
            'single'  => $a,
            'combine' => $combineBadWords
        ];


    }

    /**
     * 根据分隔符拆分组合的敏感词
     * @param array $delimiter
     * @param array $words
     * @return array
     */
    private function splitWords(array $delimiter, array $words): array
    {
        if (empty($delimiter) || count($words) > 1) {
            return $words;
        }
        $sp = array_pop($delimiter);
        $words = explode($sp, $words[0]);
        return $this->splitWords($delimiter, $words);
    }

    /**
     * 单个敏感词
     * @param string $word
     */
    private function setPushSingleWord(string $word)
    {
        $this->redis->setPush('sensitive_single_word', $word);
    }

    /**
     * 存在组合的敏感词
     * @param string $word
     */
    private function setPushCombineWord(string $word)
    {
        $this->redis->setPush('sensitive_combine_words', $word);
    }

    /**
     * 敏感词组合
     * @param array $words
     */
    private function setPushCombine(array $words)
    {
        sort($words);
        $this->redis->setPush('sensitive_combine', join('_', $words));
    }

    private function getContentSensitiveWords()
    {
        //单词
        $this->sensitiveHelper->setTree($this->redis->setGetAll('sensitive_single_word'));
        $contentS = $this->sensitiveHelper->getBadWord($this->content);
        //多词
        $this->sensitiveHelper->setTree($this->redis->setGetAll('sensitive_combine_words'));
        $contentC = $this->sensitiveHelper->getBadWord($this->content);
        return [$contentS, $contentC];
    }

    /**
     * 获取的有序组合
     * @param int $num 需要组合的数量
     * @param array $words 单词源
     * @param array $tmp 临时变量
     * @param array $combine 组合保存的数组
     */
    private function getCombine(int $num, array $words, array $tmp, array &$combine)
    {
        $wordsCount = count($words);
        if ($num > $wordsCount || $wordsCount == 0) {
            return;
        }
        if ($num == 0) {
            sort($tmp);
            $combine[] = join('_', $tmp);
            return;
        }
        if ($num == $wordsCount) {
            $tmp = array_merge($tmp, $words);
            sort($tmp);
            $combine[] = join('_', $tmp);
            return;
        }
        $numDecrease = $num - 1;
        $this->getCombine($numDecrease, array_slice($words, 1), array_merge($tmp, [$words[0]]), $combine);
        $this->getCombine($num, array_slice($words, 1), $tmp, $combine);
    }
}
