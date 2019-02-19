<?php

namespace App\Actions;

use TCG\Voyager\Actions\ViewAction;

class TenantViewAction extends ViewAction
{
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
            return array_merge( parent::getAttributes(), [ 'target' => '_blank'] );
        }


    }

    public function getDefaultRoute()
    {
        $route = '//'. $this->data->fqdn;

        return $route;
    }
}
