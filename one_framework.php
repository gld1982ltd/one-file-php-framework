<?php
/**
 * One Framefork
 * @author Julio Cesar Martin
 * @juliomatcom - juliomatcom@gmail.com
 * Feel free to contact me any time
 * http://oneframework.julces.com/
 *
 * Controllers must be in APP_DIR/controllers
 * Views must be in APP_DIR/views
 * Translations must be in APP_DIR/translations
 * Assets must be in APP_DIR/assets/
 */
class OneFramework{
    protected $request;
    protected $db;
    protected $routes = array();
    //set this value to True if you want to get access to translations
    protected $translate = false;
    //here the value of the locale requested by url (segment 1)
    protected $locale = null;
    protected $locales = ['es','en','fr'];
    protected $translations = array();

    public function __construct(){
        $this->defineConstants();
        $this->buildRequest();
        $this->loadTrans();
    }

    public function listen(){
        $slugs = array();
        foreach($this->routes as $route)
            if($func = $this->processUri($route,$slugs)){
                //call callback function with params in slugs
                call_user_func_array($func,$slugs);
            }

        $this->error('Not route found');
    }

    /**
     * Connect if needed, retrieve the database connection
     * @return mysqli
     */
    public function getDB(){
        $this->db = $this->db ? $this->db : new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
        if ($this->db->connect_errno) {
            $this->error("Error connecting to database: ".DB_DATABASE."<br/> Info:". $this->db->connect_error);
        }
        return $this->db;
    }
    private function defineConstants(){
        define('APP_DIR',__DIR__);
        define('CONTROLLERS_ROUTE',APP_DIR.'/controllers/');
        define('VIEWS_ROUTE',APP_DIR.'/views/');
        define('TRANSLATION_DIR',APP_DIR.'/translations/');
        define('DB_HOST','127.0.0.1');
        define('DB_USER','root');
        define('DB_PASSWORD','');
        define('DB_DATABASE','');
        define('APP_NAME','');
    }

    private function buildRequest(){
        $this->request = new stdClass();
        $this->request->query = $_GET;
        $this->request->request = $_POST;
        $this->request->server = $_SERVER;
        $this->request->cookie = $_COOKIE;
    }

    /**
     * Create a route to the app
     * @param $uri
     * @return string new URL with locale if needed
     */
    public function getRoute($uri){
        return $this->translate ? ((APP_NAME != '') ? ('/'.APP_NAME.'/'.$this->locale.$uri) : ('/'.$this->locale.$uri) ):
            ((APP_NAME != '') ?('/'.APP_NAME.$uri) : $uri);
    }


    /**
     * Process the request and Return a Response
     * @param  uri key for the Route example: /book/{number}/edit
     * @param  action
     */
    public function get($uri, $function){
        $routeKey = $this->translate ? ('/{_locale}'.$uri) : $uri;

        $route = new stdClass();
        $route->route = $routeKey;
        $route->function = $function;

        //save route and function
        $this->routes[] = $route;
    }

    //REGION PRIVATE FUNCTIONS FOR THE APP CORE
    private function processUri($route,&$slugs = array()){
        $uri = isset($this->request->server['REQUEST_URI']) ? $this->request->server['REQUEST_URI'] : '/' ;
        $func = $this->matchUriWithRoute($uri,$route,$slugs);
        return $func ? $func : false;
    }

    private function matchUriWithRoute($uri,$route,&$slugs){
        $uri_segments = preg_split('/[\/]+/',$uri,null,PREG_SPLIT_NO_EMPTY);
        //redirect if no locale is set
        if(count($uri_segments) == 0 && $this->translate){
            APP_NAME!= '' ?   $this->redirect("/".APP_NAME."/{$this->locales[0]}/") : $this->redirect("/{$this->locales[0]}/");
        }
        $route_segments = preg_split('/[\/]+/',$route->route,null,PREG_SPLIT_NO_EMPTY);

        if($this->compareSegments($uri_segments,$route_segments,$slugs)){
            //route matched
            if($this->translate) $this->locale = $this->getSegment(0); //save locale
            return $route->function; //Object route
        }
        return false;
    }

    /**
     * If locale is set return locale from values in array $locales
     * @return bool|null
     */
    public function getLocale(){
        return $this->locale ? $this->locale : false;
    }

    /**
     * Get request Object
     * @return Request class
     */
    public function getRequest(){
        return $this->request;
    }


    /**
     * Translate a string
     * @param string $key
     * @param bool $lang Language
     * @return string|exception Error
     */
    public function trans($key,$lang = false){
        if($this->translate){
            if(!$lang) $lang= $this->locale;
            return isset($this->translations[$lang][$key]) ? $this->translations[$lang][$key] : 'translation_'.$key;
        }
        else return $this->error('Translation disabled');
    }

    /**
     * Load translations from disk
     */
    private  function loadTrans(){
        if($this->translate){//include translations
            foreach($this->locales as $locale){
                $filename = 'trans.'.$locale.'.txt';

                if(file_exists(TRANSLATION_DIR.$filename)){
                    $text = file_get_contents(TRANSLATION_DIR.$filename);
                    $lines = explode(PHP_EOL,$text);
                    $arr = array();

                    foreach($lines as $line){//add pairs
                        $par = explode(':',$line);
                        if(!empty($par[0]) && !empty($par[1]))
                            $arr[utf8_decode($par[0])] = trim($par[1]); //save
                    }

                    $this->translations[$locale] = $arr;
                }
            }
        }
    }

    /**
     * Returns al the translations loaded
     * @return array
     */
    public function getTranslations(){
        return $this->translations;
    }

    public function Redirect($href){
        echo header('Location: '.$href);
        exit;
    }

    /**
     * Return new Response
     * @param $view_filename source to the view file
     * @param array $vars for the View
     */
    public function Response($view_filename,$vars = array()){
        $data = $vars;
        include_once(VIEWS_ROUTE."$view_filename");
        exit;
    }

    /**  Match 2 uris
     * @param $uri_segments
     * @param $route_segments
     * @return bool
     */
    private function CompareSegments($uri_segments,$route_segments,&$slugs){

        if(count($uri_segments) != count($route_segments)) return false;

        foreach($uri_segments as $segment_index=>$segment){

            $segment_route = $route_segments[$segment_index];
            //different segments must be an {slug}
            if(preg_match('/^{[^\/]*}$/',$segment_route) && $segment_route!='{_locale}')
                $slugs[] = $segment;//save slug key => value
            else if($segment_route != $segment && preg_match('/^{[^\/]*}$/',$segment_route) != 1) return false;

        }

        //match with every segment
        return true;
    }

    private  function getSegment($segment_number){
        $uri = isset($this->request->server['REQUEST_URI']) ? $this->request->server['REQUEST_URI'] : '/' ;
        $uri_segments = preg_split('/[\/]+/',$uri,null,PREG_SPLIT_NO_EMPTY);

        return isset($uri_segments[$segment_number]) ? $uri_segments[$segment_number] : false;
    }


    /**
     * Show frameworks error
     * @param string $msg
     * @throws Exception
     */
    private function error($msg=''){
        echo
        "<h1>Error</h1>
         <p>$msg</p>
         <br/>
         <h2>Trace:</h2>"
        ;
        throw new Exception();
    }


}