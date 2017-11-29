<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class FlarumEventSubscriber implements ShouldQueue
{

    /**
     * Flarum API URL
     */
    private $api_url;
    
    /**
     * Flarum API key
     */
    private $api_key;

    /**
     * Root domain of site
     */
    private $root;
    
    /**
     * Token used to salt Flarum password hash
     */
    private $password_token;

    /**
     * Cookie key to store Flarum token
     */
    private const REMEMBER_ME_KEY = 'flarum_remember';

    /**
     * Cookie key to store Flarum token
     */
    private const SESSION_KEY = 'flarum_session';

    private const LIFETIME_IN_SECONDS = 99999999;
  
    /**
     * The name of the queue that Flarum-related jobs should be sent to
     *
     * @var string|null
     */
    public $queue = 'flarum';

    /**
     * Fetch API information from Flarum config.
     */
    public function __construct()
    {
        $this->api_url = config('flarum.url');
        $this->api_key = config('flarum.api_key');
        $this->root = config('flarum.root');
    }
  
    /**
     * Create a new Flarum user with the given user info.
     * Hashes username and salts with token to use as Flarum password.
     *
     * @return void
     */
    public function onUserRegistration($event)
    {
        $user = $event->user;
        $method = 'POST';
        $endpoint = '/api/users';

        var_dump($user->password);

        $data = [
          'data' => [
              'attributes' => [
                  'id'       => $user->id,
                  'username' => $user->name,
                  'password' => $user->password,
                  'email'    => $user->email
              ]
          ]
      ];

        $this->sendRequest($endpoint, $method, $data);
    }
  
    /**
     * Activate a Flarum user.
     *
     * @return void
     */
    public function onUserActivation($event)
    {
        $user = $event->user;
        $method = 'POST';
        $endpoint = "/api/users/{$user->id}";
      
        $data = [
            'data' => [
                    'attributes' => [
                    'isActivated' => true
                ]
            ]
        ];

        $updated_user = $this->sendRequest($endpoint, $method, $data);
    }
    
    /**
     * Fetch auth token from Flarum and set it as a cookie.
     */
    public function onUserLogin($event)
    {
        $user = $event->user;
        $response = $this->authenticate($user->name, $user->password);
        $token = $response['token'] ?: '';
        $this->setRememberMeCookie($token);
    }
    
    /**
     * Clear Flarum session. 
     * Must remove cookies on both domain root and Flarum directory.
     */
    public function onUserLogout($event)
    {
        $this->removeRememberMeCookie();
        $this->setCookie('flarum_session', '', time() - 10);
        $this->setCookie('flarum_session', '', time() - 10, '/flarum');
    }
  
    /**
     * Subscribe to user auth events.
     */
    public function subscribe($events)
    {
        $events->listen(
            'Illuminate\Auth\Events\Registered',
            'App\Listeners\FlarumEventSubscriber@onUserRegistration'
        );

        $events->listen(
            'Illuminate\Auth\Events\Login',
            'App\Listeners\FlarumEventSubscriber@onUserLogin'
        );
        
        $events->listen(
            'Illuminate\Auth\Events\Logout',
            'App\Listeners\FlarumEventSubscriber@onUserLogout'
        );
    
        $events->listen(
            'App\Events\Activate',
            'App\Listeners\FlarumEventSubscriber@onUserActivation'
        );
    }

    /**
     * Attempt to fetch a session token from Flarum's API.
     */
    private function authenticate($id, $password)
    {
        $endpoint = '/api/token';
        $method = 'POST';

        $data = [
            'identification' => $id,
            'password' => $password,
            'lifetime' => self::LIFETIME_IN_SECONDS
        ];
        
        return $this->sendRequest($endpoint, $method, $data);
    }

    private function setRememberMeCookie($token)
    {
        $this->setCookie(self::REMEMBER_ME_KEY, $token, time() + self::LIFETIME_IN_SECONDS);
    }

    private function removeRememberMeCookie()
    {
        $this->setCookie(self::REMEMBER_ME_KEY, '', time() - 10);
    }

    private function setCookie($key, $token, $time, $path = '/')
    {
        setcookie($key, $token, $time, $path, $this->root);
    }
  
    /**
     * Compose and send request to Flarum's API.
     *
     * @param String $endpoint
     * @param String $method
     * @param JSON   $data
     * @return void
     */
    private function sendRequest($endpoint, $method, $data)
    {
        $data_string = json_encode($data);
        $ch = curl_init($this->api_url . $endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
          $ch,
          CURLOPT_HTTPHEADER,
          [
              'Content-Type: application/json',
              'Content-Length: ' . strlen($data_string),
              'Authorization: Token ' . $this->api_key . '; userId=1',
          ]
        );
        $result = curl_exec($ch);
        return json_decode($result, true);
    }
}
