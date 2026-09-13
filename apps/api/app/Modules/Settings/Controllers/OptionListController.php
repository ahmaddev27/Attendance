<?php

declare(strict_types=1);

namespace App\Modules\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Requests\UpdateOptionListRequest;
use App\Modules\Settings\Services\OptionListService;
use App\Shared\Enums\OptionList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Picker lists: a read endpoint every form uses, and an admin surface
 * to replace or reset one list at a time. `{list}` binds to the
 * OptionList enum, so an unknown key is a 404 before any code runs.
 */
class OptionListController extends Controller
{
    public function __construct(private readonly OptionListService $options) {}

    /**
     * GET /api/option-lists
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->options->all()]);
    }

    /**
     * GET /api/admin/option-lists
     */
    public function adminIndex(): JsonResponse
    {
        $lists = array_map(
            fn (OptionList $list) => $this->present($list, $this->options->items($list)),
            OptionList::cases(),
        );

        return response()->json(['data' => $lists]);
    }

    /**
     * PUT /api/admin/option-lists/{list}
     */
    public function update(UpdateOptionListRequest $request, OptionList $list): JsonResponse
    {
        $items = $this->options->replace($list, $request->validated('items'), $request->user());

        return response()->json(['data' => $this->present($list, $items)]);
    }

    /**
     * DELETE /api/admin/option-lists/{list} — restores the code defaults.
     */
    public function destroy(Request $request, OptionList $list): JsonResponse
    {
        $items = $this->options->reset($list, $request->user());

        return response()->json(['data' => $this->present($list, $items)]);
    }

    /**
     * @param  list<array{value: string, label: string}>  $items
     * @return array<string, mixed>
     */
    private function present(OptionList $list, array $items): array
    {
        return [
            'key' => $list->value,
            'group' => $list->group(),
            'label' => $list->label(),
            'description' => $list->description(),
            'value_hint' => $list->valueHint(),
            'items' => $items,
        ];
    }
}
