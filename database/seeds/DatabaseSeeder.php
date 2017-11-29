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
        // Create admin account
        factory(App\User::class, 1)->create([
            'name' => env('ADMIN_NAME'),
            'email' => env('ADMIN_EMAIL'),
            'password' => env('ADMIN_PASSWORD')
        ]);

        // Create users
        factory(App\User::class, 20)->create();

        // Creates initial API key
        DB::connection('flarum')->table('api_keys')->insert([
            'id' => env('FLARUM_API_KEY')
        ]);
    }
}
