<?php 

namespace App;

use Hyn\Tenancy\Generators\Database\DefaultPasswordGenerator;
use Hyn\Tenancy\Contracts\Website;
use Illuminate\Contracts\Foundation\Application;

class DatabasePasswordGenerator extends DefaultPasswordGenerator
{
  /**
   * @var Application
   */
  protected $app;
  
  public function __construct(Application $app)
  {
      $this->app = $app;
  }
  
  public function generate(Website $website) : string
  {
        return crypt(sprintf(
            '%s.%d',
            $this->app['config']->get('app.key'),
            $website->id
        ), '$1$rasmusle$');
  }
}
