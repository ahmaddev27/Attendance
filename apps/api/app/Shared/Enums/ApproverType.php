<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * How a WorkflowStep's approver is determined at runtime — see
 * ApproverResolver for how each case is turned into actual Employee(s).
 *
 * `approver_ref` on the owning WorkflowStep carries the extra piece of
 * data each case (other than DirectManager/DepartmentManager, which are
 * derived purely from the requesting employee's own org placement) needs
 * to resolve:
 *  - SpecificEmployee -> an employees.id
 *  - SpecificRole     -> a Spatie role name
 *  - FormField        -> a key into the request's form_data holding an
 *                        employee id (e.g. a "reviewed_by" field the
 *                        submitter picks on the form itself)
 */
enum ApproverType: string
{
    case DirectManager = 'direct_manager';
    case DepartmentManager = 'department_manager';
    case SpecificEmployee = 'specific_employee';
    case SpecificRole = 'specific_role';
    case FormField = 'form_field';
}
