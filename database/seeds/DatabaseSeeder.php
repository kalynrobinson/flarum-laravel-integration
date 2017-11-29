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
            'password' => bcrypt(env('ADMIN_PASSWORD'))
        ]);

        // Creates initial API key
        DB::connection('flarum')->table('api_keys')->insert([
            'id' => config('flarum.api_key')
        ]);
    }
}
