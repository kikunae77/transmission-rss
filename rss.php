#!/usr/bin/php
<?php
/**
 * Transmission simple RPC/0.1
 *
 * @author  fengqi <lyf362345@gmail.com>
 * @Modified_by ankit nigam @ankit__nigam
 * @Modified_by kikunae for WD My Cloud EX2
 * @link    https://github.com/fengqi/transmission-rss
 */
class Transmission
{
    private $server;
    private $user;
    private $password;
    private $session_id;

    /**
     * 입력 받은 값으로 초기값 설정
     *
     * @param $server
     * @param string $port
     * @param string $rpcPath
     * @param string $user
     * @param string $password
     *
     * @return \Transmission
     */
    public function __construct($server, $port = '9091', $rpcPath = '/transmission/rpc', $user = '', $password = '')
    {
        $this->server = $server.':'.$port.$rpcPath;
        $this->user = $user;
        $this->password = $password;
        $this->session_id = $this->getSessionId();
    }

    /**
     * seed 추가하기 이진데이터인 경우 base64로 인코드
     *
     * @param $url
     * @param bool $isEncode
     * @param array $options
     * @return mixed
     */
    public function add($url, $isEncode = false, $options = array())
    {
        return $this->request('torrent-add', array_merge($options, array(
            $isEncode ? 'metainfo' : 'filename' => $url,
        )));
    }

    /**
     * Transmission 서버 상태 가져오기
     *
     * @return mixed
     */
    public function status()
    {
        return $this->request("session-stats");
    }

    /**
     * Transmission session-id 가져오기
     *
     * @return string
     */
    public function getSessionId()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->server);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->password);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $content = curl_exec($ch);
        curl_close($ch);
        preg_match("/<code>(X-Transmission-Session-Id: .*)<\/code>/", $content, $content);
        $this->session_id = $content[1];

        return $this->session_id;
    }

    /**
     * rpc에 요청 보내기
     *
     * @param $method 요청 방식, $this->allowMethods 참조
     * @param array $arguments 인수
     * @return mixed
     */
    private function request($method, $arguments = array())
    {
        $data = array(
            'method' => $method,
            'arguments' => $arguments
        );

        $header = array(
            'Content-Type: application/json',
            'Authorization: Basic '.base64_encode(sprintf("%s:%s", $this->user, $this->password)),
            $this->session_id
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->server);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->user.':'.$this->password);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $content = curl_exec($ch);
        curl_close($ch);

        if (!$content)  $content = json_encode(array('result' => 'failed'));
        return $content;

    }

    /**
     * rss 에서 seed 목록 가져오기
     *
     * @param $rss
     * @return array
     */
    function getRssItems($rss)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT,"Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36");
        curl_setopt($ch, CURLOPT_COOKIE, "Cookie: CUPID=e4dfd575a42dc6c57d75cc885fa4a24a");
        curl_setopt($ch, CURLOPT_REFERER, "http://www.google.com/bot.html");
  		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  		curl_setopt($ch, CURLOPT_COOKIESESSION, true);

        $items = array();
        foreach ($rss as $link) {
            curl_setopt($ch, CURLOPT_URL, $link);
            $content = curl_exec($ch);
            if (!$content) continue;

            $xml = new DOMDocument();
            $xml->loadXML($content);
            $elements = $xml->getElementsByTagName('item');

            foreach ($elements as $item) {
                $link = $item->getElementsByTagName('enclosure')->item(0) != null ?
                        $item->getElementsByTagName('enclosure')->item(0)->getAttribute('url') :
                        $item->getElementsByTagName('link')->item(0)->nodeValue;

                $items[] = array(
                    'title' => $item->getElementsByTagName('title')->item(0)->nodeValue,
                    'link' => $link,
                );
            }
        }
        curl_close($ch);

        return $items;
    }
}

// 설정
$rss = array(
    'http://rss 주소 1',
    'http://rss 주소 2',
    'https://rss 주소 3'
);
$server = 'http://192.168.2.0';     // Transmission-rpc 연결 주소. 내부에서 실행하므로 그대로 두면 됨.
$port = 9091;                       // Transmission-rpc 포트. 변경하지 않았으면 그냥 두면 됨.
$rpcPath = '/transmission/rpc';     // rpc 연결 경로. 변경하지 않았으면 그냥 두면 됨.
$user = '';                         // Transmission setting.json 설정 파일에서 아이디, 암호 입력 하도록 설정했으면 해당 설정 내용 입력
$password = '';
$file = '/mnt/HD/HD_a2/added_list.log'; // 기존에 다운받은 파일을 다시 추가하지 않기 위한 이미 추가했던 seed 파일명을 기록할 파일의 경로 및 파일 이름
$pushbullet_script = '';
$trans = new Transmission($server, $port, $rpcPath, $user, $password);
$torrents = $trans->getRssItems($rss);

foreach ($torrents as $torrent) {
    $exists = 0;    // seed가 이미 기존에 추가됐던 것인지 여부를 표시하는 변수
    $search = $torrent['title'];
    
    $match = 0; // 정규식으로 원하는 단어를 포함하는 경우에만 추가되도록 단어를 포함했는지의 여부를 표시하는 변수
    if (preg_match('/(720p-NEXT|720p NEXT)/i', $search)) { // 릴그룹 및 동영상 해상도 판별 정규식
		    if (preg_match('/(런닝맨|라디오스타)/',$search)) { // 포함되길 원하는 단어로 ()안의 단어 수정, 여러개의 단어를 or 조건으로 판별시 '|'로 구분
		    	  $match = 1;
		    }
    }
    
    if ($match) {
        $lines = file($file);
        foreach($lines as $line){
          if(strpos($line, $search) !== false){
          $exists = 1;
          printf("%s: Torrent Already Downloaded / or in queue: %s\n", date('Y-m-d H:i:s'), $torrent['title']);
          }
        }
        if($exists == 0){
          $response = json_decode($trans->add($torrent['link']));
          if ($response->result == 'success') {
              printf("%s: success add torrent: %s\n", date('Y-m-d H:i:s'), $torrent['title']);
              $message = $torrent['title'].PHP_EOL;
              file_put_contents($file, $message, FILE_APPEND | LOCK_EX);
              # WD My Cloud EX2는 python을 지원하지 않으므로 pushbullet으로 메시지 보내는 부분 주석 처리
              # $mystring = system("python $pushbullet_script $message");
              # echo $mystring;
          }
        }
    }
}

