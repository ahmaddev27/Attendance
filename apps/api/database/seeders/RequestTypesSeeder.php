<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RequestType;
use App\Models\Workflow;
use App\Shared\Enums\ApproverType;
use Illuminate\Database\Seeder;

/**
 * Three example request types (each with its own workflow) demonstrating
 * the M5 engine end to end. Idempotent on `request_types.code` so it is
 * safe to run alongside `migrate:fresh --seed` repeatedly in local/dev.
 */
class RequestTypesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBusinessMission();
        $this->seedAdvance();
        $this->seedComplaint();
    }

    private function seedBusinessMission(): void
    {
        if (RequestType::query()->where('code', 'business_mission')->exists()) {
            return;
        }

        $workflow = Workflow::query()->create([
            'name' => 'مسار موافقة طلب مأمورية عمل',
            'description' => 'الموظف يقدم الطلب، ثم يعتمده المدير المباشر، ثم مدير القسم.',
            'is_active' => true,
        ]);

        $workflow->steps()->createMany([
            [
                'step_order' => 1,
                'name' => 'موافقة المدير المباشر',
                'approver_type' => ApproverType::DirectManager,
                'can_reject' => true,
                'can_return' => true,
                'can_forward' => true,
            ],
            [
                'step_order' => 2,
                'name' => 'موافقة مدير القسم',
                'approver_type' => ApproverType::DepartmentManager,
                'can_reject' => true,
                'can_return' => true,
                'can_forward' => false,
            ],
        ]);

        RequestType::query()->create([
            'name' => 'طلب مأمورية عمل',
            'code' => 'business_mission',
            'description' => 'طلب تكليف بمأمورية عمل خارج مقر العمل الأساسي.',
            'icon' => 'briefcase',
            'color' => '#2678C4',
            'workflow_id' => $workflow->id,
            'form_schema' => [
                ['key' => 'destination', 'label' => 'الوجهة', 'type' => 'text', 'required' => true],
                ['key' => 'start_date', 'label' => 'تاريخ البداية', 'type' => 'date', 'required' => true],
                ['key' => 'end_date', 'label' => 'تاريخ النهاية', 'type' => 'date', 'required' => true],
                ['key' => 'purpose', 'label' => 'الغرض من المأمورية', 'type' => 'textarea', 'required' => true],
                ['key' => 'estimated_cost', 'label' => 'التكلفة التقديرية', 'type' => 'number', 'required' => false, 'min' => 0],
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function seedAdvance(): void
    {
        if (RequestType::query()->where('code', 'advance')->exists()) {
            return;
        }

        $workflow = Workflow::query()->create([
            'name' => 'مسار موافقة طلب سلفة',
            'description' => 'الموظف يقدم الطلب، ثم يعتمده المدير المباشر، ثم قسم المالية.',
            'is_active' => true,
        ]);

        $workflow->steps()->createMany([
            [
                'step_order' => 1,
                'name' => 'موافقة المدير المباشر',
                'approver_type' => ApproverType::DirectManager,
                'can_reject' => true,
                'can_return' => true,
                'can_forward' => false,
            ],
            [
                'step_order' => 2,
                'name' => 'موافقة قسم المالية',
                // Assumes a "finance" role. It doesn't need to exist yet —
                // ApproverResolver::resolveByRole() resolves an unseeded
                // role to no approvers rather than throwing, so this step
                // just fails to find anyone to approve it at runtime until
                // the role is created and assigned.
                'approver_type' => ApproverType::SpecificRole,
                'approver_ref' => 'finance',
                'can_reject' => true,
                'can_return' => false,
                'can_forward' => false,
            ],
        ]);

        RequestType::query()->create([
            'name' => 'طلب سلفة',
            'code' => 'advance',
            'description' => 'طلب سلفة على الراتب.',
            'icon' => 'wallet',
            'color' => '#2E9E6C',
            'workflow_id' => $workflow->id,
            'form_schema' => [
                ['key' => 'amount', 'label' => 'المبلغ', 'type' => 'number', 'required' => true, 'min' => 1],
                ['key' => 'reason', 'label' => 'السبب', 'type' => 'textarea', 'required' => true],
                ['key' => 'repayment_months', 'label' => 'عدد أشهر السداد', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 12],
            ],
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    private function seedComplaint(): void
    {
        if (RequestType::query()->where('code', 'complaint')->exists()) {
            return;
        }

        $workflow = Workflow::query()->create([
            'name' => 'مسار معالجة شكوى',
            'description' => 'الموظف يقدم الشكوى، ويعتمدها مدير القسم مباشرة.',
            'is_active' => true,
        ]);

        $workflow->steps()->create([
            'step_order' => 1,
            'name' => 'مراجعة مدير القسم',
            'approver_type' => ApproverType::DepartmentManager,
            'can_reject' => true,
            'can_return' => false,
            'can_forward' => true,
        ]);

        RequestType::query()->create([
            'name' => 'شكوى',
            'code' => 'complaint',
            'description' => 'تقديم شكوى للمراجعة من قبل الإدارة.',
            'icon' => 'message-triangle-warning',
            'color' => '#C4443A',
            'workflow_id' => $workflow->id,
            'form_schema' => [
                ['key' => 'subject', 'label' => 'موضوع الشكوى', 'type' => 'text', 'required' => true],
                ['key' => 'details', 'label' => 'تفاصيل الشكوى', 'type' => 'textarea', 'required' => true],
            ],
            'is_active' => true,
            'sort_order' => 3,
        ]);
    }
}
