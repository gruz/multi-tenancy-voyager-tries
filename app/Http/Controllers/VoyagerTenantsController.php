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

        $fqdn = $request->get('fqdn');
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

            // This line is stored just in case from the parent class method. Would try to save to tenant `hostnames`. 
            // So it's of no use. Leave here as an example and just in case.
            // $data = $this->insertUpdateData($request, $slug, $dataType->addRows, new $dataType->model_name());

            // !!! IMPORTANT 
            // If you add additional fields to system `hostnames` table
            // (it's assumed you have created and executed corresponding migrations, updated `hostnames` Voyager bread) 
            // and want to save the additional fields, just uncomment the line below.
            // $data = $this->insertUpdateData($request, $slug, $dataType->editRows, $data);

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

            parent::update($request, $id);

            return redirect()->to('//' . $request->fqdn  . '/admin/');
        } else {
            return parent::update($request, $id);
        }
    }


}
