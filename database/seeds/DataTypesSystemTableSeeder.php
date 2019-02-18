<?php

use Illuminate\Database\Seeder;
use TCG\Voyager\Models\DataType;

class DataTypesSystemTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     */
    public function run()
    {
        $dataType = $this->dataType('slug', 'hostnames');
        if (!$dataType->exists) {
            $dataType->fill([
                'name'                  => 'hostnames',
                'display_name_singular' => __('Hostname'),
                'display_name_plural'   => __('Hostnames'),
                'icon'                  => 'voyager-ship',
                'model_name'            => 'App\\Hostname',
                'controller'            => '\\App\\Http\\Controllers\\VoyagerTenantsController',
                'generate_permissions'  => 1,
                'description'           => '',
            ])->save();
        }
    }

    /**
     * [dataType description].
     *
     * @param [type] $field [description]
     * @param [type] $for   [description]
     *
     * @return [type] [description]
     */
    protected function dataType($field, $for)
    {
        return DataType::firstOrNew([$field => $for]);
    }
}
