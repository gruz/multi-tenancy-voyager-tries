<?php

namespace App\Http\Controllers;

use App\Tenant;
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Hyn\Tenancy\Models\Hostname;
use TCG\Voyager\Facades\Voyager;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Events\BreadDataAdded;
use TCG\Voyager\Events\BreadDataDeleted;


class VoyagerTenantsController extends \TCG\Voyager\Http\Controllers\VoyagerBaseController
{
    /**
     * Check if current request is an add of a tenant
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    private function isTenantOperation(Request $request) {
        $slug = $this->getSlug($request);

        $env = app(Environment::class);
        $fqdn = optional($env->hostname())->fqdn;

        if (\App\Tenant::getRootFqdn() !== $fqdn || 'hostnames' !== $slug) {
            return false;
        }

        return true;
    }

    /**
     * POST BRE(A)D - Store data.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        if (!$this->isTenantOperation($request)) {
            return parent::store($request);
        }

        $fqdn = $request->get('fqdn') . '.' . \App\Tenant::getRootFqdn();
        $request->offsetSet('fqdn', $fqdn);


        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        // Check permission
        $this->authorize('add', app($dataType->model_name));

        // Validate fields with ajax
        $val = $this->validateBread($request->all(), $dataType->addRows);

        if ($val->fails()) {
            return response()->json(['errors' => $val->messages()]);
        }

        if (!$request->has('_validate')) {

            $tenant = Tenant::registerTenant($fqdn);

            $data = Hostname::where('fqdn', $fqdn)->firstOrFail(); 

            // $data = $this->insertUpdateData($request, $slug, $dataType->addRows, new $dataType->model_name());

            event(new BreadDataAdded($dataType, $data));

            if ($request->ajax()) {
                return response()->json(['success' => true, 'data' => $data]);
            }

            return redirect()
                ->route("voyager.{$dataType->slug}.index")
                ->with([
                        'message'    => __('voyager::generic.successfully_added_new')." {$dataType->display_name_singular}",
                        'alert-type' => 'success',
                    ]);
        }
    }

    //***************************************
    //                _____
    //               |  __ \
    //               | |  | |
    //               | |  | |
    //               | |__| |
    //               |_____/
    //
    //         Delete an item BREA(D)
    //
    //****************************************

    public function destroy(Request $request, $id)
    {
        if (!$this->isTenantOperation($request)) {
            return parent::destroy($request);
        }

        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $fqdn = Hostname::where('id', $id)->firstOrFail(['fqdn'])->fqdn; 
        $systemSite = \App\Tenant::getRootFqdn();

        if ( $systemSite === $fqdn ) {
            return redirect()
                ->route("voyager.{$dataType->slug}.index")
                ->with([
                        'message'    => __('voyager::generic.system.site.cannot.be.deleted'),
                        'alert-type' => 'error',
                    ]);
        }

        // Check permission
        $this->authorize('delete', app($dataType->model_name));

        // Init array of IDs
        $ids = [];
        if (empty($id)) {
            // Bulk delete, get IDs from POST
            $ids = explode(',', $request->ids);
        } else {
            // Single item delete, get ID from URL
            $ids[] = $id;
        }

        $res = false;
        foreach ($ids as $id) {
            $data = call_user_func([$dataType->model_name, 'findOrFail'], $id, $columns = array('fqdn') );
            $this->cleanup($dataType, $data);
            $res = Tenant::deleteById($id);
        }

        $displayName = count($ids) > 1 ? $dataType->display_name_plural : $dataType->display_name_singular;

        // TODO ##mygruz20190213014253 
        // If deleting several domains, we can get partial successfull result. We must properly handle the situations.
        // Currently if we have at least one (or last) success, we return a success message.
        $data = $res
            ? [
                'message'    => __('voyager::generic.successfully_deleted')." {$displayName}",
                'alert-type' => 'success',
            ]
            : [
                'message'    => __('voyager::generic.error_deleting')." {$displayName}",
                'alert-type' => 'error',
            ];

        if ($res) {
            event(new BreadDataDeleted($dataType, $data));
        }

        return redirect()->route("voyager.{$dataType->slug}.index")->with($data);
    }

    // POST BR(E)AD
    public function update(Request $request, $id)
    {
        if (!$this->isTenantOperation($request)) {
            return parent::update($request, $id);
        }

        $systemSiteId = Hostname::where('website_id', null)->first()->id;
        $systemSite = \App\Tenant::getRootFqdn();

        if ( $systemSiteId === intval($id) ) {

            $slug = $this->getSlug($request);

            $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();
    
            // Compatibility with Model binding.
            $id = $id instanceof Model ? $id->{$id->getKeyName()} : $id;
    
            $data = call_user_func([$dataType->model_name, 'findOrFail'], $id);
    
            // Check permission
            $this->authorize('edit', $data);
    
            // Validate fields with ajax
            $val = $this->validateBread($request->all(), $dataType->editRows, $dataType->name, $id);
    
            if ($val->fails()) {
                return response()->json(['errors' => $val->messages()]);
            }

            if (!$request->ajax()) {
                $newSystemSite = $request->fqdn;
                $hostnames = Hostname::where('website_id', '<>', null)->get();

                DB::beginTransaction();
                // do all your updates here
        
                foreach ($hostnames as $hostname) {
                    $newFqdn = preg_replace('/(.*)(\.' . $systemSite . '$)/', '$1.' . $newSystemSite, $hostname->fqdn);
        
                    DB::table('hostnames')
                            ->where('id', '=', $hostname->id)
                            ->update([
                                'fqdn' => $newFqdn  // update your field(s) here
                            ]);
                }
                // when done commit
                DB::commit();

                parent::update($request, $id);

                $slug = $this->getSlug($request);

                $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

                return redirect()
                    ->to('//' . $newSystemSite  . '/admin/');
            }

        } else {
            $fqdn = $request->get('fqdn') . '.' . \App\Tenant::getRootFqdn();
            $request->offsetSet('fqdn', $fqdn);
    
            return parent::update($request, $id);
        }
    }


    //***************************************
    //                _____
    //               |  __ \
    //               | |__) |
    //               |  _  /
    //               | | \ \
    //               |_|  \_\
    //
    //  Read an item of our Data Type B(R)EAD
    //
    //****************************************
    
    public function show(Request $request, $id)
    {
        if (!$this->isTenantOperation($request)) {
            return parent::show($request, $id);
        }

        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);
            $dataTypeContent = call_user_func([$model, 'findOrFail'], $id);
        } else {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        // Replace relationships' keys for labels and create READ links if a slug is provided.
        $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType, true);
        
        $systemSite = \App\Tenant::getRootFqdn();
        $dataTypeContent->fqdn = preg_replace('/(.*)(\.' . $systemSite . '$)/', '$1', $dataTypeContent->fqdn);

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'read');

        // Check permission
        $this->authorize('read', $dataTypeContent);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        $view = 'voyager::bread.read';

        if (view()->exists("voyager::$slug.read")) {
            $view = "voyager::$slug.read";
        }

        return Voyager::view($view, compact('dataType', 'dataTypeContent', 'isModelTranslatable'));
    }

}
