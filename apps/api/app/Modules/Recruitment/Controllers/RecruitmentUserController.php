<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Repositories\RecruitmentUserRepository;
use App\Modules\Recruitment\Requests\ListRecruitmentUsersRequest;
use App\Modules\Recruitment\Resources\RecruitmentUserOptionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Owner picker source for leads, cases and jobs. A read-only lookup with no
 * rules beyond the query itself, so it uses the repository directly, like
 * RecruitmentPipelineController.
 */
class RecruitmentUserController extends Controller
{
    public function __construct(
        private readonly RecruitmentUserRepository $users,
    ) {}

    public function index(ListRecruitmentUsersRequest $request): AnonymousResourceCollection
    {
        return RecruitmentUserOptionResource::collection($this->users->assignable($request->searchTerm()));
    }
}
