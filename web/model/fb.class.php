<?php
class fb {
    private static $instance = null;
    private $registry;
    private $fb;


    public static function getInstance($arg) {
        if (!self::$instance instanceof self) {
            self::$instance = new self($arg);
        }
        return self::$instance;
    }

    public function __clone() {
        trigger_error('Clone is not allowed.', E_USER_ERROR);
    }

    public function __wakeup() {
        trigger_error('Deserializing is not allowed.', E_USER_ERROR);
    }

    private function __construct($registry){
        $permissions = ['user_birthday', 'user_games_activity', 'user_likes', 'user_location', 'email', 'publish_actions'];
        require_once __SITE_PATH.'/includes/facebook-php-sdk-v4-5.0.0/src/Facebook/autoload.php';
        $fb = new Facebook\Facebook([
            'app_id' => APP_ID,
            'app_secret' => APP_SECRET,
            'default_graph_version' => GRAPH_VERSION
        ]);
        $helper = $fb->getRedirectLoginHelper();
        if(empty($_SESSION['facebook_access_token'])){
            $loginUrl = $helper->getLoginUrl(BASE_URL.'scripts/login.php', $permissions);
            header('Location: '.$loginUrl);
            die();
        }else{
            if($this->tokenIsValid()){
                $fb->setDefaultAccessToken($_SESSION['facebook_access_token']);   
            }else{
                $loginUrl = $helper->getLoginUrl(BASE_URL.'scripts/login.php', $permissions);
                header('Location: '.$loginUrl);
                die();
            }
        }

        $this->fb = $fb;
        $this->registry = $registry;
        $registry->is_admin = $this->is_admin($this->getCurrentUserId());
        $registry->db->insertUserInfo($this->collectUserInfo($this->getCurrentUserId()));
    }

    private function tokenIsValid(){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://graph.facebook.com/me?access_token='.$_SESSION['facebook_access_token']);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $return = curl_exec($curl);
        $return = json_decode($return, true);
        curl_close($curl);
        return !isset($return['error']);
    }

    public function get_roles(){
        try{
            $response = $this->fb->get('/'.APP_ID.'/roles', APP_ACCESS_TOKEN);
        }catch(Facebook\Exceptions\FacebookResponseException $e){
            echo $e->getMessage();
            exit;
        }
        return $response->getGraphEdge()->asArray();
    }

    public function getCurrentUserId(){
        try{
            $response = $this->fb->get('/me')->getGraphUser()->AsArray();
        }catch(Facebook\Exceptions\FacebookResponseException $e){
            echo $e->getMessage();
            exit;
        }
        return $response['id'];
    }

    public function is_admin($id_user){
        $admins = $this->get_roles();
        foreach($admins as $admin){
            if($admin['role'] == 'administrators'){
                if($admin['user'] == $id_user){
                    return true;
                }
            }
        }
        return false;
    }

    private function collectUserInfo($id_user){
        $return = [];
                
        if($this->registry->is_admin)
            $return['is_admin'] = 1;
        else
            $return['is_admin'] = 0;
        
        try{
            $user_data = $this->fb->get('/'.$id_user.'?fields=first_name,last_name,birthday,gender,location,devices,email')->getGraphUser()->AsArray();
        }catch(Facebook\Exceptions\FacebookResponseException $e){
            echo $e->getMessage();
            exit;
        }
        
        try{
            $user_likes = $this->fb->get('/'.$id_user.'?fields=books{name},music{name},favorite_athletes,scores{application{name}}')->getGraphUser()->AsArray();
        }catch(Facebook\Exceptions\FacebookResponseException $e){
            echo $e->getMessage();
            exit;
        }
        
        foreach($user_data as $key => $data){
            if($data instanceof DateTime)
            {
                $return[$key] = $data->format('Y-m-d');
            }
            elseif(is_array($data))
            {
                foreach($data as $k => $v)
                {
                    if(is_array($v))
                    {
                        foreach($v as $cle => $val)
                        {
                            if(isset($return[$cle]))
                                $return[$key] .= '|'.$val; 
                            else
                                $return[$key] = $val;
                        }
                    }
                    elseif($k != 'id')
                        $return[$key] = $v;
                }
            }
            elseif($key != 'id')
                $return[$key] = $data;
        }
        // M'en veux pas Younes je sais que c'est moche <3
        foreach($user_likes as $k => $v)
        {
            if(is_array($v))
            {
                foreach($v as $kk => $vv)
                {
                    foreach($vv as $kkk => $vvv)
                    {
                        if(is_array($vvv))
                        {
                            foreach($vvv as $kkkk => $vvvv)
                            {
                                if($kkk != 'user' && $kkkk != 'id')
                                {
                                    if(isset($return[$kkk]))
                                        $return[$kkk] .= '|'.$vvvv; 
                                    else
                                        $return[$kkk] = $vvvv;
                                }
                            }
                        }
                        else
                        {
                            if($kkk != 'id')
                            {
                                if(isset($return[$k]))
                                    $return[$k] .= '|'.$vvv; 
                                else
                                    $return[$k] = $vvv;
                            }
                        }
                    }
                }
            }
        }
        
        $return['id_fb'] = $id_user;
        
        /*var_dump($return);
        echo "<br><hr><br>";
        var_dump($user_data);*/
        
        return $return;
    }
    
}