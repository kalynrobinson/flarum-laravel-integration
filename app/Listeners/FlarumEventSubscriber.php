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
    }
  
    /**
     * Create a new Flarum user with the given user info.
     *
     * @return void
     */
    private function onUserRegistration(User $user)
    {
        $user = $event->user;
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

        $this->sendRequest($data, self::METHOD, self::ENDPOINT);
    }
  
    /**
     * Activate a Flarum user.
     *
     * @return void
     */
    private function onUserActivation($event)
    {
        $user = $event->user;
        $endpoint = self::ENDPOINT . $user->id;
      
        $data = [
          'data' => [
            'attributes' => [
              'isActivated' => true
            ]
          ]
      ];

        $updated_user = sendRequest($endpoint, self::METHOD, $data);
    }

    private function onUserLogin($event)
    {
        
    }
  
    public function subscribe($events)
    {
        $events->listen(
            'Illuminate\Auth\Events\Login',
            'App\Listeners\FlarumEventSubscriber@onUserLogin'
        );
    
        $events->listen(
            'Illuminate\Auth\Events\Register',
            'App\Listeners\FlarumEventSubscriber@onUserRegistration'
        );
    
        $events->listen(
            'Illuminate\Auth\Events\Activate',
            'App\Listeners\FlarumEventSubscriber@onUserActivation'
        );
    }
  
    /**
     * Compose and send request to Flarum's API.
     *
     * @param String $path
     * @param String $method
     * @param JSON $data
     * @return void
     */
    private function sendRequest($path, $method, $data)
    {
        $data_string = json_encode($data);
        $ch = curl_init($this->flarum_url, $path);
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
