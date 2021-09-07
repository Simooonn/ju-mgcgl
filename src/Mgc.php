<?php

namespace JuMgcgl;

class Mgc
{
    /**
     * 敏感词过滤类，使用 DFA 算法。
     * 敏感词库数据结构为 Trie Tree。
     *
     */


    /**
     * 干扰字符集合。
     * ·-=/*+【】；‘’、，。~！@#￥%…&（）—{}：“|”《》？`1234567890[];'\,.!$^()_:"<>?※
     * @var array
     */
    private $_disturbList = ['·','-','=','/','*','+','【','】','；','‘','’','、','，','。','~','！','@','#','￥','%','…','&','（','）','—','{','}','：','“','|','”','《','》','？','`','0','1','2','3','4','5','6','7','8','9','[',']',';',"'",'\\',',','.','!','$','^','(',')','_',':','"','<','>','?','※'];

    /**
     * 默认替代字符。
     *
     * @var string
     */
    private $_escapeChar = '*';

    /**
     * 敏感词库，Trie Tree 格式的数组。
     *
     * @var array
     */
    private $_wordsTrieTree = [];
    private $_mgclx = ['bc','sq','sz','x'];//bc=博彩 sq=色情 sz=涉政 x=其他

    /**
     * @throws
     */
    public function __construct()
    {
        //加载初始敏感词库
        foreach ($this->_mgclx as $mgc_lx) {
            $words = array_merge($words ?? [],$this->sensitive_words($mgc_lx));
        }
        if(!empty($words)){
            $this->load($words);
        }
    }

    private function sensitive_words($file){
        $result = file_get_contents(realpath(__DIR__ . '/../data/'.$file.'.txt'));
        if(empty($result)){
            return [];
        }
        else{
            $result = array_unique(explode(PHP_EOL,str_replace(" ","",$result)));
            if(($key = array_search('',$result))){
                unset($result[$key]);
            }
            sort($result);
        }
        return $result;
    }
    
    public function makeTrieTree($sensitiveWords = []){
        if(!empty($sensitiveWords) && is_array($sensitiveWords)){
            foreach ($sensitiveWords as $word) {
                if ($word == '') break;
                $now_words = &$this->_wordsTrieTree;//才开始判断时，当前词就是整个树

                $word = strtolower($word);//不区分大小写
                $word_length = mb_strlen($word);
                for ($i = 0; $i < $word_length; $i++) {
                    $char = mb_substr($word, $i, 1);
                    if (!isset($now_words[$char])) {
                        $now_words[$char] = false;
                    }
                    $now_words = &$now_words[$char];//每个词判断后，当前词是对应的子级树
                }
            }
        }
    }

    /**
     * 从指定位置开始逐一扫描文本，如果扫描到敏感词，则返回敏感词长度。
     * 如果扫描的第一个字符不是敏感词头，则直接返回0。
     *
     * @param $text
     * @param $beginIndex
     * @param $length
     * @return int
     */
    private function _check($text, $beginIndex, $length)
    {
        $flag = false;
        $c_start = false;
        $word_length = 0;
        $trie_tree = &$this->_wordsTrieTree;
        for ($i = $beginIndex; $i < $length; $i++) {
            $word = mb_substr($text, $i, 1);

            // 检查是不是干扰字，是的话指针往前走一步。
            //以敏感词的词头开始和词尾结束，中间可包含干扰字符，但是开始和结束不含干扰字符
            if (in_array($word, $this->_disturbList) && $c_start === true && $flag === false) {
                $word_length++;
                continue;
            }
            if (!isset($trie_tree[$word])) { // 一旦发现没有匹配敏感词，则直接跳出。
                break;
            }

            $word_length++;
            if ($trie_tree[$word] !== false) { // 看看是否到达词尾。
                $trie_tree = &$trie_tree[$word]; // 往深层引用，继续检索。
                $c_start = true;
            } else {
                $flag = true;
                return $word_length;
            }
        }
        $flag || $word_length = 0; // 如果检查到最后一个字条还没有匹配到词尾，则当作没有匹配到。
        return $word_length;
    }



    /**
     * 获取解析后的数据，可用于缓存，以节约解析的性能。
     *
     * @return array|null
     */
    public function getTrieTree()
    {
        if($this->_wordsTrieTree != []){
            return $this->_wordsTrieTree;
        }
        else{
            return null;
        }
    }

    /**
     * 以数级的形式装载敏感词库，程序会自动将其转成 Trie Tree 格式。
     * 如果词库过大，这个过程会比较消耗性能，所以建议将结果缓存至 Redis 中，后续直接使用 WordBan::setTrieTree 方法来设置词库。
     *
     * @param array $sensitiveWords
     * @param int $trieTree
     * @return true 成功返回 true。
     * @throws
     */
    public function load($sensitiveWords = [])
    {
        if (!is_array($sensitiveWords) || empty($sensitiveWords)) {
            throw new \Exception('The loaded data is empty!');
        }
        $this->makeTrieTree($sensitiveWords);
        return true;
    }

    /**
     * 重置敏感词库。
     */
    public function reset()
    {
        $this->_wordsTrieTree = [];
    }



    /**
     * 增加干扰字符集合。
     *
     * @param array $disturbList
     */
    public function addDisturbList($disturbList = [])
    {
        $disturbList = is_array($disturbList) ? $disturbList:[];
        $this->_disturbList = array_merge($this->_disturbList,$disturbList);
    }

    /**
     * 重置干扰字符集合。
     *
     * @param array $disturbList
     */
    public function resetDisturbList($disturbList = [])
    {
        $this->_disturbList = is_array($disturbList) ? $disturbList:[];
    }

    /* 增加敏感词 */
    public function addMgc($mgc = []){
        if(empty($mgc)){
            throw new \Exception('添加敏感词不能为空');
        }
        $this->makeTrieTree($mgc);
        return true;
    }

    /**
     * 设置敏感词替换字符。
     *
     * @param $char
     */
    public function setEscapeChar($char)
    {
        $this->_escapeChar = $char;
    }


    /**
     * 扫描并返回检测到的敏感词。
     *
     * @param string $text 要扫描的文本。
     * @return array 返回敏感词组成的数组。
     */
    public function scan($text)
    {
        $scan_result = [];
        $ytext = $text;
        $text = strtolower($text);//不区分大小写
        $text_length = mb_strlen($text);
        for ($i = 0; $i < $text_length; $i++) {
            $word_length = $this->_check($text, $i, $text_length);

            if ($word_length > 0) {
                $word = mb_substr($ytext, $i, $word_length);
                $scan_result[] = $word;
                $i += $word_length - 1;
            }
        }
        $mgc = [];
        foreach ($scan_result as $value) {
            $mgc[] = str_replace($this->_disturbList,'',$value);
        }
        return ['words'=>$scan_result,'mgc'=>$mgc];
    }

    /**
     * 将文本中的敏感词使用替代字符替换，返回替换后的文本。
     *
     * @param string $text
     * @return mixed
     */
    public function escape_text($text)
    {
        $data = $this->scan($text);
        $sensitive_words = $data['words'];
        $replace_list = [];
        foreach ($sensitive_words as $value) {
            $replace_list[] = str_repeat($this->_escapeChar, mb_strlen($value));
        }
        return empty($sensitive_words) ? $text:str_ireplace($sensitive_words, $replace_list, $text);
    }
}