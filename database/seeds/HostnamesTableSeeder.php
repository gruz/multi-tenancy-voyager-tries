<?php

use App\Hostname;
use Illuminate\Database\Seeder;

class HostnamesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     */
    /**
     * Auto generated seed file.
     *
     * @return void
     */
    public function run()
    {
        $hostname = Hostname::firstOrNew(['fqdn' => 'voyager.test']);

        if (!$hostname->exists) {
            $hostname->fill([
                    'fqdn' => 'voyager.test',
                ])->save();
        }
    }
}
