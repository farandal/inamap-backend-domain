<?php

namespace Domain\App\Http\Controllers\API\Prerelease\Signup;

use Domain\App\Models\Prerelease\PrereleaseSignup;
use App\Http\Controllers\API\System\ReactAdminBaseController;

use Domain\App\Http\Controllers\API\Prerelease\Signup\PrereleaseSignupFilter;
use Domain\App\Http\Controllers\API\Prerelease\Signup\PrereleaseSignupRequest;
use Domain\App\Http\Controllers\API\Prerelease\Signup\PrereleaseSignupResource;
use Domain\App\Http\Controllers\API\Prerelease\Signup\PrereleaseSignupPolicy;

/**
 * System-admin CRUD over pre-release signups (react-admin resource).
 * The public capture endpoint lives in PublicPrereleaseSignupController.
 */
class PrereleaseSignupController extends ReactAdminBaseController
{
    public $resource         = PrereleaseSignupResource::class;
    public $requestValidator = PrereleaseSignupRequest::class;
    public $modelFilter      = PrereleaseSignupFilter::class;
    public $policy           = PrereleaseSignupPolicy::class;
    public $_model           = PrereleaseSignup::class;

    public function __construct()
    {
        $this->model = PrereleaseSignup::query();
    }

    public function _postGetOne($item)
    {
        return $this->resource::make($item);
    }

    public function _postList($data)
    {
        return $this->resource::collection($data);
    }

    public function _create($request)
    {
        $this->_authorize('create', null);

        $validated = app($this->requestValidator)->validated();

        $item = $this->model->create($validated);

        return $this->resource::make($item);
    }

    public function _update($request, $id, $item)
    {
        $this->_authorize('manage', $item);

        $validated = app($this->requestValidator)->validated();

        $item->update($validated);

        return $this->resource::make($item);
    }

    public function _delete($request, $id, $item)
    {
        $this->_authorize('delete', $item);
        $item->delete();
        return $this->resource::make($item);
    }
}
