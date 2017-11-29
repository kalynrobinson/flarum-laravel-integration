# Flarum-Laravel Integration
Example and guide to integrating Flarum with Laravel's basic auth.

## Credits
* [Wogan](https://wogan.blog/2017/02/12/integrating-laravel-and-flarum/) for the workflow

## Tutorial

### Prerequisites
* An Apache/PHP/MySQL stack. See WAMP, LAMP, and XAMPP for all-in-one stack managers.
* [Composer](https://getcomposer.org/)

### Database Setup
* Create databases for your Laravel and Flarum sites 
* If you have already have databases setup, skip this step
  ```SHELL
  mysqli -u <username> -p
    > create database demo;
    > create database demo_laravel;
    > quit
  ```

### Flarum Setup
* Install Flarum as normal
  ```SHELL
  cd path/to/your/project
  mkdir flarum
  cd flarum
  composer create-project flarum/flarum . --stability=beta
  composer install
  ```
* If installation fails, make sure you meet Flarum's system requirements and that the necessary extensions are enabled in your php.ini
* Start up your Apache web server and configure Flarum to your liking
* Disable user sign-up in the admin panel

### Laravel Setup
* Create a new Laravel project and generate basic user authentication views
```SHELL
cd path/to/your/project
laravel new demo
cd demo
php artisan make:auth
```
* Edit the `.env` file with your MySQL credentials and the name of your Laravel database (e.g. `demo`)
* Run your migrations and start up the server to make sure everything works as expected, e.g. registration, login, and logout
```SHELL
php artisan migrate
php artisan serve
```

### API Key
* Generate a 40 character string to use as an API key
* Add the API key and the credentials to your `.env`
```
FLARUM_API_KEY=jy8HbVSSh0BjGFTnM4mlN9WVPEu31YbZEFkBAu9E
```

### **OPTIONAL** Seeding the API Key
* **If you skip this step, manually insert the above API key into your Flarum `api_keys` table**
* I've chosen to insert the API key into my Flarum `api_keys` table when seeding my Laravel app
* This is not mandatory (and I doubt this is a best practice, but it was convenient for my use case)
* Add your Flarum database credentials to `.env`
```
FLARUM_DATABASE=demo_flarum
FLARUM_USERNAME=root
FLARUM_PASSWORD=secret
```
* Add a Flarum database connection to `config/database.php`, using the .env properties added above
```PHP
    'default' => env('DB_CONNECTION', 'mysql'),
    
    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
        
        // Flarum database, used only for setup
        'flarum' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('FLARUM_DATABASE', 'forge'),
            'username' => env('FLARUM_USERNAME', 'forge'),
            'password' => env('FLARUM_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],

    ],
```
* Make sure your default connection is set to your MySQL database
* Modify `seeds/DatabaseSeeder.php` to insert your API key
```PHP
<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create initial API key
        DB::connection('flarum')->table('api_keys')->insert([
            'id' => config('flarum.api_key')
        ]);
    }
}

```

### **OPTIONAL** Setup the Queue
* While a queue is not strictly necessary, it's good practice for when you need to chain jobs together—in my case, I want to activate a Flarum user immediately after registration, but the registration request must resolve first
* **If you skip this step, remove `implements ShouldQueue` from the following event subscriber**
* Generate a queue table, migrate the changes, and start up the queue worker
```CMD
php artisan queue:table
php artisan migrate
php artisan queue:work
```
* Leave the queue worker running in the background

### Setup Event Subscriber
* Laravel's basic authentication fires off a handful of convenient events that we can listen for, namely `Login`, `Logout`, and `Registered`
* If you are using a different authentication setup, you may have to implement these events yourself and/or replace the event references in the following code samples
* Create an event subscriber and stub out the initial listeners
```PHP
<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class FlarumEventSubscriber implements ShouldQueue
{
    private $api_url;
    private $api_key;
    private $root;
    private $password_token;
    private const REMEMBER_ME_KEY = 'flarum_remember';
    private const SESSION_KEY = 'flarum_session';
    private const LIFETIME_IN_SECONDS = 99999999;
  
    public $queue = 'flarum';

    public function __construct()
    {
        $this->api_url = config('flarum.url');
        $this->api_key = config('flarum.api_key');
        $this->root = config('flarum.root');
    }
  
    public function onUserRegistration($event)
    {
    }
    
    public function onUserLogin($event)
    {
    }
    
    public function onUserLogout($event)
    {
    }
  
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
    }
```
* Note FlarumEventSubscriber#subscribe—this registers your event listeners
* Register this subscriber with the event provider `/providers/EventServiceProvider`
```PHP
protected $subscribe = [
    'App\Listeners\FlarumEventSubscriber',
];
```

### On User Registration
* When a user registers, we want to use its credentials to create a matching user in our Flarum database
* We can do this by POSTing a request to Flarum's JSON API, which, while mostly undocumented, is straightforward enough
```PHP
public function onUserRegistration($event)
{
    $user = $event->user;
    $method = 'POST';
    $endpoint = '/api/users';

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
```
* **This request should only be sent over HTTPS**; it includes the user's hashed password

### On User Login
* When a user logs in, we want to GET a Flarum session token and store it as a cookie
```PHP
public function onUserLogin($event)
{
    $user = $event->user;
    $response = $this->authenticate($user->name, $user->password);
    $token = $response['token'] ?: '';
    $this->setRememberMeCookie($token);
}

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
```

### On User Logout
* When a user logs out, we want to remove the cookie added during login, as well as the Flarum session cookie
```PHP
public function onUserLogout($event)
{
    $this->removeRememberMeCookie();
    $this->setCookie('flarum_session', '', time() - 10);
    $this->setCookie('flarum_session', '', time() - 10, '/flarum');
}
```

## Done!
* Basic registration, login, and logout should be synced across your Laraval and Flarum sites
* If you want to automatically activate the Flarum users, you can queue an activation request immediately after registration or you can create your own event and event listener
