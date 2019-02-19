<?php

namespace App\Actions;

use TCG\Voyager\Actions\AbstractAction;

class TenantLoginAction extends AbstractAction
{
    public function getTitle()
    {
        return __('voyager::generic.login');
    }

    public function getIcon()
    {
        return 'voyager-ship';
    }

    public function getPolicy()
    {
        return 'read';
    }

    public function getDataType()
    {
        return 'hostnames';
    }

    public function getAttributes()
    {
        $fqdn = $this->data->fqdn; 
        $systemSite = \App\Tenant::getRootFqdn();

        if ( $systemSite === $fqdn ) {
            return [
                'class' => 'hide',
            ];
        }
        else {

            return [
                'class' => 'btn btn-sm btn-warning pull-left login',
                'target' => '_blank'
            ];
        }
    }

    public function getDefaultRoute()
    {
        $route = '//'. $this->data->fqdn . '/admin';

        return $route;
    }
}
