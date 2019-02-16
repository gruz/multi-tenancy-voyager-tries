<?php

namespace App\Actions;

use TCG\Voyager\Actions\DeleteAction;

class TenantDeleteAction extends DeleteAction
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
            return parent::getAttributes();
        }
    }
}
